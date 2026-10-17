<?php

namespace App\Http\Controllers;

use App\Actions\Personas\CreatePersonaAction;
use App\Actions\Personas\UpdatePersonaAction;
use App\Actions\Vehiculo\ListVehiculosSeleccionablesAction;
use App\Http\Requests\PersonaRequest;
use App\Libraries\Reportes;
use App\Libraries\ReportesExcel;
use App\Models\Area;
use App\Models\Cargo;
use App\Models\Persona;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class PersonaController extends Controller
{
    /**
     * Filtros del listado; el PDF y el Excel reciben exactamente los mismos.
     */
    private const FILTROS_LISTADO = ['ci', 'nombres', 'paterno', 'celular', 'id_cargo'];

    /**
     * Valor del filtro de cargo para "personas sin cargo asignado".
     */
    private const SIN_CARGO = 'ninguno';

    public function index(Request $request): Response
    {
        $personas = $this->consultaListado($request)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Personas/Index', [
            // Inertia::scroll() no cambia la forma del paginador (sigue
            // trayendo data/total/from/to/links tal cual): sólo agrega la
            // metadata de merge que usa <InfiniteScroll> en el listado de
            // tarjetas (mobile). Ver .ai/rules/pages.md.
            'personas' => Inertia::scroll($personas),
            'filters' => $request->only(self::FILTROS_LISTADO),
            // Todos (también los inactivos): sirve para filtrar personas que
            // aún tienen un cargo ya desactivado.
            'cargos' => Cargo::orderBy('cargo')->get(['id', 'cargo', 'estado_cargo']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Listado de personas en PDF, con los mismos filtros y orden que la
     * vista (todas las páginas, no sólo la visible).
     */
    public function reportePdf(Request $request): HttpResponse|RedirectResponse
    {
        try {
            $contenido = (new Reportes)->generarReportePersonas(
                $this->listadoParaReporte($request),
                $this->filtrosAplicados($request),
                'S',
            );

            return response($contenido, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="listado_personas_'.now()->format('Y-m-d').'.pdf"',
            ]);
        } catch (\Throwable $e) {
            return $this->errorAlGenerarReporte($request, $e, 'PDF');
        }
    }

    /**
     * Mismo listado que reportePdf(), en Excel (.xlsx).
     */
    public function reporteExcel(Request $request): HttpResponse|RedirectResponse
    {
        try {
            $contenido = (new ReportesExcel)->generarReportePersonas(
                $this->listadoParaReporte($request),
                $this->filtrosAplicados($request),
            );

            return response($contenido, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="listado_personas_'.now()->format('Y-m-d').'.xlsx"',
            ]);
        } catch (\Throwable $e) {
            return $this->errorAlGenerarReporte($request, $e, 'Excel');
        }
    }

    /**
     * Si un reporte falla (dato inesperado, falta de memoria, etc.) se
     * registra el error y se vuelve al listado con los mismos filtros y un
     * aviso, en vez de dejar al usuario frente a una pantalla de error 500.
     */
    private function errorAlGenerarReporte(Request $request, \Throwable $e, string $formato): RedirectResponse
    {
        report($e);

        return redirect()
            ->route('personas.index', $request->only(self::FILTROS_LISTADO))
            ->with('error', "No se pudo generar el reporte en {$formato}. Intente nuevamente o contacte al administrador.");
    }

    /**
     * Consulta del listado con los filtros del request, compartida por la
     * vista (paginada) y por los reportes PDF/Excel (completa).
     */
    private function consultaListado(Request $request): Builder
    {
        $query = Persona::with(['user', 'conductor', 'encargadoAreas', 'cargo:id,cargo']);

        if ($request->filled('ci')) {
            $query->where('ci', 'like', '%'.$request->ci.'%');
        }
        if ($request->filled('nombres')) {
            $query->where('nombres', 'like', '%'.$request->nombres.'%');
        }
        if ($request->filled('paterno')) {
            $query->where('paterno', 'like', '%'.$request->paterno.'%');
        }
        if ($request->filled('celular')) {
            $query->where('celular', 'like', '%'.$request->celular.'%');
        }
        if ($request->filled('id_cargo')) {
            $request->input('id_cargo') === self::SIN_CARGO
                ? $query->whereNull('id_cargo')
                : $query->where('id_cargo', (int) $request->input('id_cargo'));
        }

        return $query->orderBy('id', 'desc');
    }

    /**
     * Filas ya resueltas (sin modelos) que reciben Reportes y ReportesExcel,
     * más los conteos de las tarjetas de resumen. El tipo se deriva de las
     * relaciones ya cargadas con el mismo criterio que
     * Persona::getTipoActualAttribute(), sin su consulta extra por fila.
     *
     * @return array{personas: Collection<int, array<string, mixed>>, totales: array{total: int, activos: int, inactivos: int, retirados: int, con_usuario: int}}
     */
    private function listadoParaReporte(Request $request): array
    {
        $personas = $this->consultaListado($request)
            ->get()
            ->map(fn (Persona $persona) => [
                'ci' => $persona->ci,
                'nombres' => $persona->nombres,
                'paterno' => $persona->paterno,
                'materno' => $persona->materno,
                'nombre_completo' => trim(implode(' ', array_filter([$persona->nombres, $persona->paterno, $persona->materno]))) ?: null,
                'celular' => $persona->celular,
                'cargo' => $persona->cargo?->cargo,
                'fecha_nacimiento' => $persona->fecha_nacimiento?->format('d/m/Y'),
                'edad' => $persona->fecha_nacimiento?->age,
                'tipo' => match (true) {
                    $persona->conductor !== null => 'Conductor',
                    $persona->encargadoAreas->isNotEmpty() => 'Jefe de Área',
                    $persona->user !== null => 'Personal',
                    // Sin relaciones ligadas: celda en blanco (tipo_actual también es null).
                    default => null,
                },
                'areas_a_cargo' => $persona->encargadoAreas->pluck('nombre_area')->filter()->implode(', ') ?: null,
                'usuario' => $persona->user?->email,
                'estado_usuario' => $persona->user?->estado_usuario,
                'estado_persona' => $persona->estado_persona,
            ]);

        return [
            'personas' => $personas,
            'totales' => [
                'total' => $personas->count(),
                'activos' => $personas->where('estado_persona', 'ACTIVO')->count(),
                'inactivos' => $personas->where('estado_persona', 'INACTIVO')->count(),
                'retirados' => $personas->where('estado_persona', 'RETIRADO')->count(),
                'con_usuario' => $personas->whereNotNull('usuario')->count(),
            ],
        ];
    }

    /**
     * Filtros activos con etiqueta legible, para dejar constancia en el
     * encabezado del reporte.
     *
     * @return array<string, ?string>
     */
    private function filtrosAplicados(Request $request): array
    {
        return [
            'CI' => $request->filled('ci') ? (string) $request->ci : null,
            'Nombres' => $request->filled('nombres') ? (string) $request->nombres : null,
            'Apellido paterno' => $request->filled('paterno') ? (string) $request->paterno : null,
            'Celular' => $request->filled('celular') ? (string) $request->celular : null,
            'Cargo' => match (true) {
                ! $request->filled('id_cargo') => null,
                $request->input('id_cargo') === self::SIN_CARGO => 'Sin cargo asignado',
                default => Cargo::whereKey((int) $request->input('id_cargo'))->value('cargo'),
            },
        ];
    }

    /**
     * Verifica si el CI ya pertenece a otra persona, para avisarlo en el
     * formulario apenas se escribe (CiInput.vue). Incluye personas
     * eliminadas: el CI es único en la base aun con borrado lógico, así que
     * también bloquean un nuevo registro. `ignorar` = id de la persona que se
     * está editando. La validación definitiva sigue en PersonaRequest /
     * ConductorRequest (unique) al guardar.
     */
    public function verificarCi(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'ci' => ['required', 'string', 'max:20'],
            'ignorar' => ['nullable', 'integer'],
        ]);

        $persona = Persona::withTrashed()
            ->with(['conductor', 'user', 'encargadoAreas'])
            ->where('ci', trim($datos['ci']))
            ->when($datos['ignorar'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))
            ->first();

        if (! $persona) {
            return response()->json(['existe' => false]);
        }

        $eliminada = $persona->trashed();

        return response()->json([
            'existe' => true,
            'persona' => [
                'id' => $persona->id,
                'ci' => $persona->ci,
                'nombre_completo' => trim(implode(' ', array_filter([$persona->nombres, $persona->paterno, $persona->materno]))),
                'tipo' => match (true) {
                    $persona->conductor !== null => 'Conductor',
                    $persona->encargadoAreas->isNotEmpty() => 'Jefe de Área',
                    $persona->user !== null => 'Personal',
                    default => null,
                },
                'estado_persona' => $persona->estado_persona,
                'eliminada' => $eliminada,
                // Conductores: su ficha es accesible para más roles que Personas.
                'url' => $eliminada ? null : ($persona->conductor
                    ? route('conductores.show', $persona->id)
                    : route('personas.edit', $persona->id)),
            ],
        ]);
    }

    public function create(Request $request, ListVehiculosSeleccionablesAction $listVehiculos): Response
    {
        return Inertia::render('Personas/Create', [
            'areas' => Area::where('estado_area', 'ACTIVO')->orderBy('nombre_area')->get(['id', 'nombre_area']),
            // Vehículos precargados para la asignación (rol conductor).
            'vehiculos' => $listVehiculos->execute($request->user())->all(),
            'roles' => $this->rolesAsignables(),
            'cargos' => Cargo::seleccionables(),
        ]);
    }

    public function store(PersonaRequest $request, CreatePersonaAction $action): RedirectResponse
    {
        if (! $request->user()->hasAnyRole('super-admin', 'administrador')) {
            abort(403);
        }

        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('personas', 'public');
        }

        $action->execute($data);

        return redirect()->route('personas.index')
            ->with('success', 'Persona registrada exitosamente.');
    }

    public function edit(Request $request, Persona $persona, ListVehiculosSeleccionablesAction $listVehiculos): Response
    {
        $persona->load(['user', 'conductor.asignacionesActivas', 'encargadoAreas']);
        $vehiculoActual = $persona->conductor?->asignacionesActivas->first();

        return Inertia::render('Personas/Edit', [
            'persona' => $persona,
            'areas' => Area::where('estado_area', 'ACTIVO')->orderBy('nombre_area')->get(['id', 'nombre_area']),
            'vehiculoActual' => $vehiculoActual,
            // Vehículos precargados; el actual se incluye aunque ya no esté activo.
            'vehiculos' => $listVehiculos->execute($request->user(), $vehiculoActual?->id)->all(),
            'areaActual' => $persona->encargadoAreas->first(),
            'roles' => $this->rolesAsignables(),
            'rolesAsignados' => $persona->rolesParaFormulario(),
            'cargos' => Cargo::seleccionables($persona->id_cargo),
        ]);
    }

    /**
     * Roles que pueden asignarse desde el formulario: los mismos que en el
     * módulo de Usuarios (todos salvo los ocultos, ver config/acl.php).
     */
    private function rolesAsignables(): Collection
    {
        return Role::whereNotIn('name', config('acl.roles_ocultos'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function update(PersonaRequest $request, Persona $persona, UpdatePersonaAction $action): RedirectResponse
    {
        if (! $request->user()->hasAnyRole('super-admin', 'administrador')) {
            abort(403);
        }

        $data = $request->validated();

        if ($request->hasFile('foto')) {
            if ($persona->foto) {
                Storage::disk('public')->delete($persona->foto);
            }
            $data['foto'] = $request->file('foto')->store('personas', 'public');
        } else {
            unset($data['foto']);
        }

        $action->execute($persona, $data);

        return redirect()->route('personas.index')
            ->with('success', 'Persona actualizada exitosamente.');
    }

    public function destroy(Request $request, Persona $persona): RedirectResponse
    {
        if (! $request->user()->hasAnyRole('super-admin', 'administrador')) {
            abort(403);
        }

        if ($persona->foto) {
            Storage::disk('public')->delete($persona->foto);
        }

        $persona->delete();

        return redirect()->route('personas.index')
            ->with('success', 'Persona eliminada exitosamente.');
    }
}
