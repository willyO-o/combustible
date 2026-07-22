<?php

namespace App\Http\Controllers;

use App\Http\Requests\SolicitudMantenimientoRequest;
use App\Http\Requests\PlanMantenimientoRequest;
use App\Http\Requests\EjecucionMantenimientoRequest;
use App\Models\Conductor;
use App\Models\MantenimientoRepuesto;
use App\Models\PlanMantenimiento;
use App\Models\Repuesto;
use App\Models\SolicitudMantenimiento;
use App\Models\Taller;
use App\Models\TipoMantenimiento;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MantenimientoController extends Controller
{
    // ══════════════════════════════════════════════════════════════════════════
    //  SOLICITUDES DE MANTENIMIENTO (Paso 1 – Chofer)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Lista todas las solicitudes de mantenimiento.
     */
    public function indexSolicitudes(Request $request): Response
    {
        $query = SolicitudMantenimiento::with(['vehiculo', 'conductor', 'usuarioRegistra', 'planMantenimiento']);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('tipo_mantenimiento')) {
            $query->where('tipo_mantenimiento', $request->tipo_mantenimiento);
        }
        if ($request->filled('id_vehiculo')) {
            $query->where('id_vehiculo', $request->id_vehiculo);
        }

        $solicitudes = $query->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $vehiculos = Vehiculo::select('id', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        return Inertia::render('Mantenimiento/SolicitudesIndex', [
            'solicitudes' => $solicitudes,
            'vehiculos'   => $vehiculos,
            'filters'     => $request->only(['estado', 'tipo_mantenimiento', 'id_vehiculo']),
            'flash'       => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Formulario para crear una solicitud (Paso 1).
     */
    public function createSolicitud(): Response
    {
        $vehiculos   = Vehiculo::select('id', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        $conductores = Conductor::select('id', 'ci', 'nombres', 'paterno')
            ->where('estado_conductor', 'ACTIVO')
            ->orderBy('nombres')
            ->get();

        return Inertia::render('Mantenimiento/SolicitudesCreate', [
            'vehiculos'   => $vehiculos,
            'conductores' => $conductores,
        ]);
    }

    /**
     * Guarda la solicitud de mantenimiento.
     */
    public function storeSolicitud(SolicitudMantenimientoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['id_usuario_registra'] = Auth::id();
        $data['estado']              = 'PENDIENTE';

        SolicitudMantenimiento::create($data);

        return redirect()->route('mantenimiento.solicitudes.index')
            ->with('success', 'Solicitud de mantenimiento registrada exitosamente.');
    }

    /**
     * Detalle de una solicitud.
     */
    public function showSolicitud(SolicitudMantenimiento $solicitud): Response
    {
        $solicitud->load(['vehiculo', 'conductor', 'usuarioRegistra', 'planMantenimiento.tipoMantenimiento']);

        return Inertia::render('Mantenimiento/SolicitudesShow', [
            'solicitud' => $solicitud,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  ÓRDENES DE TRABAJO / PLAN MANTENIMIENTO (Paso 2 – Jefe de Transportes)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Lista todas las órdenes de mantenimiento.
     */
    public function indexOrdenes(Request $request): Response
    {
        $query = PlanMantenimiento::with([
            'vehiculo',
            'tipoMantenimiento',
            'solicitudMantenimiento',
            'taller',
            'usuarioJefe',
        ]);

        if ($request->filled('estado_plan')) {
            $query->where('estado_plan', $request->estado_plan);
        }
        if ($request->filled('tipo_mantenimiento')) {
            $query->where('tipo_mantenimiento', $request->tipo_mantenimiento);
        }
        if ($request->filled('tipo_orden')) {
            $query->where('tipo_orden', $request->tipo_orden);
        }
        if ($request->filled('id_vehiculo')) {
            $query->where('id_vehiculo', $request->id_vehiculo);
        }

        $ordenes = $query->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $vehiculos = Vehiculo::select('id', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        return Inertia::render('Mantenimiento/OrdenesIndex', [
            'ordenes'   => $ordenes,
            'vehiculos' => $vehiculos,
            'filters'   => $request->only(['estado_plan', 'tipo_mantenimiento', 'tipo_orden', 'id_vehiculo']),
            'flash'     => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Formulario para crear una orden de trabajo.
     */
    public function createOrden(Request $request): Response
    {
        $vehiculos        = Vehiculo::select('id', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        $tiposMantenimiento = TipoMantenimiento::select('id', 'nombre_tipo')
            ->orderBy('nombre_tipo')
            ->get();

        $talleres = Taller::select('id', 'razon_social', 'nit')
            ->where('estado_taller', 'ACTIVO')
            ->orderBy('razon_social')
            ->get();

        // Solicitudes pendientes de aprobación (sin orden asignada aún)
        $solicitudesPendientes = SolicitudMantenimiento::with('vehiculo')
            ->where('estado', 'PENDIENTE')
            ->whereDoesntHave('planMantenimiento')
            ->orderBy('fecha_solicitud', 'desc')
            ->get();

        // Pre-selección si viene desde una solicitud
        $solicitudPreseleccionada = null;
        if ($request->filled('solicitud')) {
            $solicitudPreseleccionada = SolicitudMantenimiento::with('vehiculo', 'conductor')
                ->find($request->solicitud);
        }

        return Inertia::render('Mantenimiento/OrdenesCreate', [
            'vehiculos'                => $vehiculos,
            'tiposMantenimiento'       => $tiposMantenimiento,
            'talleres'                 => $talleres,
            'solicitudesPendientes'    => $solicitudesPendientes,
            'solicitudPreseleccionada' => $solicitudPreseleccionada,
        ]);
    }

    /**
     * Guarda la orden de trabajo.
     */
    public function storeOrden(PlanMantenimientoRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['id_usuario_jefe'] = Auth::id();
        $data['estado_plan']     = 'PENDIENTE';
        $data['fecha_orden']     = now()->toDateString();

        $orden = PlanMantenimiento::create($data);

        // Marcar la solicitud origen como aprobada
        if (!empty($data['id_solicitud_mantenimiento'])) {
            SolicitudMantenimiento::where('id', $data['id_solicitud_mantenimiento'])
                ->update(['estado' => 'APROBADA']);
        }

        return redirect()->route('mantenimiento.ordenes.index')
            ->with('success', 'Orden de trabajo N° ' . $orden->id . ' creada exitosamente.');
    }

    /**
     * Detalle de una orden de trabajo.
     */
    public function showOrden(PlanMantenimiento $orden): Response
    {
        $orden->load([
            'vehiculo',
            'tipoMantenimiento',
            'solicitudMantenimiento.conductor',
            'taller',
            'usuarioJefe',
            'usuarioEjecuta',
            'repuestos.repuesto',
        ]);

        $repuestos = Repuesto::select('id', 'nombre_repuesto', 'codigo_repuesto', 'unidad_medida')
            ->where('estado_repuesto', 'ACTIVO')
            ->orderBy('nombre_repuesto')
            ->get();

        return Inertia::render('Mantenimiento/OrdenesShow', [
            'orden'     => $orden,
            'repuestos' => $repuestos,
            'flash'     => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Formulario de edición de la orden (sólo en estado BORRADOR/PENDIENTE).
     */
    public function editOrden(PlanMantenimiento $orden): Response
    {
        $orden->load(['vehiculo', 'tipoMantenimiento', 'solicitudMantenimiento', 'taller']);

        $vehiculos          = Vehiculo::select('id', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        $tiposMantenimiento = TipoMantenimiento::select('id', 'nombre_tipo')
            ->orderBy('nombre_tipo')
            ->get();

        $talleres           = Taller::select('id', 'razon_social', 'nit')
            ->where('estado_taller', 'ACTIVO')
            ->orderBy('razon_social')
            ->get();

        return Inertia::render('Mantenimiento/OrdenesEdit', [
            'orden'             => $orden,
            'vehiculos'         => $vehiculos,
            'tiposMantenimiento'=> $tiposMantenimiento,
            'talleres'          => $talleres,
        ]);
    }

    /**
     * Actualiza la orden de trabajo.
     */
    public function updateOrden(PlanMantenimientoRequest $request, PlanMantenimiento $orden): RedirectResponse
    {
        $orden->update($request->validated());

        return redirect()->route('mantenimiento.ordenes.show', $orden)
            ->with('success', 'Orden de trabajo actualizada exitosamente.');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  REGISTRO DE EJECUCIÓN (Paso 3 – Jefe de Transportes registra lo hecho)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Formulario para registrar la ejecución del mantenimiento.
     */
    public function createEjecucion(PlanMantenimiento $orden): Response
    {
        $orden->load(['vehiculo', 'tipoMantenimiento', 'taller']);

        $repuestos = Repuesto::select('id', 'nombre_repuesto', 'codigo_repuesto', 'unidad_medida')
            ->where('estado_repuesto', 'ACTIVO')
            ->orderBy('nombre_repuesto')
            ->get();

        return Inertia::render('Mantenimiento/EjecucionCreate', [
            'orden'     => $orden,
            'repuestos' => $repuestos,
        ]);
    }

    /**
     * Guarda el registro de ejecución (trabajo realizado + insumos).
     */
    public function storeEjecucion(EjecucionMantenimientoRequest $request, PlanMantenimiento $orden): RedirectResponse
    {
        DB::transaction(function () use ($request, $orden) {
            $data = $request->safe()->except('items');
            $data['id_usuario_ejecuta'] = Auth::id();
            $data['estado_plan']        = 'COMPLETADO';

            $orden->update($data);

            // Eliminar ítems anteriores (por si se re-envía el formulario)
            $orden->repuestos()->delete();

            // Guardar ítems utilizados
            foreach ($request->items ?? [] as $item) {
                MantenimientoRepuesto::create([
                    'id_plan_mantenimiento' => $orden->id,
                    'id_repuesto'           => $item['id_repuesto'] ?? null,
                    'tipo_item'             => $item['tipo_item'],
                    'nombre_item'           => $item['nombre_item'] ?? null,
                    'unidad_medida'         => $item['unidad_medida'],
                    'cantidad_utilizada'    => $item['cantidad_utilizada'],
                    'costo_unitario'        => $item['costo_unitario'],
                    'subtotal'              => $item['subtotal'],
                    'observacion'           => $item['observacion'] ?? null,
                ]);
            }
        });

        return redirect()->route('mantenimiento.ordenes.show', $orden)
            ->with('success', 'Ejecución del mantenimiento registrada exitosamente.');
    }

    /**
     * Cambia el estado de una orden de trabajo.
     */
    public function cambiarEstadoOrden(Request $request, PlanMantenimiento $orden): RedirectResponse
    {
        $request->validate([
            'estado_plan' => ['required', 'in:BORRADOR,PENDIENTE,EN_PROCESO,COMPLETADO,VENCIDO,ANULADO'],
        ]);

        $orden->update(['estado_plan' => $request->estado_plan]);

        return redirect()->back()
            ->with('success', 'Estado de la orden actualizado a "' . $request->estado_plan . '".');
    }
}
