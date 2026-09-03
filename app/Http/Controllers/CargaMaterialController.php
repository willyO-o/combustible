<?php

namespace App\Http\Controllers;

use App\Http\Requests\CargaMaterialRequest;
use App\Http\Requests\ViajeRequest;
use App\Models\CargaMaterial;
use App\Models\Material;
use App\Models\User;
use App\Models\VehiculoExterno;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CargaMaterialController extends Controller
{
    /**
     * Lista las cargas de material. Un conductor solo ve las que él mismo
     * abrió; un jefe de área (o administrador) supervisa todas las cargas
     * del sistema, sin importar quién las haya registrado (ver también
     * assertPuedeGestionar(), que aplica el mismo criterio a editar()/CargaMaterialRequest).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = CargaMaterial::with(['vehiculoExterno', 'usuarioApertura'])
            ->withCount('viajes');

        if ($user->hasRole('conductor') && ! $user->hasAnyRole(['jefe-area', 'administrador', 'super-admin'])) {
            $query->where('id_usuario_apertura', $user->id);
        }

        if ($request->filled('estado_carga')) {
            $query->where('estado_carga', $request->estado_carga);
        }

        if ($request->filled('q')) {
            $texto = $request->q;
            $query->where(function ($query) use ($texto) {
                $query->where('nro_carga', 'like', "%{$texto}%")
                    ->orWhereHas('vehiculoExterno', function ($query) use ($texto) {
                        $query->where('nro_placa', 'like', "%{$texto}%");
                    });
            });
        }

        $cargas = $query->orderBy('nro_carga', 'desc')->orderBy('fecha_apertura', 'desc')->paginate(10)->withQueryString();

        $cargas->through(fn (CargaMaterial $carga) => [
            'id' => $carga->id,
            'nro' => $carga->nro,
            'estado_carga' => $carga->estado_carga,
            'fecha_apertura' => $carga->fecha_apertura?->format('d/m/Y H:i'),
            'nombre_conductor' => $carga->nombre_conductor,
            'telefono' => $carga->telefono,
            'es_al_exterior' => $carga->es_al_exterior,
            'pais' => $carga->pais,
            'vehiculo_externo' => $carga->vehiculoExterno ? [
                'id' => $carga->vehiculoExterno->id,
                'nro_placa' => $carga->vehiculoExterno->nro_placa,
                'propietario' => $carga->vehiculoExterno->propietario,
            ] : null,
            'viajes_count' => $carga->viajes_count,
            'abierta_por' => $carga->usuarioApertura?->name,
            'es_propia' => $carga->id_usuario_apertura === $user->id,
            'puede_editar' => $carga->estado_carga === 'ABIERTA',
        ]);

        return Inertia::render('ControlCargas/Index', [
            'cargas' => $cargas,
            'filters' => $request->only(['estado_carga', 'q']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('ControlCargas/Form', [
            'vehiculosExternos' => VehiculoExterno::orderBy('nro_placa')->get(['id', 'nro_placa', 'propietario']),
            'puedeEditarObservaciones' => $this->puedeEditarObservaciones(request()->user()),
        ]);
    }

    public function store(CargaMaterialRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        // Observaciones sólo puede definirla un jefe de área (o roles
        // superiores); si la envía un conductor, se ignora en silencio.
        if (! $this->puedeEditarObservaciones($request->user())) {
            unset($datos['observaciones']);
        }

        $carga = CargaMaterial::create($datos);

        return redirect()->route('control-cargas.show', $carga->id)
            ->with('success', "Flete #{$carga->nro} registrado exitosamente. Ya puedes registrar viajes.");
    }

    public function edit(CargaMaterial $cargaMaterial): Response
    {
        $this->assertPuedeGestionar($cargaMaterial);

        $cargaMaterial->load('vehiculoExterno');

        return Inertia::render('ControlCargas/Form', [
            'carga' => [
                'id' => $cargaMaterial->id,
                'nro' => $cargaMaterial->nro,
                'estado_carga' => $cargaMaterial->estado_carga,
                'nombre_conductor' => $cargaMaterial->nombre_conductor,
                'telefono' => $cargaMaterial->telefono,
                'es_al_exterior' => $cargaMaterial->es_al_exterior,
                'pais' => $cargaMaterial->pais,
                'detalle' => $cargaMaterial->detalle,
                'observaciones' => $cargaMaterial->observaciones,
                'vehiculo_externo' => $cargaMaterial->vehiculoExterno ? [
                    'nro_placa' => $cargaMaterial->vehiculoExterno->nro_placa,
                    'propietario' => $cargaMaterial->vehiculoExterno->propietario,
                ] : null,
            ],
            'puedeEditarObservaciones' => $this->puedeEditarObservaciones(request()->user()),
        ]);
    }

    public function update(CargaMaterialRequest $request, CargaMaterial $cargaMaterial): RedirectResponse
    {
        $this->assertPuedeGestionar($cargaMaterial);

        // El material y el vehículo externo no se pueden cambiar una vez
        // abierta la carga (ver CargaMaterialRequest::rules()): solo se
        // corrigen los datos del conductor externo y algunos datos del viaje.
        $campos = ['nombre_conductor', 'telefono', 'es_al_exterior', 'pais', 'detalle'];

        // Observaciones sólo puede editarla un jefe de área (o roles superiores).
        if ($this->puedeEditarObservaciones($request->user())) {
            $campos[] = 'observaciones';
        }

        $cargaMaterial->update($request->safe()->only($campos));

        return redirect()->route('control-cargas.index')
            ->with('success', "Flete #{$cargaMaterial->nro} actualizado exitosamente.");
    }

    public function show(CargaMaterial $cargaMaterial): Response
    {
        $cargaMaterial->load(['vehiculoExterno', 'usuarioApertura', 'usuarioCierre']);

        $viajes = $cargaMaterial->viajes()
            ->with(['material:id,material', 'usuarioRegistro:id,name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($viaje) => [
                'id' => $viaje->id,
                'material' => $viaje->material?->material,
                'foto_url' => $viaje->foto_url,
                'origen' => $viaje->origen,
                'destino' => $viaje->destino,
                'detalle' => $viaje->detalle,
                'registrado_por' => $viaje->usuarioRegistro?->name,
                'fecha' => $viaje->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('ControlCargas/Show', [
            'carga' => [
                'id' => $cargaMaterial->id,
                'nro' => $cargaMaterial->nro,
                'estado_carga' => $cargaMaterial->estado_carga,
                'fecha_apertura' => $cargaMaterial->fecha_apertura?->format('d/m/Y H:i'),
                'nombre_conductor' => $cargaMaterial->nombre_conductor,
                'telefono' => $cargaMaterial->telefono,
                'es_al_exterior' => $cargaMaterial->es_al_exterior,
                'pais' => $cargaMaterial->pais,
                'detalle' => $cargaMaterial->detalle,
                'observaciones' => $cargaMaterial->observaciones,
                'vehiculo_externo' => $cargaMaterial->vehiculoExterno ? [
                    'nro_placa' => $cargaMaterial->vehiculoExterno->nro_placa,
                    'propietario' => $cargaMaterial->vehiculoExterno->propietario,
                ] : null,
                'abierta_por' => $cargaMaterial->usuarioApertura?->name,
                'fecha_cierre' => $cargaMaterial->fecha_cierre?->format('d/m/Y H:i'),
                'cerrada_por' => $cargaMaterial->usuarioCierre?->name,
                'fecha_pago' => $cargaMaterial->fecha_pago?->format('d/m/Y H:i'),
                'monto_pago' => $cargaMaterial->monto_pago,
            ],
            'viajes' => $viajes,
            'materiales' => Material::orderBy('material')->get(['id', 'material']),
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Registra un viaje dentro de una carga ABIERTA. Cada viaje representa
     * una vuelta de carga del material al vehículo externo y exige una foto
     * de evidencia.
     */
    public function registrarViaje(ViajeRequest $request, CargaMaterial $cargaMaterial): RedirectResponse|JsonResponse
    {
        if ($cargaMaterial->estado_carga !== 'ABIERTA') {
            $mensaje = 'No se pueden registrar viajes: este flete ya está cerrado.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $mensaje], 422);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        $data = $request->validated();
        $data['foto'] = $request->file('foto')->store('control-cargas/viajes', 'public');

        // fecha_hora_carga es la fecha/hora actual del servidor, salvo un
        // registro offline sincronizado desde la API (ver ViajeRequest).
        if (! $request->boolean('is_offline')) {
            $data['fecha_hora_carga'] = now();
        }

        $cargaMaterial->viajes()->create($data);

        return redirect()->route('control-cargas.show', $cargaMaterial->id)
            ->with('success', 'Viaje registrado exitosamente.');
    }

    /**
     * Cierra una carga ABIERTA. Disponible para cualquier usuario que ya
     * puede gestionar la carga (mismo criterio que editar/registrar viajes,
     * ver assertPuedeGestionar()) — no requiere un permiso aparte.
     */
    public function cerrar(CargaMaterial $cargaMaterial): RedirectResponse
    {
        $this->assertPuedeGestionar($cargaMaterial);

        $cargaMaterial->update([
            'estado_carga' => 'CERRADA',
            'id_usuario_cierre' => auth()->id(),
            'fecha_cierre' => now(),
        ]);

        return redirect()->back()
            ->with('success', "Flete #{$cargaMaterial->nro} cerrado exitosamente.");
    }

    /**
     * Marca una carga CERRADA como PAGADA. Sólo disponible para quien tenga
     * el permiso control-cargas.marcar-pagado (jefe-area, administrador y
     * super-admin — nunca conductor ni técnico de mantenimiento, ver
     * UserSeeder::permisosControlCargasPago()). Monto pagado y observaciones
     * son ambos opcionales.
     */
    public function pagar(Request $request, CargaMaterial $cargaMaterial): RedirectResponse
    {
        if (! $request->user()->can('control-cargas.marcar-pagado')) {
            abort(403);
        }

        if ($cargaMaterial->estado_carga !== 'CERRADA') {
            return redirect()->back()
                ->with('error', 'Sólo se puede marcar como pagado un flete que ya esté cerrado.');
        }

        $datos = $request->validate([
            'monto_pago' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $datosActualizar = [
            'estado_carga' => 'PAGADA',
            'fecha_pago' => now(),
        ];

        if ($request->filled('monto_pago')) {
            $datosActualizar['monto_pago'] = $datos['monto_pago'];
        }

        if ($request->filled('observaciones')) {
            $datosActualizar['observaciones'] = $datos['observaciones'];
        }

        $cargaMaterial->update($datosActualizar);

        return redirect()->back()
            ->with('success', "Flete #{$cargaMaterial->nro} marcado como pagado.");
    }

    /**
     * Un conductor solo puede editar las cargas que él mismo abrió (mismo
     * criterio que index()); jefe-area/administrador pueden gestionar
     * cualquiera. Solo se pueden editar cargas ABIERTA.
     */
    private function assertPuedeGestionar(CargaMaterial $cargaMaterial): void
    {
        $user = request()->user();

        if ($user->hasRole('conductor') && ! $user->hasAnyRole(['jefe-area', 'administrador', 'super-admin'])
            && $cargaMaterial->id_usuario_apertura !== $user->id) {
            abort(403);
        }

        if ($cargaMaterial->estado_carga !== 'ABIERTA') {
            abort(403, 'No se puede editar un flete que ya está cerrado.');
        }
    }

    /**
     * Observaciones sólo la puede definir un jefe de área (o roles
     * superiores); un conductor no, aunque pueda gestionar el resto de la carga.
     */
    private function puedeEditarObservaciones(User $user): bool
    {
        return $user->hasAnyRole(['jefe-area', 'administrador', 'super-admin']);
    }
}
