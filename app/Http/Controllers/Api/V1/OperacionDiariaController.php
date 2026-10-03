<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\OperacionDiaria\CreateOperacionDiariaAction;
use App\Actions\OperacionDiaria\ListOperacionesDiariasAction;
use App\Actions\OperacionDiaria\UpdateOperacionDiariaAction;
use App\Exceptions\AreaNoAsignadaException;
use App\Exceptions\ConductorNoAsignadoException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OperacionStoreRequest;
use App\Libraries\Reportes;
use App\Models\OperacionDiaria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OperacionDiariaController extends Controller
{
    public function index(Request $request, ListOperacionesDiariasAction $listAction): JsonResponse
    {

        $filters = $request->only(['nro_placa', 'fecha_desde', 'fecha_hasta', 'estado_operacion']);

        $operaciones = $listAction->execute($filters, $request->user(), $request->input('per_page', 10));

        return response()->json($operaciones);
    }

    /**
     * Un conductor "puro" (sin ningún rol de gestión) siempre opera como él
     * mismo: se ignora cualquier `id_conductor` que envíe la petición y se
     * usa el suyo propio (un vehículo puede tener varios conductores
     * asignados a la vez — titular + provisionales — así que dejar pasar lo
     * que mande el cliente permitiría atribuir la operación a otro
     * conductor asignado al mismo vehículo). Un conductor que ADEMÁS es
     * jefe de área, administrador o super-admin puede gestionar operaciones
     * de otros conductores (p.ej. corrigiendo una jornada de su equipo), así
     * que en ese caso se respeta el `id_conductor` que llegó validado —
     * mismo criterio de "conductor puro" ya usado en
     * Api\V1\CargaMaterialController.
     */
    private function datosConConductorResuelto(OperacionStoreRequest $request): array
    {
        $datos = $request->validated();
        $user = $request->user();

        $esConductorPuro = $user->hasRole('conductor') && ! $user->hasAnyRole(['jefe-area', 'administrador', 'super-admin']);

        if ($esConductorPuro && $user->persona?->conductor) {
            $datos['id_conductor'] = $user->persona->conductor->id;
        }

        return $datos;
    }

    /**
     * Almacena una nueva operación diaria.
     */
    public function store(OperacionStoreRequest $request, CreateOperacionDiariaAction $action): JsonResponse
    {
        try {

            $operacionDiaria = $action->execute($this->datosConConductorResuelto($request));
            $operacionDiaria->load(['conductor.persona', 'vehiculo', 'area', 'actividadesRealizadas', 'mantenimientosOperacion'])
                ->cargarMaterialDeActividades();

            return response()->json([
                'message' => 'Operación diaria creada exitosamente.',
                'data' => $operacionDiaria,
            ], 201);
        } catch (AreaNoAsignadaException $e) {
            return response()->json([
                'message' => 'Error: El conductor no tiene un área asignada.',
            ], 422);
        } catch (ConductorNoAsignadoException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Error al crear la operación diaria.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(OperacionDiaria $operacionDiaria)
    {
        $operacion = $operacionDiaria->load(['conductor.persona', 'vehiculo', 'area', 'verificador', 'actividadesRealizadas', 'mantenimientosOperacion'])
            ->cargarMaterialDeActividades();

        return response()->json([
            'data' => $operacion,
        ]);
    }

    public function update(OperacionStoreRequest $request, OperacionDiaria $operacionDiaria, UpdateOperacionDiariaAction $action)
    {
        try {
            $operacionDiaria = $action->execute($operacionDiaria, $this->datosConConductorResuelto($request));
            $operacionDiaria->load(['conductor.persona', 'vehiculo', 'area', 'actividadesRealizadas', 'mantenimientosOperacion'])
                ->cargarMaterialDeActividades();

            return response()->json([
                'message' => 'Operación diaria actualizada exitosamente.',
                'data' => $operacionDiaria,
            ]);

        } catch (ConductorNoAsignadoException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la operación diaria.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Borra del disco las fotos de evidencia de los controles de
     * mantenimiento de la operación, antes de desasociarlos/eliminarla (si
     * no, quedarían huérfanas en storage/app/public).
     */
    private function eliminarEvidenciasMantenimiento(OperacionDiaria $operacionDiaria): void
    {
        $operacionDiaria->mantenimientosOperacion()
            ->get()
            ->pluck('pivot.evidencia')
            ->filter()
            ->each(fn ($ruta) => Storage::disk('public')->delete($ruta));
    }

    public function destroy(OperacionDiaria $operacionDiaria)
    {
        if ($operacionDiaria->estado === 'VERIFICADO') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una operación diaria que ya ha sido verificada.',
            ], 403);
        }

        try {

            $this->eliminarEvidenciasMantenimiento($operacionDiaria);

            $operacionDiaria->actividadesRealizadas()->detach();
            $operacionDiaria->mantenimientosOperacion()->detach();

            $operacionDiaria->delete();

            return response()->json([
                'success' => true,
                'message' => 'Operación diaria eliminada exitosamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la operación diaria: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descarga el reporte de la operación diaria en PDF (mismo formato que el
     * sistema web), pensado para que la app móvil lo guarde y lo muestre.
     * Un conductor sólo puede descargar el reporte de sus propias operaciones.
     */
    public function pdf(Request $request, OperacionDiaria $operacionDiaria): Response
    {
        if ($request->user()->esConductorPuro() && $operacionDiaria->id_conductor !== $request->user()->id_persona) {
            abort(403, 'No tienes permiso para descargar este reporte.');
        }

        $operacion = $operacionDiaria
            ->load(['conductor.persona', 'vehiculo', 'area', 'verificador', 'actividadesRealizadas', 'mantenimientosOperacion'])
            ->cargarMaterialDeActividades();

        // El catálogo de tipos de mantenimiento lo resuelve el propio reporte
        // desde la base cuando no se le pasa (ver Reportes::generarReporteOperacionDiaria).
        $contenido = (new Reportes)->generarReporteOperacionDiaria($operacion, null, 'S');

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="reporte_operacion_'.str_replace('/', '-', (string) $operacion->nro).'.pdf"',
        ]);
    }

    public function validarActividad(Request $request)
    {
        // Validar los campos requeridos
        $validated = $request->validate([
            'lugar' => 'required_without:origen|nullable|string',
            'origen' => 'required_without:lugar|nullable|string',
            'destino' => 'required_without:lugar|nullable|string',
            'id_material' => ['nullable', 'integer', Rule::exists('material', 'id')],
            'actividad' => 'required|string|min:3',
            'cantidad' => 'required|numeric|min:1',
            'unidad_medida' => 'required|string',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i',
        ]);

        // Agregar la actividad al arreglo de actividades_realizadas

        return response()->json([
            'message' => 'Actividad agregada exitosamente.',
            'data' => $validated,
        ]);
    }
}
