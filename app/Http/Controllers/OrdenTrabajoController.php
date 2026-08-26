<?php

namespace App\Http\Controllers;

use App\Http\Requests\DetalleMantenimientoRequest;
use App\Http\Requests\EjecucionOrdenTrabajoRequest;
use App\Http\Requests\OrdenTrabajoRequest;
use App\Models\DetalleMantenimiento;
use App\Models\OrdenTrabajo;
use App\Models\Repuesto;
use App\Models\SolicitudMantenimiento;
use App\Models\Taller;
use App\Models\TipoMantenimiento;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paso 2 y 3 del flujo de mantenimiento: el JEFE DE TRANSPORTES verifica
 * la solicitud, emite una orden de trabajo asignando un responsable de
 * ejecución, y luego se registra la culminación con el detalle de
 * repuestos/insumos/mano de obra aplicados.
 */
class OrdenTrabajoController extends Controller
{
    /**
     * Lista todas las órdenes de trabajo.
     */
    public function index(Request $request): Response
    {
        $query = OrdenTrabajo::with([
            'vehiculo',
            'solicitudMantenimiento',
            'taller',
            'usuarioEmite',
            'usuarioEjecuta',
        ]);

        if ($request->filled('estado_orden')) {
            $query->where('estado_orden', $request->estado_orden);
        }
        if ($request->filled('tipo_mantenimiento')) {
            $query->where('tipo_mantenimiento', $request->tipo_mantenimiento);
        }
        if ($request->filled('tipo_orden')) {
            $request->tipo_orden === 'EXTERNO'
                ? $query->whereNotNull('id_taller')
                : $query->whereNull('id_taller');
        }
        if ($request->filled('id_vehiculo')) {
            $query->where('id_vehiculo', $request->id_vehiculo);
        }

        if ($this->esSoloTecnico($request->user())) {
            $query->where('id_usuario_ejecuta', $request->user()->id);
        }

        $ordenes = $query->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $vehiculos = Vehiculo::select('id', 'codigo', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        return Inertia::render('OrdenTrabajo/Index', [
            'ordenes' => $ordenes,
            'vehiculos' => $vehiculos,
            'filters' => $request->only(['estado_orden', 'tipo_mantenimiento', 'tipo_orden', 'id_vehiculo']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Formulario para emitir una orden de trabajo.
     *
     * Sólo jefes de área y administradores pueden generar órdenes de trabajo.
     */
    public function create(Request $request): Response
    {
        if (! $request->user()->hasAnyRole(['super-admin', 'administrador', 'jefe-area'])) {
            abort(403, 'Sólo un jefe de área o administrador puede generar una orden de trabajo.');
        }

        $vehiculos = Vehiculo::select('id', 'codigo', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        $talleres = Taller::select('id', 'razon_social', 'nit')
            ->where('estado_taller', 'ACTIVO')
            ->orderBy('razon_social')
            ->get();

        // Sólo técnicos de mantenimiento activos pueden ser asignados como
        // responsables de ejecución de la orden.
        $usuarios = User::role('tecnico-mantenimiento')
            ->select('id', 'name')
            ->where('estado_usuario', 'ACTIVO')
            ->orderBy('name')
            ->get();

        // Solicitudes pendientes de aprobación (sin orden asignada aún)
        $solicitudesPendientes = SolicitudMantenimiento::with('vehiculo', 'conductor')
            ->where('estado', 'PENDIENTE')
            ->whereDoesntHave('ordenTrabajo')
            ->orderBy('fecha_solicitud', 'desc')
            ->get();

        // Pre-selección si viene desde una solicitud
        $solicitudPreseleccionada = null;
        if ($request->filled('solicitud')) {
            $solicitudPreseleccionada = SolicitudMantenimiento::with('vehiculo', 'conductor')
                ->find($request->solicitud);
        }

        return Inertia::render('OrdenTrabajo/Create', [
            'vehiculos' => $vehiculos,
            'talleres' => $talleres,
            'usuarios' => $usuarios,
            'solicitudesPendientes' => $solicitudesPendientes,
            'solicitudPreseleccionada' => $solicitudPreseleccionada,
        ]);
    }

    /**
     * Guarda la orden de trabajo.
     *
     * El acceso ya queda restringido a jefes de área/administradores por
     * OrdenTrabajoRequest::authorize().
     */
    public function store(OrdenTrabajoRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Si la orden nace de una solicitud, el vehículo/conductor y la
        // clasificación del mantenimiento se toman siempre de la solicitud de
        // origen: se fuerzan aquí para que no puedan alterarse manipulando el
        // formulario (que ya los muestra bloqueados como información).
        if (! empty($data['id_solicitud_mantenimiento'])) {
            $solicitud = SolicitudMantenimiento::findOrFail($data['id_solicitud_mantenimiento']);
            $data['id_vehiculo'] = $solicitud->id_vehiculo;
            $data['id_conductor'] = $solicitud->id_conductor;
            $data['tipo_mantenimiento'] = $solicitud->tipo_mantenimiento;
            $data['kilometraje_actual'] = $solicitud->kilometraje_actual;
            $data['horometro_actual'] = $solicitud->horometro_actual;
        }

        $orden = OrdenTrabajo::create($data);

        // Marcar la solicitud origen como aprobada
        if ($orden->id_solicitud_mantenimiento) {
            SolicitudMantenimiento::where('id', $orden->id_solicitud_mantenimiento)
                ->update(['estado' => 'APROBADA']);
        }

        return redirect()->route('mantenimiento.ordenes.show', $orden)
            ->with('success', 'Orden de trabajo N° '.$orden->nro.' emitida exitosamente.');
    }

    /**
     * Detalle de una orden de trabajo.
     *
     * Un técnico de mantenimiento sólo puede ver las órdenes que tiene asignadas.
     */
    public function show(Request $request, OrdenTrabajo $orden): Response
    {
        if ($this->esSoloTecnico($request->user()) && $orden->id_usuario_ejecuta !== $request->user()->id) {
            abort(403, 'No tiene acceso a esta orden de trabajo.');
        }

        $orden->load([
            'vehiculo',
            'conductor',
            'solicitudMantenimiento',
            'taller',
            'usuarioEmite',
            'usuarioEjecuta',
            'detalles.repuesto',
            'detalles.tipoMantenimiento',
        ]);

        return Inertia::render('OrdenTrabajo/Show', [
            'orden' => $orden,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Formulario de edición de la orden (sólo mientras está PENDIENTE).
     *
     * Sólo jefes de área y administradores pueden editar órdenes de trabajo.
     */
    public function edit(Request $request, OrdenTrabajo $orden): Response
    {
        if (! $request->user()->hasAnyRole(['super-admin', 'administrador', 'jefe-area'])) {
            abort(403, 'Sólo un jefe de área o administrador puede editar la orden de trabajo.');
        }

        $orden->load(['vehiculo', 'conductor', 'solicitudMantenimiento', 'taller']);

        $vehiculos = Vehiculo::select('id', 'codigo', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        $talleres = Taller::select('id', 'razon_social', 'nit')
            ->where('estado_taller', 'ACTIVO')
            ->orderBy('razon_social')
            ->get();

        $usuarios = User::role('tecnico-mantenimiento')
            ->select('id', 'name')
            ->where('estado_usuario', 'ACTIVO')
            ->orderBy('name')
            ->get();

        return Inertia::render('OrdenTrabajo/Edit', [
            'orden' => $orden,
            'vehiculos' => $vehiculos,
            'talleres' => $talleres,
            'usuarios' => $usuarios,
        ]);
    }

    /**
     * Actualiza la orden de trabajo.
     *
     * El acceso ya queda restringido a jefes de área/administradores por
     * OrdenTrabajoRequest::authorize().
     */
    public function update(OrdenTrabajoRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        $orden->update($request->validated());

        return redirect()->route('mantenimiento.ordenes.show', $orden)
            ->with('success', 'Orden de trabajo actualizada exitosamente.');
    }

    /**
     * Cambia el estado de una orden de trabajo.
     *
     * - VERIFICADO sólo puede ser marcado por el usuario que emitió la orden.
     * - Un técnico de mantenimiento sólo puede mover sus propias órdenes
     *   asignadas, y únicamente a EN_EJECUCION o CULMINADO.
     * - Jefes de área/administradores pueden fijar cualquier otro estado.
     */
    public function cambiarEstado(Request $request, OrdenTrabajo $orden): RedirectResponse
    {
        $request->validate([
            'estado_orden' => ['required', 'in:PENDIENTE,EN_EJECUCION,CULMINADO,CANCELADO,VERIFICADO'],
        ]);

        $user = $request->user();
        $nuevoEstado = $request->estado_orden;

        if ($nuevoEstado === 'VERIFICADO') {
            if ($orden->id_usuario_emite !== $user->id) {
                abort(403, 'Sólo el usuario que emitió la orden puede marcarla como VERIFICADO.');
            }
        } elseif ($this->esSoloTecnico($user)) {
            if ($orden->id_usuario_ejecuta !== $user->id) {
                abort(403, 'Sólo puede actualizar el estado de las órdenes que tiene asignadas.');
            }
            if (! in_array($nuevoEstado, ['EN_EJECUCION', 'CULMINADO'], true)) {
                abort(403, 'Como técnico de mantenimiento sólo puede marcar la orden como EN_EJECUCION o CULMINADO.');
            }
        } elseif (! $user->hasAnyRole(['super-admin', 'administrador', 'jefe-area'])) {
            abort(403, 'No tiene permiso para cambiar el estado de esta orden.');
        }

        $data = ['estado_orden' => $nuevoEstado];

        if ($nuevoEstado === 'EN_EJECUCION' && ! $orden->fecha_ejecucion) {
            $data['fecha_ejecucion'] = now();
        }

        $orden->update($data);

        return redirect()->back()
            ->with('success', 'Estado de la orden actualizado a "'.$nuevoEstado.'".');
    }

    /**
     * Formulario para gestionar la ejecución de la orden (Paso 3): el técnico
     * va registrando el detalle del trabajo realizado ítem por ítem, a su
     * propio ritmo, y culmina la orden cuando termina.
     *
     * Un técnico de mantenimiento sólo puede gestionar la ejecución de las
     * órdenes que tiene asignadas.
     */
    public function createEjecucion(Request $request, OrdenTrabajo $orden): Response
    {
        if ($this->esSoloTecnico($request->user()) && $orden->id_usuario_ejecuta !== $request->user()->id) {
            abort(403, 'Sólo puede registrar la ejecución de las órdenes que tiene asignadas.');
        }

        $orden->load(['vehiculo', 'taller', 'detalles.repuesto', 'detalles.tipoMantenimiento']);

        $tiposMantenimiento = TipoMantenimiento::select('id', 'tipo_mantenimiento')
            ->orderBy('tipo_mantenimiento')
            ->get();

        $repuestos = Repuesto::select('id', 'nombre_repuesto', 'codigo_repuesto', 'unidad_medida', 'stock_actual')
            ->where('estado_repuesto', 'ACTIVO')
            ->orderBy('nombre_repuesto')
            ->get();

        return Inertia::render('OrdenTrabajo/Ejecucion/Create', [
            'orden' => $orden,
            'tiposMantenimiento' => $tiposMantenimiento,
            'repuestos' => $repuestos,
            // Una vez culminada (o verificada/cancelada) la orden, el detalle
            // queda congelado: ni se agregan, editan o eliminan ítems.
            'puedeModificar' => in_array($orden->estado_orden, ['PENDIENTE', 'EN_EJECUCION'], true),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Registra un ítem del detalle de trabajo realizado. El técnico llama a
     * este endpoint cada vez que completa una acción (puede ir agregando
     * ítems de a poco, no hace falta cargarlos todos de una vez).
     */
    public function storeDetalle(DetalleMantenimientoRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        $this->assertDetalleModificable($orden);

        $orden->detalles()->create($request->validated());

        return redirect()->route('mantenimiento.ordenes.ejecucion.create', $orden)
            ->with('success', 'Detalle registrado exitosamente.');
    }

    /**
     * Corrige un ítem del detalle ya registrado (mientras la orden no esté
     * culminada).
     */
    public function updateDetalle(DetalleMantenimientoRequest $request, OrdenTrabajo $orden, DetalleMantenimiento $detalle): RedirectResponse
    {
        $this->assertDetalleModificable($orden);
        abort_if($detalle->id_orden_trabajo !== $orden->id, 404);

        $detalle->update($request->validated());

        return redirect()->route('mantenimiento.ordenes.ejecucion.create', $orden)
            ->with('success', 'Detalle actualizado exitosamente.');
    }

    /**
     * Elimina un ítem del detalle (mientras la orden no esté culminada).
     */
    public function destroyDetalle(Request $request, OrdenTrabajo $orden, DetalleMantenimiento $detalle): RedirectResponse
    {
        if ($this->esSoloTecnico($request->user()) && $orden->id_usuario_ejecuta !== $request->user()->id) {
            abort(403, 'Sólo puede registrar la ejecución de las órdenes que tiene asignadas.');
        }

        $this->assertDetalleModificable($orden);
        abort_if($detalle->id_orden_trabajo !== $orden->id, 404);

        $detalle->delete();

        return redirect()->route('mantenimiento.ordenes.ejecucion.create', $orden)
            ->with('success', 'Detalle eliminado exitosamente.');
    }

    /**
     * Culmina la ejecución de la orden: registra las lecturas finales y
     * congela el detalle de trabajo (ya no admite más ítems ni ediciones).
     *
     * El acceso ya queda restringido a las órdenes asignadas al técnico por
     * EjecucionOrdenTrabajoRequest::authorize().
     */
    public function culminarEjecucion(EjecucionOrdenTrabajoRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        $this->assertDetalleModificable($orden);

        if ($orden->detalles()->doesntExist()) {
            return redirect()->back()
                ->with('error', 'Debe registrar al menos un ítem del detalle antes de culminar la orden.');
        }

        $data = $request->validated();
        // fecha_ejecucion ya debería estar registrada desde que se marcó EN_EJECUCION
        // (cambiarEstado); si no lo está, se completa aquí como respaldo.
        $data['fecha_ejecucion'] = $orden->fecha_ejecucion ?? now();
        $data['fecha_culminacion'] = now();
        $data['estado_orden'] = 'CULMINADO';

        $orden->update($data);

        return redirect()->route('mantenimiento.ordenes.show', $orden)
            ->with('success', 'Ejecución de la orden de trabajo culminada exitosamente.');
    }

    /**
     * El detalle de trabajo sólo se puede modificar mientras la orden no
     * esté culminada/verificada/cancelada.
     */
    private function assertDetalleModificable(OrdenTrabajo $orden): void
    {
        if (! in_array($orden->estado_orden, ['PENDIENTE', 'EN_EJECUCION'], true)) {
            abort(403, 'La orden ya fue culminada: no se puede modificar su detalle de trabajo.');
        }
    }

    /**
     * Un técnico de mantenimiento sin ningún rol de gestión (jefe de área,
     * administrador o super-admin) sólo puede operar sobre sus propias
     * órdenes asignadas.
     */
    private function esSoloTecnico(User $user): bool
    {
        return $user->hasRole('tecnico-mantenimiento')
            && ! $user->hasAnyRole(['super-admin', 'administrador', 'jefe-area']);
    }
}
