<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\VehiculoExternoRequest;
use App\Models\VehiculoExterno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehiculoExternoController extends Controller
{
    /**
     * Lista el catálogo de vehículos externos (no pertenecen a la flota
     * propia), para búsqueda o selección desde la app móvil al abrir una
     * carga de material. También se envía un resumen de este mismo catálogo
     * en GET /parametros/colecciones (control_cargas.vehiculos_externos).
     */
    public function index(Request $request): JsonResponse
    {
        $query = VehiculoExterno::query();

        if ($request->filled('nro_placa')) {
            $query->where('nro_placa', 'like', '%'.$request->nro_placa.'%');
        }

        return response()->json([
            'data' => $query->orderBy('nro_placa')->get(),
        ]);
    }

    /**
     * Registra un vehículo externo bajo demanda (p.ej. si al abrir una
     * carga no se encuentra el vehículo buscado en el catálogo), igual que
     * el alta rápida del panel web (VehiculoExternoController::store()).
     */
    public function store(VehiculoExternoRequest $request): JsonResponse
    {
        $vehiculoExterno = VehiculoExterno::create($request->validated());

        return response()->json([
            'message' => 'Vehículo externo registrado exitosamente.',
            'data' => $vehiculoExterno,
        ], 201);
    }
}
