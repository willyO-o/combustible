<?php

namespace App\Http\Controllers;

use App\Http\Requests\AreaRequest;
use App\Http\Requests\EncargadoAreaRequest;
use App\Models\Area;
use App\Models\EncargadoArea;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
