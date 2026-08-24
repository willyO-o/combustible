<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehiculoExternoRequest;
use App\Models\VehiculoExterno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class VehiculoExternoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = VehiculoExterno::query();

        if ($request->filled('nro_placa')) {
            $query->where('nro_placa', 'like', '%'.$request->nro_placa.'%');
        }

        $vehiculosExternos = $query->orderBy('nro_placa')->paginate(10)->withQueryString();

        return Inertia::render('VehiculosExternos/Index', [
            'vehiculosExternos' => $vehiculosExternos,
            'filters' => $request->only(['nro_placa']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Registra un vehículo externo. Igual que MaterialController::store(),
     * también es usado por el componente reutilizable VehiculoExternoFormModal
     * desde otros formularios (ej. cargas de material): cuando la petición
     * viene por AJAX se responde en JSON con el registro creado en vez de
     * redirigir, para que el formulario que lo invocó siga en la misma
     * pantalla y pueda seleccionar el vehículo recién creado.
     */
    public function store(VehiculoExternoRequest $request): RedirectResponse|JsonResponse
    {
        $vehiculoExterno = VehiculoExterno::create($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Vehículo externo registrado exitosamente.',
                'data' => $vehiculoExterno,
            ]);
        }

        return redirect()->route('vehiculos-externos.index')
            ->with('success', 'Vehículo externo registrado exitosamente.');
    }

    public function update(VehiculoExternoRequest $request, VehiculoExterno $vehiculoExterno): RedirectResponse|JsonResponse
    {
        $vehiculoExterno->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Vehículo externo actualizado exitosamente.',
                'data' => $vehiculoExterno,
            ]);
        }

        return redirect()->route('vehiculos-externos.index')
            ->with('success', 'Vehículo externo actualizado exitosamente.');
    }

    public function destroy(VehiculoExterno $vehiculoExterno): RedirectResponse
    {
        if (DB::table('carga_material')->where('id_vehiculo_externo', $vehiculoExterno->id)->exists()) {
            return redirect()->route('vehiculos-externos.index')
                ->with('error', 'No se puede eliminar: existen cargas registradas con este vehículo.');
        }

        $vehiculoExterno->delete();

        return redirect()->route('vehiculos-externos.index')
            ->with('success', 'Vehículo externo eliminado exitosamente.');
    }
}
