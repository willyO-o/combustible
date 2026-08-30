<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoVehiculoRequest;
use App\Models\GrupoVehiculo;
use App\Models\IntervaloMantenimientoTipo;
use App\Models\TipoMantenimiento;
use App\Models\TipoVehiculo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TipoVehiculoController extends Controller
{
    public function index(Request $request): Response
    {
        $query = TipoVehiculo::query();

        if ($request->filled('tipo_vehiculo')) {
            $query->where('tipo_vehiculo', 'like', '%'.$request->tipo_vehiculo.'%');
        }
        if ($request->filled('estado_tipo_vehiculo')) {
            $query->where('estado_tipo_vehiculo', $request->estado_tipo_vehiculo);
        }

        $tipos = $query->orderBy('tipo_vehiculo')->paginate(10)->withQueryString();

        return Inertia::render('TiposVehiculo/Index', [
            // Inertia::scroll() no cambia la forma del paginador (sigue
            // trayendo data/total/from/to/links tal cual): sólo agrega la
            // metadata de merge que usa <InfiniteScroll> en el listado de
            // tarjetas (mobile). Ver .ai/rules/pages.md.
            'tipos' => Inertia::scroll($tipos),
            'filters' => $request->only(['tipo_vehiculo', 'estado_tipo_vehiculo']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('TiposVehiculo/Create', [
            'tipo' => null,
            'tiposMantenimiento' => $this->tiposMantenimientoActivos(),
            'gruposVehiculo' => $this->gruposVehiculoActivos(),
        ]);
    }

    public function store(TipoVehiculoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $intervalos = $data['intervalos'] ?? [];
        unset($data['intervalos']);

        DB::transaction(function () use ($data, $intervalos) {
            $tipoVehiculo = TipoVehiculo::create($data);
            $this->sincronizarIntervalos($tipoVehiculo, $intervalos);
        });

        return redirect()->route('tipos-vehiculo.index')
            ->with('success', 'Tipo de vehículo registrado exitosamente.');
    }

    public function edit(TipoVehiculo $tipoVehiculo): Response
    {
        return Inertia::render('TiposVehiculo/Create', [
            'tipo' => $tipoVehiculo->load('intervalos.tipoMantenimiento'),
            'tiposMantenimiento' => $this->tiposMantenimientoActivos(),
            'gruposVehiculo' => $this->gruposVehiculoActivos($tipoVehiculo->id_grupo_vehiculo),
        ]);
    }

    public function update(TipoVehiculoRequest $request, TipoVehiculo $tipoVehiculo): RedirectResponse
    {
        $data = $request->validated();
        $intervalos = $data['intervalos'] ?? [];
        unset($data['intervalos']);

        DB::transaction(function () use ($tipoVehiculo, $data, $intervalos) {
            $tipoVehiculo->update($data);
            $this->sincronizarIntervalos($tipoVehiculo, $intervalos);
        });

        return redirect()->route('tipos-vehiculo.index')
            ->with('success', 'Tipo de vehículo actualizado exitosamente.');
    }

    public function destroy(TipoVehiculo $tipoVehiculo): RedirectResponse
    {
        if ($tipoVehiculo->vehiculos()->count() > 0) {
            return redirect()->route('tipos-vehiculo.index')
                ->with('error', 'No se puede eliminar: existen vehículos asignados a este tipo.');
        }

        $tipoVehiculo->intervalos()->delete();
        $tipoVehiculo->delete();

        return redirect()->route('tipos-vehiculo.index')
            ->with('success', 'Tipo de vehículo eliminado exitosamente.');
    }

    /**
     * Catálogo de tipos de mantenimiento activos para el selector de
     * intervalos (lista pequeña, se filtra en el cliente). Sólo ámbito
     * taller: los intervalos de mantenimiento por tipo de vehículo son
     * mantenimiento de taller, no de operación diaria.
     */
    private function tiposMantenimientoActivos(): Collection
    {
        return TipoMantenimiento::where('estado_tipo_mantenimiento', 'ACTIVO')
            ->where('ambito', 'taller')
            ->orderBy('tipo_mantenimiento')
            ->get(['id', 'tipo_mantenimiento']);
    }

    /**
     * Catálogo de grupos de vehículo activos para el selector obligatorio
     * del formulario de tipo de vehículo. Si el tipo que se está editando
     * tiene asignado un grupo que ya no está activo, igual se incluye para
     * no perder la selección actual en el formulario.
     */
    private function gruposVehiculoActivos(?int $idGrupoActual = null): Collection
    {
        return GrupoVehiculo::where('estado_grupo_vehiculo', 'ACTIVO')
            ->when($idGrupoActual, fn ($query) => $query->orWhere('id', $idGrupoActual))
            ->orderBy('grupo_vehiculo')
            ->get(['id', 'grupo_vehiculo']);
    }

    /**
     * Reemplaza los intervalos de mantenimiento del tipo de vehículo por
     * los enviados en el formulario (a lo sumo uno por tipo de mantenimiento,
     * validado en TipoVehiculoRequest).
     *
     * @param  array<int, array{id_tipo_mantenimiento: int, tipo_medicion: string, frecuencia: int}>  $intervalos
     */
    private function sincronizarIntervalos(TipoVehiculo $tipoVehiculo, array $intervalos): void
    {
        $tipoVehiculo->intervalos()->delete();

        foreach ($intervalos as $intervalo) {
            IntervaloMantenimientoTipo::create([
                'id_tipo_vehiculo' => $tipoVehiculo->id,
                'id_tipo_mantenimiento' => $intervalo['id_tipo_mantenimiento'],
                'tipo_medicion' => $intervalo['tipo_medicion'],
                'frecuencia' => $intervalo['frecuencia'],
                'estado' => 'ACTIVO',
            ]);
        }
    }
}
