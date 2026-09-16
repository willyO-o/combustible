<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\OrdenTrabajoCulminada;
use App\Http\Controllers\Controller;
use App\Http\Requests\DetalleMantenimientoRequest;
use App\Http\Requests\EjecucionOrdenTrabajoRequest;
use App\Models\DetalleMantenimiento;
use App\Models\OrdenTrabajo;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paso 3 del flujo de mantenimiento visto desde la app móvil: el técnico de
 * mantenimiento consulta las órdenes de trabajo que le fueron asignadas,
 * marca el inicio del trabajo, va registrando el detalle del trabajo
 * realizado (repuestos/insumos aplicados) ítem por ítem, y finalmente lo
 * marca como culminado con las lecturas finales del vehículo.
 *
 * El flujo de estados que maneja el técnico es:
 * PENDIENTE --(iniciar)--> EN_EJECUCION --(culminar)--> CULMINADO
 * (VERIFICADO y CANCELADO quedan para el emisor de la orden / panel web).
 * La misma "colección de estados" se expone en GET /parametros/colecciones.
 */
class OrdenTrabajoController extends Controller
{
    /**
     * Lista las órdenes de trabajo. Un técnico de mantenimiento sólo ve las
     * que tiene asignadas (id_usuario_ejecuta); jefe-area/administrador ven
     * todas. Mismo criterio de visibilidad que el módulo web.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OrdenTrabajo::with([
            'vehiculo:id,codigo,nro_placa,marca,modelo,tipo_medicion',
            'solicitudMantenimiento:id,nro_solicitud,gestion,descripcion_problema',
            'taller:id,razon_social',
            'usuarioEmite:id,name',
        ])->withCount('detalles');

        if ($this->esSoloTecnico($request->user())) {
            $query->where('id_usuario_ejecuta', $request->user()->id);
        }

        if ($request->filled('estado_orden')) {
            $query->where('estado_orden', $request->estado_orden);
        }

        if ($request->filled('id_vehiculo')) {
            $query->where('id_vehiculo', $request->id_vehiculo);
        }

        $ordenes = $query->orderBy('id', 'desc')->paginate($request->integer('per_page', 10));

        return response()->json($ordenes);
    }

    /**
     * Muestra el detalle de una orden de trabajo con sus ítems ya registrados.
     */
    public function show(Request $request, OrdenTrabajo $orden): JsonResponse
    {
        $this->assertPuedeGestionar($request->user(), $orden);

        $orden->load([
            'vehiculo',
            'conductor.persona',
            'solicitudMantenimiento',
            'taller',
            'usuarioEmite:id,name',
            'usuarioEjecuta:id,name',
            'detalles.repuesto:id,nombre_repuesto,codigo_repuesto,unidad_medida',
            'detalles.tipoMantenimiento:id,tipo_mantenimiento',
        ]);

        return response()->json([
            'data' => $orden,
        ]);
    }

    /**
     * Marca el inicio del trabajo: pasa la orden de PENDIENTE a EN_EJECUCION y
     * registra la fecha/hora de ejecución. A partir de aquí el técnico puede
     * ir agregando ítems al detalle.
     */
    public function iniciar(Request $request, OrdenTrabajo $orden): JsonResponse
    {
        $this->assertPuedeGestionar($request->user(), $orden);

        if ($orden->estado_orden !== 'PENDIENTE') {
            return response()->json([
                'message' => 'Sólo se puede iniciar una orden que está PENDIENTE.',
            ], 422);
        }

        $orden->update([
            'estado_orden' => 'EN_EJECUCION',
            'fecha_ejecucion' => $orden->fecha_ejecucion ?? now(),
        ]);

        return response()->json([
            'message' => 'Mantenimiento iniciado.',
            'data' => $orden->fresh(),
        ]);
    }

    /**
     * Registra un ítem del detalle de trabajo de la orden. El técnico llama a
     * este endpoint cada vez que completa una acción (se agregan de a uno, no
     * hace falta enviarlos todos juntos).
     *
     * El acceso ya queda restringido a las órdenes asignadas al técnico por
     * DetalleMantenimientoRequest::authorize().
     */
    public function storeDetalle(DetalleMantenimientoRequest $request, OrdenTrabajo $orden): JsonResponse
    {
        if (! $this->detalleEsModificable($orden)) {
            return response()->json([
                'message' => 'La orden ya no admite cambios en su detalle de trabajo (sólo mientras está PENDIENTE o EN_EJECUCION).',
            ], 422);
        }

        $detalle = $orden->detalles()->create($request->validated());
        $detalle->load([
            'repuesto:id,nombre_repuesto,codigo_repuesto,unidad_medida',
            'tipoMantenimiento:id,tipo_mantenimiento',
        ]);

        return response()->json([
            'message' => 'Detalle de mantenimiento registrado exitosamente.',
            'data' => $detalle,
        ], 201);
    }

