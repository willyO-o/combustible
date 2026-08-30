<?php

namespace App\Http\Controllers;

use App\Actions\OperacionDiaria\CreateOperacionDiariaAction;
use App\Actions\OperacionDiaria\ListOperacionesDiariasAction;
use App\Actions\OperacionDiaria\UpdateOperacionDiariaAction;
use App\Http\Requests\OperacionStoreRequest;
use App\Libraries\Reportes;
use App\Models\Actividad;
use App\Models\Area;
use App\Models\Asignacion;
use App\Models\Material;
use App\Models\OperacionDiaria;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OperacionDiariaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(
        Request $request,
        ListOperacionesDiariasAction $listAction
    ) {

        $filters = $request->only(['nro_placa', 'id_conductor', 'estado_operacion']);
        // Por defecto "Este mes" (1º del mes actual -> hoy), igual que el preset
        // seleccionado por defecto en DateRangeFilter.vue: así la primera carga
        // de la página ya llega filtrada del servidor y se evita la doble
        // petición que causaba el propio componente al autoseleccionar el
        // rango en el cliente después del primer render.
        $filters['fecha_desde'] = $request->input('fecha_desde', now()->startOfMonth()->format('Y-m-d'));
        $filters['fecha_hasta'] = $request->input('fecha_hasta', now()->format('Y-m-d'));

        $actividades = $listAction->execute($filters, $request->user());

        $conductores = [];

        $areas = [];

        if ($request->user()->hasRole('conductor')) {
            $areas = false;
        }

        if ($request->user()->hasRole('jefe-area')) {
            $areas = $request->user()->persona->encargadoAreas()->pluck('id_area')->toArray();
        }

        $conductores = Area::conductores($areas)?->map(function ($conductor) {
            return [
                'id' => $conductor->id,
                'label' => "{$conductor->persona->nombre_completo} (CI: {$conductor->persona->ci})",
            ];
        })->toArray();

        return inertia('Operacion/Index', [
            // Inertia::scroll() no cambia la forma del paginador (sigue
            // trayendo data/total/from/to/links tal cual): sólo agrega la
            // metadata de merge que usa <InfiniteScroll> en el listado de
            // tarjetas (mobile). Ver .ai/rules/pages.md.
            'actividades' => Inertia::scroll($actividades),
            'filters' => $filters,
            'conductores' => $conductores,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * Antes sólo el rol conductor podía registrar operaciones diarias; ahora
     * cualquier rol puede hacerlo, pero el listado de vehículos para elegir
     * depende del rol (ver vehiculosDisponibles()).
     */
    public function create()
    {
        $user = request()->user();

        return inertia('Operacion/Create', [
            'vehiculosAsignados' => $this->vehiculosDisponibles($user),
            'operacion' => null,
            'actividadesSugeridas' => $this->actividadesSugeridas($user),
            'materiales' => $this->materiales(),
            'tiposMantenimiento' => $this->tiposMantenimientoOperacionDiaria(),
            'mostrarSelectorConductor' => ! $user->hasRole('conductor'),
        ]);
    }

    /**
     * Catálogo de materiales para el selector de material trasladado de cada
     * actividad (sólo se usa en vehículos con medición por kilometraje).
     */
    private function materiales()
    {
        return Material::orderBy('material')->get(['id', 'material']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(OperacionStoreRequest $request, CreateOperacionDiariaAction $action)
    {

        try {
            $operacionDiaria = $action->execute($this->datosConConductorResuelto($request));

            return redirect()->route('operacion-diaria.index')->with('success', 'Operación diaria creada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al crear la operación diaria: '.$e->getMessage());
        }
    }

    /**
     * El combo de conductor sólo se muestra en el formulario a quien no
     * tiene el rol conductor (un conductor siempre opera su propio vehículo
     * asignado, sin ambigüedad, así que no elige nada): se completa aquí con
     * su propio id de conductor antes de pasarlo a la Action.
     */
    private function datosConConductorResuelto(OperacionStoreRequest $request): array
    {
        $datos = $request->validated();
        $user = $request->user();

        if ($user->hasRole('conductor') && $user->persona?->conductor) {
            $datos['id_conductor'] = $user->persona->conductor->id;
        }

        return $datos;
    }

    /**
     * Display the specified resource.
     */
    public function show(OperacionDiaria $operacionDiaria)
    {

        $operacion = $operacionDiaria->load(['conductor.persona', 'vehiculo', 'area', 'verificador', 'actividadesRealizadas'])
            ->cargarMaterialDeActividades();

        return inertia('Operacion/Show', [
            'operacion' => $operacion,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OperacionDiaria $operacionDiaria)
    {
        $user = request()->user();

        $operacionDiaria->actividades_realizadas_edit = $operacionDiaria->actividadesRealizadasEdit();
        $operacionDiaria->mantenimientos_edit = $operacionDiaria->mantenimientosOperacionEdit();

        return inertia('Operacion/Create', [
            // Se asegura que el vehículo ya asignado a la operación aparezca en
            // el listado aunque ya no cumpla el filtro por rol (p.ej. fue
            // reasignado de área/conductor después de crear la operación).
            'vehiculosAsignados' => $this->vehiculosDisponibles($user, $operacionDiaria->vehiculo),
            'operacion' => $operacionDiaria->load(['vehiculo', 'area']),
            'actividadesSugeridas' => $this->actividadesSugeridas($user),
            'materiales' => $this->materiales(),
            'tiposMantenimiento' => $this->tiposMantenimientoOperacionDiaria(),
            'mostrarSelectorConductor' => ! $user->hasRole('conductor'),
        ]);
    }

    /**
     * Catálogo de controles de mantenimiento de ámbito operacion_diaria
     * (activos) para la sección de mantenimiento del formulario. Los tipos
     * de ámbito taller no se ofrecen aquí (se usan en órdenes de trabajo).
     */
    private function tiposMantenimientoOperacionDiaria()
    {
        return TipoMantenimiento::query()
            ->where('ambito', 'operacion_diaria')
            ->where('estado_tipo_mantenimiento', 'ACTIVO')
            ->orderBy('tipo_mantenimiento')
            ->get(['id', 'tipo_mantenimiento', 'tipo_valor', 'unidad_medida']);
    }

    /**
     * Vehículos que el usuario puede elegir al registrar/editar una operación
     * diaria, según su rol:
     * - conductor: sólo sus propios vehículos asignados (Conductor::asignacionesActivas,
     *   asignación ACTIVO/PROVISIONAL con fecha_culminacion nula o futura).
     * - jefe-area: sólo los vehículos de las áreas que tiene a cargo
     *   (Persona::encargadoAreas + vehiculo_area con asignación ACTIVO/PROVISIONAL
     *   y fecha_culminacion nula o futura).
     * - un usuario con ambos roles ve la unión de los dos listados, sin duplicar.
     * - cualquier otro rol (administrador, super-admin, técnico de mantenimiento,
     *   etc.): sin filtro, todos los vehículos activos.
     *
     * $incluirSiFalta permite garantizar que el vehículo ya asignado a una
     * operación existente (edit()) aparezca en el listado aunque ya no
     * cumpla el filtro del rol actual.
     */
    private function vehiculosDisponibles(User $user, ?Vehiculo $incluirSiFalta = null): array
    {
        $persona = $user->persona;
        $esConductor = $user->hasRole('conductor') && $persona?->conductor;
        $esJefeArea = $user->hasRole('jefe-area') && $persona;

        if (! $esConductor && ! $esJefeArea) {
            $vehiculos = Vehiculo::select($this->columnasVehiculoOpt())
                ->where('estado_vehiculo', 'ACTIVO')
                ->get();
        } else {
            $vehiculos = collect();

            if ($esConductor) {
                $vehiculos = $vehiculos->merge($persona->conductor->asignacionesActivas);
            }

            if ($esJefeArea) {
                $idsArea = $persona->encargadoAreas()->pluck('id_area');

                if ($idsArea->isNotEmpty()) {
                    $vehiculosArea = Vehiculo::select($this->columnasVehiculoOpt())
                        ->where('estado_vehiculo', 'ACTIVO')
                        ->whereHas('areas', function ($query) use ($idsArea) {
                            $query->whereIn('area.id', $idsArea)
                                ->where(function ($query) {
                                    $query->where('vehiculo_area.estado_asignacion', 'ACTIVO')
                                        ->orWhere('vehiculo_area.estado_asignacion', 'PROVISIONAL');
                                })
                                ->where(function ($query) {
                                    $query->whereNull('vehiculo_area.fecha_culminacion')
                                        ->orWhere('vehiculo_area.fecha_culminacion', '>', now());
                                });
                        })
                        ->get();

                    $vehiculos = $vehiculos->merge($vehiculosArea);
                }
            }

            $vehiculos = $vehiculos->unique('id');
        }

        if ($incluirSiFalta && ! $vehiculos->contains('id', $incluirSiFalta->id)) {
            $vehiculos = $vehiculos->push($incluirSiFalta);
        }

        $vehiculos = $vehiculos->sortBy('nro_placa')->values();

        // El combo para elegir conductor sólo se muestra a quien no tiene el
        // rol conductor (ver mostrarSelectorConductor en create()/edit()):
        // sólo en ese caso vale la pena resolver, en una única consulta
        // agrupada por vehículo (evita el N+1 de un conductoresAsignados()
        // por vehículo, relevante con ~200 vehículos para administrador).
        $conductoresPorVehiculo = $user->hasRole('conductor')
            ? collect()
            : $this->conductoresAsignadosPorVehiculo($vehiculos->pluck('id'));

        return $vehiculos
            ->map(fn (Vehiculo $vehiculo) => $this->vehiculoOpt($vehiculo, $conductoresPorVehiculo->get($vehiculo->id, collect())))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function columnasVehiculoOpt(): array
    {
        return ['id', 'codigo', 'nro_placa', 'marca', 'anio', 'modelo', 'id_tipo_vehiculo', 'id_tipo_combustible', 'tipo_medicion'];
    }

    /**
     * Conductores actualmente asignados (ACTIVO o PROVISIONAL, ver
     * Vehiculo::conductoresAsignados()) de cada vehículo de $idsVehiculo, en
     * una sola consulta agrupada por id_vehiculo.
     *
     * @param  Collection<int, int>  $idsVehiculo
     * @return Collection<int, Collection>
     */
    private function conductoresAsignadosPorVehiculo($idsVehiculo)
    {
        if ($idsVehiculo->isEmpty()) {
            return collect();
        }

        return Asignacion::with('conductor.persona:id,nombres,paterno,materno,ci')
            ->whereIn('id_vehiculo', $idsVehiculo)
            ->where(function ($query) {
                $query->where('estado_asignacion', 'ACTIVO')
                    ->orWhere('estado_asignacion', 'PROVISIONAL');
            })
            ->where(function ($query) {
                $query->whereNull('fecha_culminacion')
                    ->orWhere('fecha_culminacion', '>', now());
            })
            ->get()
            ->groupBy('id_vehiculo');
    }

    /**
     * @param  Collection<int, Asignacion>  $conductoresAsignados
     * @return array{id: int, label: string, meta: array}
     */
    private function vehiculoOpt(Vehiculo $vehiculo, $conductoresAsignados = null): array
    {
        return [
            'id' => $vehiculo->id,
            'label' => "{$vehiculo->codigo} — {$vehiculo->nro_placa} — {$vehiculo->marca} ({$vehiculo->anio})",
            'meta' => [
                'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
                'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
                'tipo_medicion' => $vehiculo->tipo_medicion,
                'marca' => $vehiculo->marca,
                'nro_placa' => $vehiculo->nro_placa,
                'anio' => $vehiculo->anio,
                'modelo' => $vehiculo->modelo,
                'codigo' => $vehiculo->codigo,
                // Conductores activos/provisionales asignados a este
                // vehículo (titular + eventuales reemplazos por permiso o
                // vacaciones) para el combo "Conductor" del formulario.
                'conductoresAsignados' => collect($conductoresAsignados)
                    ->unique('id_conductor')
                    ->map(fn ($asignacion) => [
                        'id' => $asignacion->id_conductor,
                        'label' => "{$asignacion->conductor->persona->nombre_completo} (CI: {$asignacion->conductor->persona->ci})",
                    ])
                    ->values()
                    ->all(),
            ],
        ];
    }

    /**
     * Actividades sugeridas por área, con el mismo criterio de rol que
     * vehiculosDisponibles(): conductor -> sus áreas; jefe-area -> las áreas
     * a su cargo; cualquier otro rol -> sin filtro (todas las actividades).
     */
    private function actividadesSugeridas(User $user)
    {
        $persona = $user->persona;
        $idsArea = collect();

        if ($user->hasRole('conductor') && $persona?->conductor) {
            $idsArea = $idsArea->merge($persona->conductor->areas()->pluck('id'));
        }

        if ($user->hasRole('jefe-area') && $persona) {
            $idsArea = $idsArea->merge($persona->encargadoAreas()->pluck('id_area'));
        }

        $query = Actividad::select('id', 'nombre_actividad', 'unidad_medida');

        if ($idsArea->isNotEmpty()) {
            $query->whereIn('id_area', $idsArea->unique());
        }

        return $query->get();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(OperacionStoreRequest $request, OperacionDiaria $operacionDiaria, UpdateOperacionDiariaAction $action)
    {
        try {
            $operacionDiaria = $action->execute($operacionDiaria, $this->datosConConductorResuelto($request));

            return redirect()->route('operacion-diaria.index')->with('success', 'Operación diaria actualizada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al actualizar la operación diaria: '.$e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OperacionDiaria $operacionDiaria)
    {
        if ($operacionDiaria->estado === 'VERIFICADO') {
            return redirect()->back()->with('error', 'No se puede eliminar una operación diaria que ya ha sido verificada.');
        }

        try {

            $operacionDiaria->actividadesRealizadas()->detach();
            $operacionDiaria->mantenimientosOperacion()->detach();

            $operacionDiaria->delete();

            return redirect()->route('operacion-diaria.index')->with('success', 'Operación diaria eliminada exitosamente.');
        } catch (\Exception $e) {

            return redirect()->back()->with('error', 'Error al eliminar la operación diaria: '.$e->getMessage());
        }
    }

    public function validarActividad(Request $request)
    {
        // Validar los campos requeridos
        $validated = $request->validate([
            'lugar' => 'required_without:origen|nullable|string',
            'origen' => 'required_without:lugar|nullable|string',
            'destino' => 'required_without:lugar|nullable|string',
            'id_material' => ['nullable', 'integer', Rule::exists('material', 'id')],
            'actividad' => 'required|string|min:3',
            'cantidad' => 'required|numeric|min:1',
            'unidad_medida' => 'required|string',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i',
        ]);

        // Agregar la actividad al arreglo de actividades_realizadas

        return redirect()->route('operacion-diaria.create')->with('success', 'Actividad agregada exitosamente.');
    }

    public function generarPDF(OperacionDiaria $operacionDiaria)
    {
        $operacion = $operacionDiaria->load(['conductor.persona', 'vehiculo', 'area', 'verificador', 'actividadesRealizadas']);

        $reporte = new Reportes;

        $reporte->generarReporteOperacionDiaria($operacion);
        exit;
    }

    public function verificarOperacion(Request $request)
    {
        $operacion = OperacionDiaria::findOrFail($request->id_operacion);
        $operacion->estado = 'VERIFICADO';
        $operacion->id_verificador = request()->user()->id_persona;
        $operacion->save();

        return redirect()->route('operacion-diaria.show', $operacion->id)
            ->with('success', 'Operación diaria verificada exitosamente.');
    }
}
