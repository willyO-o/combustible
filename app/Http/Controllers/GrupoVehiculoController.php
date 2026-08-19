<?php

namespace App\Http\Controllers;

use App\Http\Requests\GrupoVehiculoRequest;
use App\Models\GrupoVehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrupoVehiculoController extends Controller
{
    public function index(Request $request): Response
    {
        $grupos = GrupoVehiculo::withCount('tiposVehiculos')
            ->orderBy('grupo_vehiculo')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('GruposVehiculo/Index', [
            'grupos' => $grupos,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function store(GrupoVehiculoRequest $request): RedirectResponse
    {
        GrupoVehiculo::create($request->validated());

        return redirect()->route('grupos-vehiculo.index')
            ->with('success', 'Grupo de vehículo registrado exitosamente.');
    }

    public function update(GrupoVehiculoRequest $request, GrupoVehiculo $grupoVehiculo): RedirectResponse
    {
        $grupoVehiculo->update($request->validated());

        return redirect()->route('grupos-vehiculo.index')
            ->with('success', 'Grupo de vehículo actualizado exitosamente.');
    }

    public function destroy(GrupoVehiculo $grupoVehiculo): RedirectResponse
    {
        if ($grupoVehiculo->tiposVehiculos()->count() > 0) {
            return redirect()->route('grupos-vehiculo.index')
                ->with('error', 'No se puede eliminar: existen tipos de vehículo asignados a este grupo.');
        }

        $grupoVehiculo->delete();

        return redirect()->route('grupos-vehiculo.index')
            ->with('success', 'Grupo de vehículo eliminado exitosamente.');
    }
}
