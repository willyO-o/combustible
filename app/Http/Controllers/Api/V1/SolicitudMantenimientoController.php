<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SolicitudMantenimiento\CreateSolicitudMantenimientoAction;
use App\Actions\SolicitudMantenimiento\ListSolicitudesMantenimientoAction;
use App\Actions\SolicitudMantenimiento\UpdateSolicitudMantenimientoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudMantenimientoRequest;
use App\Libraries\Reportes;
use App\Models\SolicitudMantenimiento;
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
     * El acceso ya queda restringido a conductores por SolicitudMantenimientoRequest::authorize().
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
