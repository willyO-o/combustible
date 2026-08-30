<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\OperacionDiaria\CreateOperacionDiariaAction;
use App\Actions\OperacionDiaria\ListOperacionesDiariasAction;
use App\Actions\OperacionDiaria\UpdateOperacionDiariaAction;
use App\Exceptions\AreaNoAsignadaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OperacionStoreRequest;
use App\Models\OperacionDiaria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Almacena una nueva operación diaria.
     */
    public function store(OperacionStoreRequest $request, CreateOperacionDiariaAction $action): JsonResponse
    {
        try {

            $operacionDiaria = $action->execute($request->validated());
            $operacionDiaria->load(['conductor.persona', 'vehiculo', 'area', 'actividadesRealizadas'])
                ->cargarMaterialDeActividades();

            return response()->json([
                'message' => 'Operación diaria creada exitosamente.',
                'data' => $operacionDiaria,
            ], 201);
        } catch (AreaNoAsignadaException $e) {
            return response()->json([
                'message' => 'Error: El conductor no tiene un área asignada.',
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
        $operacion = $operacionDiaria->load(['conductor.persona', 'vehiculo', 'area', 'verificador', 'actividadesRealizadas'])
            ->cargarMaterialDeActividades();

        return response()->json([
            'data' => $operacion,
        ]);
    }

    public function update(OperacionStoreRequest $request, OperacionDiaria $operacionDiaria, UpdateOperacionDiariaAction $action)
    {
        try {
            $operacionDiaria = $action->execute($operacionDiaria, $request->validated());
            $operacionDiaria->load(['conductor.persona', 'vehiculo', 'area', 'actividadesRealizadas'])
                ->cargarMaterialDeActividades();

            return response()->json([
                'message' => 'Operación diaria actualizada exitosamente.',
                'data' => $operacionDiaria,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la operación diaria.',
                'error' => $e->getMessage(),
            ], 500);
        }
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