    /**
     * Corrige un ítem del detalle ya registrado (p. ej. una cantidad o lectura
     * mal cargada), mientras la orden siga PENDIENTE o EN_EJECUCION. El acceso
     * ya queda restringido a las órdenes asignadas al técnico por
     * DetalleMantenimientoRequest::authorize().
     */
    public function updateDetalle(DetalleMantenimientoRequest $request, OrdenTrabajo $orden, DetalleMantenimiento $detalle): JsonResponse
    {
        abort_if($detalle->id_orden_trabajo !== $orden->id, 404);

        if (! $this->detalleEsModificable($orden)) {
            return response()->json([
                'message' => 'La orden ya no admite cambios en su detalle de trabajo (sólo mientras está PENDIENTE o EN_EJECUCION).',
            ], 422);
        }

        $detalle->update($request->validated());
        $detalle->load([
            'repuesto:id,nombre_repuesto,codigo_repuesto,unidad_medida',
            'tipoMantenimiento:id,tipo_mantenimiento',
        ]);

        return response()->json([
            'message' => 'Detalle de mantenimiento actualizado exitosamente.',
            'data' => $detalle,
        ]);
    }

    /**
     * Elimina un ítem del detalle que se registró por error (mientras la orden
     * siga PENDIENTE o EN_EJECUCION).
     */
    public function destroyDetalle(Request $request, OrdenTrabajo $orden, DetalleMantenimiento $detalle): JsonResponse
    {
        $this->assertPuedeGestionar($request->user(), $orden);

        abort_if($detalle->id_orden_trabajo !== $orden->id, 404);

        if (! $this->detalleEsModificable($orden)) {
            return response()->json([
                'message' => 'La orden ya no admite cambios en su detalle de trabajo (sólo mientras está PENDIENTE o EN_EJECUCION).',
            ], 422);
        }

        $detalle->delete();

        return response()->json([
            'message' => 'Detalle de mantenimiento eliminado exitosamente.',
        ]);
    }

    /**
     * Culmina la ejecución de la orden: registra las lecturas finales del
     * vehículo (km/horómetro según su tipo de medición) y una observación
     * opcional, pasa la orden a CULMINADO y congela el detalle de trabajo
     * (ya no admite más ítems ni ediciones).
     *
     * Exige que la orden esté EN_EJECUCION (debe haberse iniciado antes) y
     * tenga al menos un ítem de detalle registrado. El acceso ya queda
     * restringido a las órdenes asignadas al técnico por
     * EjecucionOrdenTrabajoRequest::authorize().
     */
    public function culminar(EjecucionOrdenTrabajoRequest $request, OrdenTrabajo $orden): JsonResponse
    {
        if ($orden->estado_orden !== 'EN_EJECUCION') {
            return response()->json([
                'message' => 'Sólo se puede culminar una orden que está EN_EJECUCION (primero debe iniciarla).',
            ], 422);
        }

        if ($orden->detalles()->doesntExist()) {
            return response()->json([
                'message' => 'Debe registrar al menos un ítem del detalle antes de culminar la orden.',
            ], 422);
        }

        $orden->update([
            ...$request->validated(),
            'fecha_ejecucion' => $orden->fecha_ejecucion ?? now(),
            'fecha_culminacion' => now(),
            'estado_orden' => 'CULMINADO',
        ]);

        OrdenTrabajoCulminada::dispatch($orden);

        $orden->load([
            'vehiculo',
            'detalles.repuesto:id,nombre_repuesto,codigo_repuesto,unidad_medida',
            'detalles.tipoMantenimiento:id,tipo_mantenimiento',
        ]);

        return response()->json([
            'message' => 'Mantenimiento culminado exitosamente.',
            'data' => $orden,
        ]);
    }

    /**
     * Un jefe de área/administrador puede gestionar cualquier orden; un técnico
     * de mantenimiento sólo las que tiene asignadas.
     */
    private function assertPuedeGestionar(User $user, OrdenTrabajo $orden): void
    {
        if ($user->hasAnyRole(['super-admin', 'administrador', 'jefe-area'])) {
            return;
        }

        if ($user->hasRole('tecnico-mantenimiento') && $orden->id_usuario_ejecuta === $user->id) {
            return;
        }

        abort(403, 'No tiene acceso a esta orden de trabajo.');
    }

    /**
     * El detalle de trabajo sólo se puede modificar mientras la orden no esté
     * culminada/verificada/cancelada (mismo criterio que el módulo web).
     */
    private function detalleEsModificable(OrdenTrabajo $orden): bool
    {
        return in_array($orden->estado_orden, ['PENDIENTE', 'EN_EJECUCION'], true);
    }

    /**
     * Un técnico de mantenimiento sin ningún rol de gestión sólo puede operar
     * sobre sus propias órdenes asignadas.
     */
    private function esSoloTecnico(User $user): bool
    {
        return $user->hasRole('tecnico-mantenimiento')
            && ! $user->hasAnyRole(['super-admin', 'administrador', 'jefe-area']);
    }
}
