<?php

namespace App\Http\Controllers;

use App\Http\Requests\CargaMaterialRequest;
use App\Http\Requests\ViajeRequest;
use App\Models\CargaMaterial;
use App\Models\Material;
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

        $cargas = $query->orderBy('fecha_apertura', 'desc')->paginate(10)->withQueryString();

        $cargas->through(fn (CargaMaterial $carga) => [
            'id' => $carga->id,
            'nro_carga' => $carga->nro_carga,
            'estado_carga' => $carga->estado_carga,
            'fecha_apertura' => $carga->fecha_apertura?->format('d/m/Y H:i'),
            'nombre_conductor' => $carga->nombre_conductor,
            'telefono' => $carga->telefono,
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
        ]);
    }

    public function store(CargaMaterialRequest $request): RedirectResponse
    {
        $carga = CargaMaterial::create($request->validated());

        return redirect()->route('control-cargas.show', $carga->id)
            ->with('success', "Carga #{$carga->nro_carga} registrada exitosamente. Ya puedes registrar viajes.");
    }

    public function edit(CargaMaterial $cargaMaterial): Response
    {
        $this->assertPuedeGestionar($cargaMaterial);

        $cargaMaterial->load('vehiculoExterno');

        return Inertia::render('ControlCargas/Form', [
            'carga' => [
                'id' => $cargaMaterial->id,
                'nro_carga' => $cargaMaterial->nro_carga,
                'estado_carga' => $cargaMaterial->estado_carga,
                'nombre_conductor' => $cargaMaterial->nombre_conductor,
                'telefono' => $cargaMaterial->telefono,
                'observaciones' => $cargaMaterial->observaciones,
                'vehiculo_externo' => $cargaMaterial->vehiculoExterno ? [
                    'nro_placa' => $cargaMaterial->vehiculoExterno->nro_placa,
                    'propietario' => $cargaMaterial->vehiculoExterno->propietario,
                ] : null,
            ],
        ]);
    }

    public function update(CargaMaterialRequest $request, CargaMaterial $cargaMaterial): RedirectResponse
    {
        $this->assertPuedeGestionar($cargaMaterial);

        // El material y el vehículo externo no se pueden cambiar una vez
        // abierta la carga (ver CargaMaterialRequest::rules()): solo se
        // corrigen los datos del conductor externo y las observaciones.
        $cargaMaterial->update($request->safe()->only(['nombre_conductor', 'telefono', 'observaciones']));

        return redirect()->route('control-cargas.index')
            ->with('success', "Carga #{$cargaMaterial->nro_carga} actualizada exitosamente.");
    }

    public function show(CargaMaterial $cargaMaterial): Response
    {
        $cargaMaterial->load(['vehiculoExterno', 'usuarioApertura']);

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
                'nro_carga' => $cargaMaterial->nro_carga,
                'estado_carga' => $cargaMaterial->estado_carga,
                'fecha_apertura' => $cargaMaterial->fecha_apertura?->format('d/m/Y H:i'),
                'nombre_conductor' => $cargaMaterial->nombre_conductor,
                'telefono' => $cargaMaterial->telefono,
                'observaciones' => $cargaMaterial->observaciones,
                'vehiculo_externo' => $cargaMaterial->vehiculoExterno ? [
                    'nro_placa' => $cargaMaterial->vehiculoExterno->nro_placa,
                    'propietario' => $cargaMaterial->vehiculoExterno->propietario,
                ] : null,
                'abierta_por' => $cargaMaterial->usuarioApertura?->name,
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
            $mensaje = 'No se pueden registrar viajes: esta carga ya está cerrada.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $mensaje], 422);
            }

            return redirect()->back()->with('error', $mensaje);
        }

        $data = $request->validated();
        $data['foto'] = $request->file('foto')->store('control-cargas/viajes', 'public');

        $cargaMaterial->viajes()->create($data);

        return redirect()->route('control-cargas.show', $cargaMaterial->id)
            ->with('success', 'Viaje registrado exitosamente.');
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
            abort(403, 'No se puede editar una carga que ya está cerrada.');
        }
    }
}
