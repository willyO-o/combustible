<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehiculoAreaRequest;
use App\Http\Requests\VehiculoRequest;
use App\Models\Area;
use App\Models\TipoCombustible;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class VehiculoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Vehiculo::with(['tipoCombustible', 'tipoVehiculo', 'conductorAsignado.persona', 'areasAsignadas']);

        if ($request->filled('nro_placa')) {
            $query->where('nro_placa', 'like', '%'.$request->nro_placa.'%');
        }
        if ($request->filled('codigo')) {
            $query->where('codigo', 'like', '%'.$request->codigo.'%');
        }
        if ($request->filled('marca')) {
            $query->where('marca', 'like', '%'.$request->marca.'%');
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
            'vehiculos' => $vehiculos,
            'tiposVehiculo' => TipoVehiculo::where('estado_tipo_vehiculo', 'ACTIVO')->orderBy('tipo_vehiculo')->get(['id', 'tipo_vehiculo']),
            'areas' => Area::where('estado_area', 'ACTIVO')->orderBy('nombre_area')->get(['id', 'nombre_area']),
            'filters' => $request->only(['nro_placa', 'codigo', 'marca', 'estado_vehiculo', 'id_tipo_vehiculo']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Vehiculos/Create', [
            'vehiculo' => null,
            'tiposCombustible' => TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')->orderBy('tipo_combustible')->get(['id', 'tipo_combustible']),
            'tiposVehiculo' => TipoVehiculo::where('estado_tipo_vehiculo', 'ACTIVO')->orderBy('tipo_vehiculo')->get(['id', 'tipo_vehiculo']),
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
            'vehiculo' => $vehiculo->load(['tipoCombustible', 'tipoVehiculo']),
            'historialAsignaciones' => [],
        ]);
    }

    public function edit(Vehiculo $vehiculo): Response
    {
        return Inertia::render('Vehiculos/Create', [
            'vehiculo' => $vehiculo->load(['tipoCombustible', 'tipoVehiculo']),
            'tiposCombustible' => TipoCombustible::where('estado_tipo_combustible', 'ACTIVO')->orderBy('tipo_combustible')->get(['id', 'tipo_combustible']),
            'tiposVehiculo' => TipoVehiculo::where('estado_tipo_vehiculo', 'ACTIVO')->orderBy('tipo_vehiculo')->get(['id', 'tipo_vehiculo']),
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

    /**
     * Asigna (o reasigna) un vehículo a un área: se puede prestar a otras
     * áreas de forma activa o provisional. La asignación activa/provisional
     * anterior de este vehículo (si la tiene, sin importar el área) queda
     * REASIGNADA con fecha_reasignacion=ahora; nunca conviven dos
     * asignaciones activas para el mismo vehículo.
     */
    public function asignarArea(VehiculoAreaRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        DB::transaction(function () use ($request, $vehiculo) {
            VehiculoArea::where('id_vehiculo', $vehiculo->id)
                ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
                ->where(function ($query) {
                    $query->whereNull('fecha_culminacion')
                        ->orWhere('fecha_culminacion', '>', now());
                })
                ->update([
                    'estado_asignacion' => 'REASIGNADO',
                    'fecha_reasignacion' => now(),
                ]);

            $esProvisional = $request->validated('estado_asignacion') === 'PROVISIONAL';

            VehiculoArea::create([
                'id_vehiculo' => $vehiculo->id,
                'id_area' => $request->validated('id_area'),
                'fecha_asignacion' => now(),
                'fecha_culminacion' => $esProvisional ? $request->validated('fecha_culminacion') : null,
                'estado_asignacion' => $request->validated('estado_asignacion'),
                'motivo_asignacion' => $request->validated('motivo_asignacion'),
            ]);
        });

        return redirect()->route('vehiculos.index')
            ->with('success', 'Vehículo asignado al área exitosamente.');
    }

    /**
     * Finaliza manualmente una asignación de área activa/provisional, sin
     * reemplazarla de inmediato (el vehículo queda sin área asignada).
     */
    public function finalizarAsignacionArea(Request $request, Vehiculo $vehiculo, VehiculoArea $asignacion): RedirectResponse
    {
        if (! $request->user()->hasAnyRole(['super-admin', 'administrador', 'jefe-area'])) {
            abort(403, 'Sólo un jefe de área o administrador puede finalizar asignaciones de área.');
        }

        if ((int) $asignacion->id_vehiculo !== $vehiculo->id) {
            abort(404);
        }

        $asignacion->update([
            'estado_asignacion' => 'CULMINADO',
            'fecha_culminacion' => now(),
        ]);

        return redirect()->route('vehiculos.index')
            ->with('success', 'Asignación de área finalizada exitosamente.');
    }
}
