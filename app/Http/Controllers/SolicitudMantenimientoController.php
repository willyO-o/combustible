<?php

namespace App\Http\Controllers;

use App\Actions\SolicitudMantenimiento\CreateSolicitudMantenimientoAction;
use App\Http\Requests\SolicitudMantenimientoRequest;
use App\Libraries\Reportes;
use App\Models\Asignacion;
use App\Models\SolicitudMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paso 1 del flujo de mantenimiento: se registra una solicitud o alarma de
 * mantenimiento con los datos del vehículo y la posible falla o
 * mantenimiento preventivo. Antes sólo el conductor podía registrarla (sobre
 * su propio vehículo); ahora un jefe de área o administrador también puede,
 * eligiendo vehículo y conductor (ver create()/esConductorFinal()).
 */
class SolicitudMantenimientoController extends Controller
{
    /**
     * Lista todas las solicitudes de mantenimiento.
     */
    public function index(Request $request): Response
    {
        $query = SolicitudMantenimiento::with(['vehiculo', 'conductor.persona', 'usuarioRegistra', 'ordenTrabajo']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('tipo_mantenimiento')) {
            $query->where('tipo_mantenimiento', $request->tipo_mantenimiento);
        }
        if ($request->filled('id_vehiculo')) {
            $query->where('id_vehiculo', $request->id_vehiculo);
        }

        if ($request->user()->hasRole('conductor')) {
            $query->where('id_conductor', $request->user()->id_persona);
        }

        $solicitudes = $query->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $vehiculos = Vehiculo::select('id', 'codigo', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        return Inertia::render('SolicitudMantenimiento/Index', [
            // Inertia::scroll() no cambia la forma del paginador (sigue
            // trayendo data/total/from/to/links tal cual): sólo agrega la
            // metadata de merge que usa <InfiniteScroll> en el listado de
            // tarjetas (mobile). Ver .ai/rules/pages.md.
            'solicitudes' => Inertia::scroll($solicitudes),
            'vehiculos' => $vehiculos,
            'filters' => $request->only(['estado', 'tipo_mantenimiento', 'id_vehiculo']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Formulario para crear una solicitud (Paso 1), según el rol:
     * - conductor "puro" (sin rol de gestión): sólo sus propios vehículos
     *   asignados, sin elegir conductor (siempre es él mismo).
     * - jefe-area (incluso si además es conductor: ese rol prevalece, a
     *   diferencia de Operación Diaria, ver .ai/rules/operacion.md): sólo los
     *   vehículos de las áreas que tiene a cargo, y debe elegir el conductor
     *   entre los realmente asignados al vehículo.
     * - cualquier otro rol (administrador, super-admin, etc.): todos los
     *   vehículos activos, y también elige el conductor.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('SolicitudMantenimiento/Create', [
            'vehiculos' => $this->vehiculosDisponibles($user),
            // El combo de conductor sólo se muestra a quien no es "conductor
            // final" (ver esConductorFinal()).
            'mostrarSelectorConductor' => ! $this->esConductorFinal($user),
        ]);
    }

    /**
     * Mismo criterio que SolicitudMantenimientoRequest::esConductorFinal() y
     * CreateSolicitudMantenimientoAction::execute(): un conductor que además
     * tiene un rol de gestión (jefe-area, administrador, super-admin) deja de
     * operar "sobre sí mismo" — ese otro rol prevalece.
     */
    private function esConductorFinal(User $user): bool
    {
        return $user->hasRole('conductor') && ! $user->hasAnyRole(['jefe-area', 'administrador', 'super-admin']);
    }

    /**
     * @return array<int, array{id: int, label: string, meta: array}>
     */
    private function vehiculosDisponibles(User $user): array
    {
        $persona = $user->persona;

        if ($this->esConductorFinal($user) && $persona?->conductor) {
            $vehiculos = $persona->conductor->asignacionesActivas;
        } elseif ($user->hasRole('jefe-area') && $persona) {
            $idsArea = $persona->encargadoAreas()->pluck('id_area');

            $vehiculos = $idsArea->isEmpty()
                ? collect()
                : Vehiculo::select($this->columnasVehiculoOpt())
                    ->where('estado_vehiculo', 'ACTIVO')
                    ->whereHas('areasAsignadas', fn ($q) => $q->whereIn('area.id', $idsArea))
                    ->get();
        } else {
            $vehiculos = Vehiculo::select($this->columnasVehiculoOpt())
                ->where('estado_vehiculo', 'ACTIVO')
                ->get();
        }

        $vehiculos = $vehiculos->sortBy('nro_placa')->values();

        // El combo de conductor sólo se muestra a quien no es "conductor
        // final": sólo en ese caso vale la pena resolver, en una única
        // consulta agrupada por vehículo, los conductores realmente
        // asignados (evita el N+1 de un conductoresAsignados() por vehículo).
        $conductoresPorVehiculo = $this->esConductorFinal($user)
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
        return ['id', 'codigo', 'nro_placa', 'marca', 'anio'];
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
            'label' => "{$vehiculo->codigo} — {$vehiculo->nro_placa} — {$vehiculo->marca}",
            'meta' => [
                // Conductores activos/provisionales asignados a este vehículo
                // (titular + eventuales reemplazos) para el combo "Conductor"
                // del formulario.
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
     * Guarda la solicitud de mantenimiento.
     *
     * El acceso ya queda restringido por SolicitudMantenimientoRequest::authorize()
     * (conductor, jefe-area, administrador o super-admin).
     */
    public function store(SolicitudMantenimientoRequest $request, CreateSolicitudMantenimientoAction $createSolicitudMantenimientoAction): RedirectResponse
    {
        $data = $request->validated();

        $createSolicitudMantenimientoAction->execute($data, $request->user());

        return redirect()->route('mantenimiento.solicitudes.index')
            ->with('success', 'Solicitud de mantenimiento registrada exitosamente.');
    }

    /**
     * Detalle de una solicitud.
     */
    public function show(SolicitudMantenimiento $solicitud): Response
    {
        $solicitud->load(['vehiculo', 'conductor.persona', 'usuarioRegistra', 'ordenTrabajo']);

        return Inertia::render('SolicitudMantenimiento/Show', [
            'solicitud' => $solicitud,
        ]);
    }

    public function imprimir(SolicitudMantenimiento $solicitud)
    {
        $solicitud->load([
            'vehiculo', 'conductor.persona', 'usuarioRegistra',
            'ordenTrabajo.detalles.repuesto', 'ordenTrabajo.usuarioEjecuta', 'ordenTrabajo.usuarioEmite',
        ]);
        $reporte = new Reportes;
        $reporte->generarSolicitudMantenimiento($solicitud);
        exit;
    }
}
