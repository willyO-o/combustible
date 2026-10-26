<?php

namespace App\Http\Controllers;

use App\Actions\OperacionDiaria\ListOperariosDisponiblesAction;
use App\Actions\SolicitudMantenimiento\CreateSolicitudMantenimientoAction;
use App\Actions\Vehiculo\ListVehiculosSeleccionablesAction;
use App\Http\Requests\SolicitudMantenimientoRequest;
use App\Libraries\Reportes;
use App\Models\SolicitudMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        if ($request->user()->esConductorPuro()) {
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
     * - cualquier otro usuario con el permiso (jefe-area, administrador...,
     *   aunque además tenga el rol conductor): TODOS los vehículos activos y
     *   cualquier operario activo, ya precargados (a pedido del usuario,
     *   2026-10-17: la asignación a áreas/conductores no es confiable). Los
     *   vehículos propios y los operarios asignados al vehículo elegido se
     *   sugieren primero.
     */
    public function create(Request $request, ListVehiculosSeleccionablesAction $listVehiculos, ListOperariosDisponiblesAction $listOperarios): Response
    {
        $user = $request->user();
        $eligeVehiculoYConductor = ! $this->esConductorFinal($user);

        return Inertia::render('SolicitudMantenimiento/Create', [
            'vehiculos' => $eligeVehiculoYConductor
                ? $listVehiculos->execute($user)->all()
                : $this->vehiculosDelConductor($user),
            'operarios' => $eligeVehiculoYConductor ? $listOperarios->execute()->all() : [],
            // El combo de conductor sólo se muestra a quien no es "conductor
            // final" (ver esConductorFinal()).
            'mostrarSelectorConductor' => $eligeVehiculoYConductor,
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
        return $user->esConductorPuro();
    }

    /**
     * Vehículos asignados al conductor puro, con el mismo formato
     * {id, label, meta} que ListVehiculosSeleccionablesAction.
     *
     * @return array<int, array{id: int, label: string, meta: array<string, mixed>}>
     */
    private function vehiculosDelConductor(User $user): array
    {
        $conductor = $user->persona?->conductor;

        if (! $conductor) {
            return [];
        }

        return $conductor->asignacionesActivas
            ->sortBy('nro_placa')
            ->values()
            ->map(fn (Vehiculo $vehiculo) => [
                'id' => $vehiculo->id,
                'label' => $vehiculo->etiquetaSeleccion(),
                'meta' => [
                    'tipo_medicion' => $vehiculo->tipo_medicion,
                ],
            ])
            ->all();
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
    public function show(Request $request, SolicitudMantenimiento $solicitud): Response
    {
        // Sin mantenimiento.solicitudes.ver sólo se llega desde una orden de
        // trabajo (ver routes/web.php): el técnico ve únicamente la solicitud
        // de origen de una orden que tiene asignada.
        abort_unless(
            $request->user()->can('mantenimiento.solicitudes.ver')
                || $solicitud->ordenTrabajo()->where('id_usuario_ejecuta', $request->user()->id)->exists(),
            403
        );

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
