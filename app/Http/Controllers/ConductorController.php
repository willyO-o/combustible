<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignacionRequest;
use App\Http\Requests\ConductorRequest;
use App\Models\Asignacion;
use App\Models\Conductor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ConductorController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Conductor::query()->with('asignacionesActivas')
            ->join('persona', 'persona.id', '=', 'conductor.id'); // Cargar las asignaciones activas para cada conductor

        if ($request->filled('ci')) {
            $query->where('persona.ci', 'like', '%'.$request->ci.'%');
        }
        if ($request->filled('nombres')) {
            $query->where('persona.nombres', 'like', '%'.$request->nombres.'%');
        }
        if ($request->filled('paterno')) {
            $query->where('persona.paterno', 'like', '%'.$request->paterno.'%');
        }
        if ($request->filled('celular')) {
            $query->where('persona.celular', 'like', '%'.$request->celular.'%');
        }

        $conductores = $query
            ->orderBy('persona.id', 'desc')
            ->paginate(10)
            ->withQueryString();

        return inertia('Conductores/Index', [
            'conductores' => $conductores,
            'filters' => $request->only(['ci', 'nombres', 'paterno', 'celular']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Conductores/Create');
    }

    public function store(ConductorRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('conductores', 'public');
        }

        Conductor::create($data);

        return redirect()->route('conductores.index')
            ->with('success', 'Conductor registrado exitosamente.');
    }

    public function show(Conductor $conductor): Response
    {
        $conductor->load([
            'persona',
            'asignacionesActivas.tipoVehiculo',
            'asignacionesActivas.tipoCombustible',
        ]);

        $historialAsignaciones = $conductor->asignaciones()
            ->with(['vehiculo.tipoVehiculo', 'vehiculo.tipoCombustible'])
            ->orderByDesc('fecha_asignacion')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($asignacion) => [
                'id' => $asignacion->id,
                'estado_asignacion' => $asignacion->estado_asignacion,
                'fecha_asignacion' => $asignacion->fecha_asignacion?->format('d/m/Y'),
                'fecha_culminacion' => $asignacion->fecha_culminacion?->format('d/m/Y'),
                'detalle' => $asignacion->detalle,
                'kilometraje_inicial' => $asignacion->kilometraje_inicial,
                'horometro_inicial' => $asignacion->horometro_inicial,
                'vehiculo' => $asignacion->vehiculo ? [
                    'id' => $asignacion->vehiculo->id,
                    'codigo' => $asignacion->vehiculo->codigo,
                    'nro_placa' => $asignacion->vehiculo->nro_placa,
                    'marca' => $asignacion->vehiculo->marca,
                    'modelo' => $asignacion->vehiculo->modelo,
                    'anio' => $asignacion->vehiculo->anio,
                    'url_fotografia' => $asignacion->vehiculo->url_fotografia,
                    'tipo_vehiculo' => $asignacion->vehiculo->tipoVehiculo?->tipo_vehiculo,
                    'tipo_combustible' => $asignacion->vehiculo->tipoCombustible?->tipo_combustible,
                ] : null,
            ]);

        return Inertia::render('Conductores/Show', [
            'conductor' => $conductor,
            'historialAsignaciones' => $historialAsignaciones,
        ]);
    }

    public function edit(Conductor $conductor): Response
    {
        return Inertia::render('Conductores/Edit', [
            'conductor' => $conductor,
        ]);
    }

    public function update(ConductorRequest $request, Conductor $conductor): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            if ($conductor->foto) {
                Storage::disk('public')->delete($conductor->foto);
            }
            $data['foto'] = $request->file('foto')->store('conductores', 'public');
        } else {
            unset($data['foto']);
        }

        $conductor->update($data);

        return redirect()->route('conductores.index')
            ->with('success', 'Conductor actualizado exitosamente.');
    }

    public function destroy(Conductor $conductor): RedirectResponse
    {
        if ($conductor->foto) {
            Storage::disk('public')->delete($conductor->foto);
        }

        $conductor->delete();

        return redirect()->route('conductores.index')
            ->with('success', 'Conductor eliminado exitosamente.');
    }

    /**
     * Asigna (o reasigna) un vehículo a un conductor. La asignación activa
     * anterior de este conductor (si la tiene) queda REASIGNADO con
     * fecha_culminacion=ahora; nunca conviven dos asignaciones activas para
     * el mismo conductor. El usuario que realiza la asignación se registra
     * automáticamente desde el servidor.
     */
    public function asignarVehiculo(AsignacionRequest $request, Conductor $conductor): RedirectResponse
    {
        DB::transaction(function () use ($request, $conductor) {
            Asignacion::where('id_conductor', $conductor->id)
                ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
                ->where(function ($query) {
                    $query->whereNull('fecha_culminacion')
                        ->orWhere('fecha_culminacion', '>', now());
                })
                ->update([
                    'estado_asignacion' => 'REASIGNADO',
                    'fecha_culminacion' => now(),
                ]);

            $esProvisional = $request->validated('estado_asignacion') === 'PROVISIONAL';

            Asignacion::create([
                'id_vehiculo' => $request->validated('id_vehiculo'),
                'id_conductor' => $conductor->id,
                'fecha_asignacion' => now(),
                'fecha_culminacion' => $esProvisional ? $request->validated('fecha_culminacion') : null,
                'estado_asignacion' => $request->validated('estado_asignacion'),
                'detalle' => $request->validated('detalle'),
                'kilometraje_inicial' => $request->validated('kilometraje_inicial'),
                'horometro_inicial' => $request->validated('horometro_inicial'),
                'id_usuario' => auth()->id(),
            ]);
        });

        return redirect()->route('conductores.index')
            ->with('success', 'Vehículo asignado exitosamente.');
    }

    /**
     * Finaliza manualmente una asignación activa/provisional, sin
     * reemplazarla de inmediato (el conductor queda sin vehículo).
     */
    public function finalizarAsignacion(Request $request, Conductor $conductor, Asignacion $asignacion): RedirectResponse
    {
        if (! $request->user()->hasAnyRole(['super-admin', 'administrador', 'jefe-area'])) {
            abort(403, 'Sólo un jefe de área o administrador puede finalizar asignaciones.');
        }

        if ((int) $asignacion->id_conductor !== $conductor->id) {
            abort(404);
        }

        $asignacion->update([
            'estado_asignacion' => 'INACTIVO',
            'fecha_culminacion' => now(),
        ]);

        return redirect()->route('conductores.index')
            ->with('success', 'Asignación finalizada exitosamente.');
    }
}
