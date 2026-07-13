<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehiculoRequest;
use App\Models\TipoCombustible;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class VehiculoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Vehiculo::with(['tipoCombustible', 'tipoVehiculo','conductorAsignado']);

        if ($request->filled('nro_placa')) {
            $query->where('nro_placa', 'like', '%' . $request->nro_placa . '%');
        }
        if ($request->filled('marca')) {
            $query->where('marca', 'like', '%' . $request->marca . '%');
        }
        if ($request->filled('estado_vehiculo')) {
            $query->where('estado_vehiculo', $request->estado_vehiculo);
        }
        if ($request->filled('id_tipo_vehiculo')) {
            $query->where('id_tipo_vehiculo', $request->id_tipo_vehiculo);
        }

        $vehiculos = $query
            ->orderBy('nro_placa')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Vehiculos/Index', [
            'vehiculos'       => $vehiculos,
            'tiposVehiculo'   => TipoVehiculo::where('estado_tipo_vehiculo', 'ACTIVO')->orderBy('tipo_vehiculo')->get(['id', 'tipo_vehiculo']),
            'filters'         => $request->only(['nro_placa', 'marca', 'estado_vehiculo', 'id_tipo_vehiculo']),
            'flash'           => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Vehiculos/Create', [
            'tiposCombustible' => TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')->orderBy('tipo_combustible')->get(['id', 'tipo_combustible']),
            'tiposVehiculo'    => TipoVehiculo::where('estado_tipo_vehiculo', 'ACTIVO')->orderBy('tipo_vehiculo')->get(['id', 'tipo_vehiculo']),
        ]);
    }

    public function store(VehiculoRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('fotografia')) {
            $data['fotografia'] = $request->file('fotografia')->store('vehiculos', 'public');
        }

        Vehiculo::create($data);

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo registrado exitosamente.');
    }

    public function show(Vehiculo $vehiculo): Response
    {
        // $historialAsignaciones = $vehiculo->historialAsignacionesConductores()->get();
        return Inertia::render('Vehiculos/Show', [
            'vehiculo'             => $vehiculo->load(['tipoCombustible', 'tipoVehiculo']),
            'historialAsignaciones' => [],
        ]);
    }
    public function edit(Vehiculo $vehiculo): Response
    {
        return Inertia::render('Vehiculos/Edit', [
            'vehiculo'         => $vehiculo->load(['tipoCombustible', 'tipoVehiculo']),
            'tiposCombustible' => TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')->orderBy('tipo_combustible')->get(['id', 'tipo_combustible']),
            'tiposVehiculo'    => TipoVehiculo::where('estado_tipo_vehiculo', 'ACTIVO')->orderBy('tipo_vehiculo')->get(['id', 'tipo_vehiculo']),
        ]);
    }

    public function update(VehiculoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('fotografia')) {
            if ($vehiculo->fotografia) {
                Storage::disk('public')->delete($vehiculo->fotografia);
            }
            $data['fotografia'] = $request->file('fotografia')->store('vehiculos', 'public');
        } else {
            unset($data['fotografia']);
        }

        $vehiculo->update($data);

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo actualizado exitosamente.');
    }

    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        if ($vehiculo->fotografia) {
            Storage::disk('public')->delete($vehiculo->fotografia);
        }

        $vehiculo->delete();

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo eliminado exitosamente.');
    }
}
