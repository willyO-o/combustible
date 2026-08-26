<?php

namespace App\Http\Controllers;

use App\Actions\SolicitudMantenimiento\CreateSolicitudMantenimientoAction;
use App\Http\Requests\SolicitudMantenimientoRequest;
use App\Libraries\Reportes;
use App\Models\Conductor;
use App\Models\SolicitudMantenimiento;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paso 1 del flujo de mantenimiento: el CHOFER registra una solicitud
 * o alarma de mantenimiento con los datos del vehículo y la posible
 * falla o mantenimiento preventivo.
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
            'solicitudes' => $solicitudes,
            'vehiculos' => $vehiculos,
            'filters' => $request->only(['estado', 'tipo_mantenimiento', 'id_vehiculo']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Formulario para crear una solicitud (Paso 1).
     *
     * Sólo los conductores registran solicitudes de mantenimiento: el
     * vehículo y el conductor se determinan a partir de su propia persona.
     */
    public function create(Request $request): Response
    {
        if (! $request->user()->hasRole('conductor')) {
            abort(403, 'Sólo los conductores pueden registrar solicitudes de mantenimiento.');
        }

        $conductores = Conductor::join('persona', 'conductor.id', '=', 'persona.id')
            ->orderBy('persona.nombres')
            ->get();

        $conductor = null;
        if ($request->user()->hasRole('conductor')) {
            $conductor = Conductor::with('persona')->where('id', $request->user()->id_persona)->first();
            $vehiculos = $conductor->asignacionesActivas;
        } else {
            $vehiculos = Vehiculo::select('id', 'codigo', 'nro_placa', 'marca')
                ->where('estado_vehiculo', 'ACTIVO')
                ->orderBy('nro_placa')->get();
        }

        return Inertia::render('SolicitudMantenimiento/Create', [
            'vehiculos' => $vehiculos,
            'conductores' => $conductores,
            'conductor' => $conductor,
        ]);
    }

    /**
     * Guarda la solicitud de mantenimiento.
     *
     * El acceso ya queda restringido a conductores por SolicitudMantenimientoRequest::authorize().
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
