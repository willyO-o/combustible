<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\DB;
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

        $ordenes = $query->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        $vehiculos = Vehiculo::select('id', 'nro_placa', 'marca')
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
     */
    public function create(Request $request): Response
    {
        $vehiculos = Vehiculo::select('id', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        $talleres = Taller::select('id', 'razon_social', 'nit')
            ->where('estado_taller', 'ACTIVO')
            ->orderBy('razon_social')
            ->get();

        $usuarios = User::select('id', 'name')
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
     */
    public function store(OrdenTrabajoRequest $request): RedirectResponse
    {
        $orden = OrdenTrabajo::create($request->validated());

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
     */
    public function show(OrdenTrabajo $orden): Response
    {
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
     */
    public function edit(OrdenTrabajo $orden): Response
    {
        $orden->load(['vehiculo', 'conductor', 'solicitudMantenimiento', 'taller']);

        $vehiculos = Vehiculo::select('id', 'nro_placa', 'marca')
            ->where('estado_vehiculo', 'ACTIVO')
            ->orderBy('nro_placa')
            ->get();

        $talleres = Taller::select('id', 'razon_social', 'nit')
            ->where('estado_taller', 'ACTIVO')
            ->orderBy('razon_social')
            ->get();

        $usuarios = User::select('id', 'name')
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
     */
    public function update(OrdenTrabajoRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        $orden->update($request->validated());

        return redirect()->route('mantenimiento.ordenes.show', $orden)
            ->with('success', 'Orden de trabajo actualizada exitosamente.');
    }

    /**
     * Cambia el estado de una orden de trabajo.
     */
    public function cambiarEstado(Request $request, OrdenTrabajo $orden): RedirectResponse
    {
        $request->validate([
            'estado_orden' => ['required', 'in:PENDIENTE,EN_EJECUCION,CULMINADO,CANCELADO,VERIFICADO'],
        ]);

        $data = ['estado_orden' => $request->estado_orden];

        if ($request->estado_orden === 'EN_EJECUCION' && ! $orden->fecha_ejecucion) {
            $data['fecha_ejecucion'] = now();
        }

        $orden->update($data);

        return redirect()->back()
            ->with('success', 'Estado de la orden actualizado a "'.$request->estado_orden.'".');
    }

    /**
     * Formulario para registrar la ejecución/culminación de la orden (Paso 3).
     */
    public function createEjecucion(OrdenTrabajo $orden): Response
    {
        $orden->load(['vehiculo', 'taller']);

        $tiposMantenimiento = TipoMantenimiento::select('id', 'tipo_mantenimiento')
            ->orderBy('tipo_mantenimiento')
            ->get();

        $repuestos = Repuesto::select('id', 'nombre_repuesto', 'codigo_repuesto', 'unidad_medida')
            ->where('estado_repuesto', 'ACTIVO')
            ->orderBy('nombre_repuesto')
            ->get();

        return Inertia::render('OrdenTrabajo/Ejecucion/Create', [
            'orden' => $orden,
            'tiposMantenimiento' => $tiposMantenimiento,
            'repuestos' => $repuestos,
        ]);
    }

    /**
     * Guarda el registro de ejecución/culminación (detalle de trabajo + insumos).
     */
    public function storeEjecucion(EjecucionOrdenTrabajoRequest $request, OrdenTrabajo $orden): RedirectResponse
    {
        DB::transaction(function () use ($request, $orden) {
            $data = $request->safe()->except('detalles');
            $data['fecha_ejecucion'] = $orden->fecha_ejecucion ?? $data['fecha_ejecucion'] ?? now();
            $data['estado_orden'] = 'CULMINADO';

            $orden->update($data);

            // Eliminar detalle anterior (por si se re-envía el formulario)
            $orden->detalles()->delete();

            foreach ($request->detalles as $item) {
                DetalleMantenimiento::create([
                    'id_orden_trabajo' => $orden->id,
                    'id_repuesto' => $item['id_repuesto'] ?? null,
                    'id_tipo_mantenimiento' => $item['id_tipo_mantenimiento'],
                    'detalle' => $item['detalle'] ?? null,
                    'cantidad' => $item['cantidad'],
                    'costo_unitario' => $item['costo_unitario'],
                ]);
            }
        });

        return redirect()->route('mantenimiento.ordenes.show', $orden)
            ->with('success', 'Ejecución de la orden de trabajo registrada exitosamente.');
    }
}
