<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\CargaMaterialRegistrada;
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
     * Abre una nueva carga. La mayoría de los datos (usuario, estado,
     * número/gestión) se asignan automáticamente desde el usuario autenticado
     * (ver CargaMaterial::boot()), igual que en el módulo web.
     *
     * Opcionalmente, si el cliente envía el bloque "viaje" (id_material,
     * foto, origen, destino y detalle opcional), se registra también el
     * primer viaje de la carga en la misma petición.
     */
    public function store(CargaMaterialRequest $request): JsonResponse
    {
        try {
            $carga = DB::transaction(function () use ($request) {
                $datos = $request->safe()->except('viaje');

                // Observaciones sólo puede definirla un jefe de área (o roles
                // superiores); si la envía un conductor, se ignora en silencio.
                if (! $request->user()->hasAnyRole(['jefe-area', 'administrador', 'super-admin'])) {
                    unset($datos['observaciones']);
                }

                // fecha_apertura es la fecha/hora actual del servidor, salvo un
                // registro offline sincronizado desde la app (ver CargaMaterialRequest).
                if (! $request->boolean('is_offline')) {
                    $datos['fecha_apertura'] = now();
                }

                $carga = CargaMaterial::create($datos);

                if ($request->has('viaje')) {
                    $datosViaje = $request->validated('viaje');
                    $datosViaje['foto'] = $request->file('viaje.foto')->store('control-cargas/viajes', 'public');

                    // El primer viaje comparte el mismo is_offline que la carga:
                    // toda la petición corresponde al mismo momento offline.
                    if (! $request->boolean('is_offline')) {
                        $datosViaje['fecha_hora_carga'] = now();
                    }

                    $carga->viajes()->create($datosViaje);
                }

                return $carga;
            });

            CargaMaterialRegistrada::dispatch($carga);

            $carga->load(['vehiculoExterno', 'viajes.material']);

            return response()->json([
                'message' => "Flete #{$carga->nro} registrado exitosamente.",
                'data' => $carga,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el flete.',
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
     * Cierra un flete ABIERTA. Mismo criterio de acceso que registrar viajes
     * (un conductor sólo el flete que él mismo abrió; jefe-area/administrador/
     * super-admin cualquiera): no requiere un permiso aparte. Registra
     * id_usuario_cierre y fecha_cierre.
     */
    public function cerrar(Request $request, CargaMaterial $cargaMaterial): JsonResponse
    {
        $user = $request->user();

        if ($user->hasRole('conductor') && ! $user->hasAnyRole(['jefe-area', 'administrador', 'super-admin'])
            && $cargaMaterial->id_usuario_apertura !== $user->id) {
            return response()->json([
                'message' => 'No tienes permiso para gestionar este flete.',
            ], 403);
        }

        if ($cargaMaterial->estado_carga !== 'ABIERTA') {
            return response()->json([
                'message' => 'Sólo se puede cerrar un flete que esté abierto.',
            ], 422);
        }

        $cargaMaterial->update([
            'estado_carga' => 'CERRADA',
            'id_usuario_cierre' => $user->id,
            'fecha_cierre' => now(),
        ]);

        $cargaMaterial->load(['vehiculoExterno', 'usuarioApertura', 'usuarioCierre', 'viajes.material']);

        return response()->json([
            'message' => "Flete #{$cargaMaterial->nro} cerrado exitosamente.",
            'data' => $cargaMaterial,
        ]);
    }

    /**
     * Marca un flete CERRADA como PAGADA. Exige el permiso
     * `control-cargas.marcar-pagado` (jefe-area/administrador/super-admin;
     * nunca conductor ni técnico de mantenimiento). `monto_pago` y
     * `observaciones` son opcionales: si se omiten, no se sobrescribe el
     * valor ya guardado.
     */
    public function pagar(Request $request, CargaMaterial $cargaMaterial): JsonResponse
    {
        $user = $request->user();

        // El permiso vive en el guard web (igual que los roles); tras
        // autenticar con `auth:api` el guard por defecto pasa a ser `api`, así
        // que se consulta el guard explícitamente. super-admin lo tiene por el
        // bypass global de AppServiceProvider (Gate::before), no por permiso.
        $puedeMarcarPagado = $user->hasRole('super-admin')
            || $user->hasPermissionTo('control-cargas.marcar-pagado', 'web');

        if (! $puedeMarcarPagado) {
            return response()->json([
                'message' => 'No tienes permiso para marcar fletes como pagados.',
            ], 403);
        }

        if ($cargaMaterial->estado_carga !== 'CERRADA') {
            return response()->json([
                'message' => 'Sólo se puede marcar como pagado un flete que ya esté cerrado.',
            ], 422);
        }

        $datos = $request->validate([
            'monto_pago' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $cambios = [
            'estado_carga' => 'PAGADA',
            'fecha_pago' => now(),
        ];

        if ($request->filled('monto_pago')) {
            $cambios['monto_pago'] = $datos['monto_pago'];
        }

        if ($request->filled('observaciones')) {
            $cambios['observaciones'] = $datos['observaciones'];
        }

        $cargaMaterial->update($cambios);

        $cargaMaterial->load(['vehiculoExterno', 'usuarioApertura', 'usuarioCierre', 'viajes.material']);

        return response()->json([
            'message' => "Flete #{$cargaMaterial->nro} marcado como pagado.",
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
                'message' => 'No se pueden registrar viajes: este flete ya está cerrado.',
            ], 422);
        }

        try {
            $data = $request->validated();
            $data['foto'] = $request->file('foto')->store('control-cargas/viajes', 'public');

            // fecha_hora_carga es la fecha/hora actual del servidor, salvo un
            // registro offline sincronizado desde la API (ver ViajeRequest).
            if (! $request->boolean('is_offline')) {
                $data['fecha_hora_carga'] = now();
            }

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
