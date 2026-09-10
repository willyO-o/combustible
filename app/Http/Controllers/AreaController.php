<?php

namespace App\Http\Controllers;

use App\Http\Requests\AreaRequest;
use App\Http\Requests\EncargadoAreaRequest;
use App\Http\Requests\VehiculoAreaRequest;
use App\Models\Area;
use App\Models\EncargadoArea;
use App\Models\Persona;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function index(Request $request): Response
    {
        $areas = Area::withCount(['encargados', 'vehiculos'])
            ->with('encargadosActivos')
            ->orderBy('nombre_area')
            ->paginate(10)
            ->withQueryString();

        $areas->through(fn (Area $area) => [
            'id' => $area->id,
            'nombre_area' => $area->nombre_area,
            'descripcion_area' => $area->descripcion_area,
            'estado_area' => $area->estado_area,
            'created_at' => $area->created_at,
            'encargados_count' => $area->encargados_count,
            'vehiculos_count' => $area->vehiculos_count,
            'encargados_activos' => $area->encargadosActivos->map(fn (Persona $persona) => [
                'id_encargo' => $persona->pivot->id,
                'id_persona' => $persona->id,
                'nombre_completo' => $persona->nombre_completo,
                'ci' => $persona->ci,
                'tipo_encargo' => $persona->pivot->tipo_encargo,
                'fecha_inicio' => optional($persona->pivot->fecha_inicio)->format('d/m/Y'),
                'fecha_fin' => optional($persona->pivot->fecha_fin)->format('d/m/Y'),
            ])->values(),
        ]);

        return Inertia::render('Areas/Index', [
            // Inertia::scroll() no cambia la forma del paginador (sigue
            // trayendo data/total/from/to/links tal cual): sólo agrega la
            // metadata de merge que usa <InfiniteScroll> en el listado de
            // tarjetas (mobile). Ver .ai/rules/pages.md.
            'areas' => Inertia::scroll($areas),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Ficha de un área: sus datos, los encargados (vigentes e histórico) y
     * TODOS los vehículos que tiene o tuvo asignados. La tabla principal
     * ("Vehículos Asignados") sólo lista las asignaciones vigentes
     * (estado ACTIVO/PROVISIONAL y sin culminar, mismo criterio que
     * Area::vehiculosActivos()); el histórico completo va aparte.
     */
    public function show(Area $area): Response
    {
        $fmt = fn ($fecha): ?string => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : null;

        // Encargados: primero los vigentes (titular antes que suplente), luego
        // los encargos ya finalizados, más recientes primero.
        $encargados = $area->encargados()
            ->orderByRaw("CASE WHEN encargado_area.estado_encargo = 'ACTIVO' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE encargado_area.tipo_encargo WHEN 'TITULAR' THEN 0 ELSE 1 END")
            ->orderByDesc('encargado_area.fecha_inicio')
            ->get()
            ->map(fn (Persona $persona): array => [
                'id' => $persona->pivot->id,
                'nombre_completo' => $persona->nombre_completo,
                'ci' => $persona->ci,
                'celular' => $persona->celular,
                'foto_url' => $persona->foto_url,
                'tipo_encargo' => $persona->pivot->tipo_encargo,
                'estado_encargo' => $persona->pivot->estado_encargo,
                'fecha_inicio' => $fmt($persona->pivot->fecha_inicio),
                'fecha_fin' => $fmt($persona->pivot->fecha_fin),
                'fecha_reasignacion' => $fmt($persona->pivot->fecha_reasignacion),
                'motivo' => $persona->pivot->motivo,
                'vigente' => $persona->pivot->estado_encargo === 'ACTIVO'
                    && ($persona->pivot->fecha_fin === null
                        || Carbon::parse($persona->pivot->fecha_fin)->greaterThanOrEqualTo(today())),
            ]);

        // Vehículos con asignación vigente al área, con su conductor actual.
        $vehiculos = $area->vehiculosActivos()
            ->with([
                'tipoVehiculo:id,tipo_vehiculo',
                'tipoCombustible:id,tipo_combustible',
                'conductorAsignado.persona',
            ])
            ->get()
            ->map(fn (Vehiculo $vehiculo): array => [
                'id' => $vehiculo->id,
                'codigo' => $vehiculo->codigo,
                'nro_placa' => $vehiculo->nro_placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'anio' => $vehiculo->anio,
                'estado_vehiculo' => $vehiculo->estado_vehiculo,
                'tipo_medicion' => $vehiculo->tipo_medicion,
                'url_fotografia' => $vehiculo->url_fotografia,
                'tipo_vehiculo' => $vehiculo->tipoVehiculo?->tipo_vehiculo,
                'tipo_combustible' => $vehiculo->tipoCombustible?->tipo_combustible,
                'conductor' => $vehiculo->conductorAsignado?->persona ? [
                    'nombre_completo' => $vehiculo->conductorAsignado->persona->nombre_completo,
                    'ci' => $vehiculo->conductorAsignado->persona->ci,
                    'celular' => $vehiculo->conductorAsignado->persona->celular,
                ] : null,
                'id_asignacion' => $vehiculo->pivot->id,
                'estado_asignacion' => $vehiculo->pivot->estado_asignacion,
                'fecha_asignacion' => $fmt($vehiculo->pivot->fecha_asignacion),
                'fecha_culminacion' => $fmt($vehiculo->pivot->fecha_culminacion),
                'motivo_asignacion' => $vehiculo->pivot->motivo_asignacion,
            ]);

        // Histórico completo de asignaciones de vehículos al área (incluye
        // REASIGNADO/CULMINADO). Se resuelve con query builder para traer una
        // fila por asignación (belongsToMany deduplicaría por vehículo).
        $historialVehiculos = DB::table('vehiculo_area as va')
            ->join('vehiculo as v', 'v.id', '=', 'va.id_vehiculo')
            ->where('va.id_area', $area->id)
            ->orderByDesc('va.fecha_asignacion')
            ->orderByDesc('va.id')
            ->get([
                'va.id',
                'va.id_vehiculo',
                'va.estado_asignacion',
                'va.fecha_asignacion',
                'va.fecha_reasignacion',
                'va.fecha_culminacion',
                'va.motivo_asignacion',
                'v.codigo',
                'v.nro_placa',
                'v.marca',
                'v.modelo',
            ])
            ->map(fn ($fila): array => [
                'id' => $fila->id,
                'id_vehiculo' => $fila->id_vehiculo,
                'codigo' => $fila->codigo,
                'nro_placa' => $fila->nro_placa,
                'marca' => $fila->marca,
                'modelo' => $fila->modelo,
                'estado_asignacion' => $fila->estado_asignacion,
                'fecha_asignacion' => $fmt($fila->fecha_asignacion),
                'fecha_reasignacion' => $fmt($fila->fecha_reasignacion),
                'fecha_culminacion' => $fmt($fila->fecha_culminacion),
                'motivo_asignacion' => $fila->motivo_asignacion,
            ]);

        return Inertia::render('Areas/Show', [
            'area' => [
                'id' => $area->id,
                'nombre_area' => $area->nombre_area,
                'descripcion_area' => $area->descripcion_area,
                'estado_area' => $area->estado_area,
                'created_at' => $fmt($area->created_at),
            ],
            'resumen' => [
                'vehiculos_vigentes' => $vehiculos->count(),
                'vehiculos_historico' => $historialVehiculos->count(),
                'encargados_vigentes' => $encargados->where('vigente', true)->count(),
            ],
            'encargados' => $encargados,
            'vehiculos' => $vehiculos,
            'historialVehiculos' => $historialVehiculos,
            // Para el selector del modal "Cambiar asignación".
            'areasDisponibles' => Area::where('estado_area', 'ACTIVO')
                ->orderBy('nombre_area')
                ->get(['id', 'nombre_area']),
        ]);
    }

    /**
     * Reasigna un vehículo a un área desde la ficha del área. Misma lógica
     * que VehiculoController::asignarArea() (cierra como REASIGNADA la
     * asignación vigente del vehículo —sin importar el área— y crea la
     * nueva), pero es una acción separada porque aquélla redirige a
     * vehiculos.index y sacaría al usuario de la ficha del área (mismo
     * criterio que VehiculoController::finalizarAsignacionConductor()).
     */
    public function reasignarVehiculo(VehiculoAreaRequest $request, Area $area, Vehiculo $vehiculo): RedirectResponse
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

        return redirect()->route('areas.show', $area)
            ->with('success', 'Asignación de vehículo actualizada exitosamente.');
    }

    /**
     * Finaliza una asignación de vehículo al área sin reemplazarla (el
     * vehículo queda sin área). Igual que
     * VehiculoController::finalizarAsignacionArea() pero redirige a la ficha
     * del área.
     */
    public function finalizarAsignacionVehiculo(Request $request, Area $area, VehiculoArea $asignacion): RedirectResponse
    {
        if (! $request->user()->hasAnyRole(['super-admin', 'administrador', 'jefe-area'])) {
            abort(403, 'Sólo un jefe de área o administrador puede finalizar asignaciones de vehículos.');
        }

        if ((int) $asignacion->id_area !== $area->id) {
            abort(404);
        }

        $asignacion->update([
            'estado_asignacion' => 'CULMINADO',
            'fecha_culminacion' => now(),
        ]);

        return redirect()->route('areas.show', $area)
            ->with('success', 'Asignación de vehículo finalizada exitosamente.');
    }

    public function store(AreaRequest $request): RedirectResponse
    {
        Area::create($request->validated());

        return redirect()->route('areas.index')
            ->with('success', 'Área registrada exitosamente.');
    }

    public function update(AreaRequest $request, Area $area): RedirectResponse
    {
        $area->update($request->validated());

        return redirect()->route('areas.index')
            ->with('success', 'Área actualizada exitosamente.');
    }

    public function destroy(Area $area): RedirectResponse
    {
        if ($area->encargados()->count() > 0 || $area->vehiculos()->count() > 0) {
            return redirect()->route('areas.index')
                ->with('error', 'No se puede eliminar: el área tiene encargados o vehículos asignados.');
        }

        $area->delete();

        return redirect()->route('areas.index')
            ->with('success', 'Área eliminada exitosamente.');
    }

    /**
     * Asigna un encargado (titular o suplente) a un área. La asignación
     * anterior del mismo tipo de encargo para esta área (si sigue activa)
     * se inactiva automáticamente. Además sincroniza la cuenta de usuario
     * de la persona (la crea si no existe) y le asigna el rol jefe-area
     * como único rol.
     */
    public function asignarEncargado(EncargadoAreaRequest $request, Area $area): RedirectResponse
    {
        DB::transaction(function () use ($request, $area) {
            $persona = Persona::findOrFail($request->validated('id_persona'));
            $tipoEncargo = $request->validated('tipo_encargo');

            EncargadoArea::where('id_area', $area->id)
                ->where('tipo_encargo', $tipoEncargo)
                ->where('estado_encargo', 'ACTIVO')
                ->update([
                    'estado_encargo' => 'INACTIVO',
                    'fecha_reasignacion' => now(),
                ]);

            EncargadoArea::create([
                'id_persona' => $persona->id,
                'id_area' => $area->id,
                'tipo_encargo' => $tipoEncargo,
                'fecha_inicio' => now(),
                'fecha_fin' => $request->validated('fecha_fin'),
                'motivo' => $request->validated('motivo'),
                'estado_encargo' => 'ACTIVO',
            ]);

            $this->sincronizarUsuarioJefeArea($persona, $request->validated('email'));
        });

        return redirect()->route('areas.index')
            ->with('success', 'Encargado asignado exitosamente.');
    }

    /**
     * Finaliza manualmente un encargo activo, sin reemplazarlo de inmediato
     * (el puesto queda libre hasta la próxima asignación).
     */
    public function finalizarEncargado(Request $request, Area $area, EncargadoArea $encargado): RedirectResponse
    {
        if (! $request->user()->hasAnyRole(['super-admin', 'administrador'])) {
            abort(403, 'Sólo un administrador puede finalizar encargos de área.');
        }

        if ((int) $encargado->id_area !== $area->id) {
            abort(404);
        }

        $encargado->update([
            'estado_encargo' => 'INACTIVO',
            'fecha_reasignacion' => now(),
        ]);

        return redirect()->route('areas.index')
            ->with('success', 'Encargo finalizado exitosamente.');
    }

    /**
     * Búsqueda de personas activas candidatas a encargado de área, para el
     * selector del modal de asignación. Incluye si ya tienen una cuenta de
     * usuario y si actualmente están a cargo de otra área, para que el
     * administrador decida con esa información a la vista.
     */
    public function searchPersonasParaEncargado(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $personas = Persona::where('estado_persona', 'ACTIVO')
            ->where(function ($query) use ($q) {
                $query->where('ci', 'like', "%{$q}%")
                    ->orWhere('nombres', 'like', "%{$q}%")
                    ->orWhere('paterno', 'like', "%{$q}%");
            })
            ->with(['user', 'encargadoAreas'])
            ->limit(20)
            ->get()
            ->map(function (Persona $persona) {
                $encargoActual = $persona->encargadoAreas->first();
                $notaEncargo = $encargoActual
                    ? " (Actualmente {$encargoActual->pivot->tipo_encargo} de {$encargoActual->nombre_area})"
                    : '';

                return [
                    'id' => $persona->id,
                    'label' => "{$persona->ci} — {$persona->nombre_completo}{$notaEncargo}",
                    'nombre_completo' => $persona->nombre_completo,
                    'ci' => $persona->ci,
                    'tiene_usuario' => (bool) $persona->user,
                    'email_actual' => $persona->user?->email,
                ];
            });

        return response()->json($personas);
    }

    private function sincronizarUsuarioJefeArea(Persona $persona, ?string $email): void
    {
        $usuario = $persona->user;

        if (! $usuario) {
            $usuario = User::create([
                'name' => trim("{$persona->nombres} {$persona->paterno}"),
                'email' => $email,
                'password' => Hash::make($persona->ci.'#Plusmetals'),
                'id_persona' => $persona->id,
                'estado_usuario' => 'ACTIVO',
            ]);
        }

        $usuario->syncRoles(['jefe-area']);
    }
}
