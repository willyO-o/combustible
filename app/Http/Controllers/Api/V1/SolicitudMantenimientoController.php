<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\OrdenTrabajo\CreateOrdenTrabajoAction;
use App\Actions\SolicitudMantenimiento\CreateSolicitudMantenimientoAction;
use App\Actions\SolicitudMantenimiento\ListSolicitudesMantenimientoAction;
use App\Actions\SolicitudMantenimiento\UpdateSolicitudMantenimientoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmitirOrdenTrabajoRequest;
use App\Http\Requests\SolicitudMantenimientoRequest;
use App\Libraries\Reportes;
use App\Models\SolicitudMantenimiento;
use App\Models\Taller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SolicitudMantenimientoController extends Controller
{
    /**
     * Lista las solicitudes de mantenimiento.
     */
    public function index(Request $request, ListSolicitudesMantenimientoAction $action): JsonResponse
    {
        $filters = $request->only(['estado', 'tipo_mantenimiento', 'id_vehiculo']);

        $solicitudes = $action->execute($filters, $request->user(), $request->input('per_page', 10));

        return response()->json($solicitudes);
    }

    /**
     * Registra una nueva solicitud de mantenimiento.
     *
     * El acceso ya queda restringido por SolicitudMantenimientoRequest::authorize()
     * (conductor, jefe-area, administrador o super-admin).
     */
    public function store(SolicitudMantenimientoRequest $request, CreateSolicitudMantenimientoAction $action): JsonResponse
    {
        try {
            $solicitud = $action->execute($request->validated(), $request->user());
            $solicitud->load(['vehiculo', 'conductor.persona', 'usuarioRegistra']);

            return response()->json([
                'message' => 'Solicitud de mantenimiento registrada exitosamente.',
                'data' => $solicitud,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la solicitud de mantenimiento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el detalle de una solicitud de mantenimiento.
     */
    public function show(SolicitudMantenimiento $solicitud): JsonResponse
    {
        $solicitud->load(['vehiculo', 'conductor.persona', 'usuarioRegistra', 'ordenTrabajo']);

        return response()->json([
            'data' => $solicitud,
        ]);
    }

    /**
     * Actualiza una solicitud de mantenimiento (sólo si sigue PENDIENTE).
     *
     * El acceso ya queda restringido a conductores por SolicitudMantenimientoRequest::authorize().
     */
    public function update(SolicitudMantenimientoRequest $request, SolicitudMantenimiento $solicitud, UpdateSolicitudMantenimientoAction $action): JsonResponse
    {
        if ($solicitud->estado !== 'PENDIENTE') {
            return response()->json([
                'message' => 'Sólo se pueden editar solicitudes en estado PENDIENTE.',
            ], 403);
        }

        try {
            $solicitud = $action->execute($solicitud, $request->validated());
            $solicitud->load(['vehiculo', 'conductor.persona', 'usuarioRegistra']);

            return response()->json([
                'message' => 'Solicitud de mantenimiento actualizada exitosamente.',
                'data' => $solicitud,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la solicitud de mantenimiento.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Datos para armar el formulario de emisión de la orden de trabajo de una
     * solicitud (equivalente a OrdenTrabajo/Create.vue con la solicitud
     * preseleccionada), en un solo llamado:
     * - solicitud: los datos heredados (vehículo, conductor, categoría y
     *   lecturas), que la app muestra bloqueados;
     * - valores_por_defecto: la nota del emisor precargada con la descripción
     *   del problema, editable;
     * - catalogos: técnicos activos y talleres externos activos.
     *
     * Mismo permiso y misma disponibilidad de la solicitud que emitirOrden().
     */
    public function formularioOrden(EmitirOrdenTrabajoRequest $request, SolicitudMantenimiento $solicitud): JsonResponse
    {
        if (! $this->solicitudDisponibleParaOrden($solicitud)) {
            return $this->solicitudNoDisponibleResponse();
        }

        $solicitud->load([
            'vehiculo:id,codigo,nro_placa,marca,modelo,tipo_medicion',
            'conductor.persona:id,nombres,paterno,materno,ci',
        ]);

        return response()->json([
            'data' => [
                'solicitud' => $solicitud,
                'valores_por_defecto' => [
                    'nota_emisor' => $solicitud->descripcion_problema,
                ],
                'catalogos' => [
                    'tecnicos' => User::where('estado_usuario', 'ACTIVO')
                        ->whereHas('roles', fn ($query) => $query->where('name', 'tecnico-mantenimiento'))
                        ->orderBy('name')
                        ->get(['id', 'name']),
                    'talleres' => Taller::where('estado_taller', 'ACTIVO')
                        ->orderBy('razon_social')
                        ->get(['id', 'razon_social', 'nit']),
                ],
            ],
        ]);
    }

    /**
     * Convierte una solicitud PENDIENTE en una orden de trabajo (equivalente al
     * botón "Generar orden" del sistema web). Sólo se reciben los datos
     * editables (técnico, nota del emisor, taller, observación): vehículo,
     * conductor, categoría y lecturas se heredan de la solicitud, que pasa a
     * APROBADA. La emisión es la misma que la del web (CreateOrdenTrabajoAction).
     *
     * El acceso ya queda restringido al permiso mantenimiento.ordenes.crear por
     * EmitirOrdenTrabajoRequest::authorize().
     */
    public function emitirOrden(EmitirOrdenTrabajoRequest $request, SolicitudMantenimiento $solicitud, CreateOrdenTrabajoAction $action): JsonResponse
    {
        if (! $this->solicitudDisponibleParaOrden($solicitud)) {
            return $this->solicitudNoDisponibleResponse();
        }

        try {
            $orden = $action->execute([
                ...$request->validated(),
                'id_solicitud_mantenimiento' => $solicitud->id,
            ]);
            $orden->load([
                'vehiculo',
                'conductor.persona',
                'solicitudMantenimiento',
                'taller',
                'usuarioEmite:id,name',
                'usuarioEjecuta:id,name',
            ]);

            return response()->json([
                'message' => 'Orden de trabajo N° '.$orden->nro.' emitida exitosamente.',
                'data' => $orden,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al emitir la orden de trabajo.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Una solicitud sólo puede generar orden si sigue PENDIENTE y no tiene ya
     * una orden: estado + "sin orden asignada" en una sola consulta (NOT EXISTS).
     */
    private function solicitudDisponibleParaOrden(SolicitudMantenimiento $solicitud): bool
    {
        return SolicitudMantenimiento::whereKey($solicitud->id)
            ->where('estado', 'PENDIENTE')
            ->whereDoesntHave('ordenTrabajo')
            ->exists();
    }

    private function solicitudNoDisponibleResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Sólo las solicitudes PENDIENTES sin orden de trabajo pueden generar una orden.',
        ], 422);
    }

    /**
     * Descarga el PDF de la solicitud para verla/guardarla desde la app móvil.
     * Un conductor sólo puede descargar sus propias solicitudes.
     */
    public function pdf(Request $request, SolicitudMantenimiento $solicitud): Response
    {
        if ($request->user()->hasRole('conductor') && $solicitud->id_conductor !== $request->user()->id_persona) {
            abort(403, 'No tienes permiso para descargar esta solicitud.');
        }

        $solicitud->load([
            'vehiculo', 'conductor.persona', 'usuarioRegistra',
            'ordenTrabajo.detalles.repuesto', 'ordenTrabajo.usuarioEjecuta', 'ordenTrabajo.usuarioEmite',
        ]);

        $contenido = (new Reportes)->generarSolicitudMantenimiento($solicitud, 'S');

        // $solicitud->nro tiene formato "NNNNNN/GESTION"; el '/' no es válido dentro
        // de un nombre de archivo, así que se reemplaza por '-' sólo para el header.
        $nombreArchivo = 'solicitud_mantenimiento_'.str_replace('/', '-', $solicitud->nro).'.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombreArchivo.'"',
        ]);
    }
}
