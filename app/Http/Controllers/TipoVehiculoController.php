<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoVehiculoRequest;
use App\Models\TipoVehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoVehiculoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TipoVehiculo::query();

        if ($request->filled('tipo_vehiculo')) {
            $query->where('tipo_vehiculo', 'like', '%' . $request->tipo_vehiculo . '%');
        }
        if ($request->filled('estado_tipo_vehiculo')) {
            $query->where('estado_tipo_vehiculo', $request->estado_tipo_vehiculo);
        }

        $tipos = $query->orderBy('tipo_vehiculo')->paginate(10)->withQueryString();

        return Inertia::render('TiposVehiculo/Index', [
            'tipos'   => $tipos,
            'filters' => $request->only(['tipo_vehiculo', 'estado_tipo_vehiculo']),
            'flash'   => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('TiposVehiculo/Create');
    }

    public function store(TipoVehiculoRequest $request): RedirectResponse
    {
        TipoVehiculo::create($request->validated());

        return redirect()->route('tipos-vehiculo.index')
            ->with('success', 'Tipo de vehículo registrado exitosamente.');
    }

    public function edit(TipoVehiculo $tipoVehiculo): Response
    {
        return Inertia::render('TiposVehiculo/Edit', [
            'tipo' => $tipoVehiculo,
        ]);
    }

    public function update(TipoVehiculoRequest $request, TipoVehiculo $tipoVehiculo): RedirectResponse
    {
        $tipoVehiculo->update($request->validated());

        return redirect()->route('tipos-vehiculo.index')
            ->with('success', 'Tipo de vehículo actualizado exitosamente.');
    }

    public function destroy(TipoVehiculo $tipoVehiculo): RedirectResponse
    {
        if ($tipoVehiculo->vehiculos()->count() > 0) {
            return redirect()->route('tipos-vehiculo.index')
                ->with('error', 'No se puede eliminar: existen vehículos asignados a este tipo.');
        }

        $tipoVehiculo->delete();

        return redirect()->route('tipos-vehiculo.index')
            ->with('success', 'Tipo de vehículo eliminado exitosamente.');
    }
}
