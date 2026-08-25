<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CargaMaterialRequest;
use App\Http\Requests\ViajeRequest;
use App\Models\CargaMaterial;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CargaMaterialController extends Controller
{
    /**
     * Lista las cargas de material. Mismo criterio de visibilidad que el
     * módulo web (CargaMaterialController::index()): un conductor sólo ve
     * las cargas que él mismo abrió; jefe-area/administrador ven todas.
     */
    public function index(Request $request): JsonResponse
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

        $cargas = $query->orderBy('fecha_apertura', 'desc')->paginate($request->input('per_page', 10));

        return response()->json($cargas);
    }

    /**
     * Abre una nueva carga. La mayoría de los datos (usuario, fecha de
     * apertura, estado, número/gestión) se asignan automáticamente desde el
     * usuario autenticado (ver CargaMaterial::boot()), igual que en el
     * módulo web.
     *
     * Opcionalmente, si el cliente envía el bloque "viaje" (id_material,
     * foto, origen, destino y detalle opcional), se registra también el
     * primer viaje de la carga en la misma petición.
     */
    public function store(CargaMaterialRequest $request): JsonResponse
    {
        try {
            $carga = DB::transaction(function () use ($request) {
                $carga = CargaMaterial::create($request->safe()->except('viaje'));

                if ($request->has('viaje')) {
                    $datosViaje = $request->validated('viaje');
                    $datosViaje['foto'] = $request->file('viaje.foto')->store('control-cargas/viajes', 'public');

                    $carga->viajes()->create($datosViaje);
                }

                return $carga;
            });

            $carga->load(['vehiculoExterno', 'viajes.material']);

            return response()->json([
                'message' => "Carga #{$carga->nro} registrada exitosamente.",
                'data' => $carga,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la carga.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Muestra el detalle de una carga con sus viajes registrados.
     */
    public function show(CargaMaterial $cargaMaterial): JsonResponse
    {
        $cargaMaterial->load([
            'vehiculoExterno',
            'usuarioApertura',
            'viajes.material',
            'viajes.usuarioRegistro',
        ]);

        return response()->json([
            'data' => $cargaMaterial,
        ]);
    }

    /**
     * Registra un viaje separado dentro de una carga ya abierta (mismo
     * comportamiento que CargaMaterialController::registrarViaje() en el
     * módulo web): cada viaje exige su propia foto de evidencia.
     */
    public function registrarViaje(ViajeRequest $request, CargaMaterial $cargaMaterial): JsonResponse
    {
        if ($cargaMaterial->estado_carga !== 'ABIERTA') {
            return response()->json([
                'message' => 'No se pueden registrar viajes: esta carga ya está cerrada.',
            ], 422);
        }

        try {
            $data = $request->validated();
            $data['foto'] = $request->file('foto')->store('control-cargas/viajes', 'public');

            $viaje = $cargaMaterial->viajes()->create($data);
            $viaje->load('material');

            return response()->json([
                'message' => 'Viaje registrado exitosamente.',
                'data' => $viaje,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el viaje.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
