<?php

namespace App\Actions\OrdenTrabajo;

use App\Events\OrdenTrabajoAsignada;
use App\Models\OrdenTrabajo;
use App\Models\SolicitudMantenimiento;
use Illuminate\Support\Facades\DB;

/**
 * Emite una orden de trabajo (Paso 2 del flujo de mantenimiento). Compartida
 * por el módulo web (OrdenTrabajoController::store) y la API móvil
 * (Api\V1\SolicitudMantenimientoController::emitirOrden).
 */
class CreateOrdenTrabajoAction
{
    /**
     * Toca 2 tablas (solicitud_mantenimiento + orden_trabajo), por eso corre
     * dentro de una transacción. La validación (disponibilidad de la
     * solicitud, técnico, etc.) ya la hizo el Form Request del llamador.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): OrdenTrabajo
    {
        $orden = DB::transaction(function () use ($data) {
            $huboSolicitudOrigen = ! empty($data['id_solicitud_mantenimiento']);

            if ($huboSolicitudOrigen) {
                // Si la orden nace de una solicitud, el vehículo/conductor y la
                // clasificación del mantenimiento se toman siempre de la
                // solicitud de origen: se fuerzan aquí para que no puedan
                // alterarse manipulando el formulario (que ya los muestra
                // bloqueados como información).
                $solicitud = SolicitudMantenimiento::findOrFail($data['id_solicitud_mantenimiento']);
                $data['id_vehiculo'] = $solicitud->id_vehiculo;
                $data['id_conductor'] = $solicitud->id_conductor;
                $data['tipo_mantenimiento'] = $solicitud->tipo_mantenimiento;
                $data['kilometraje_actual'] = $solicitud->kilometraje_actual;
                $data['horometro_actual'] = $solicitud->horometro_actual;
            } else {
                // Sin solicitud de origen: se genera una automáticamente, ya
                // APROBADA (nace junto con una orden ya emitida), con los
                // mismos datos del formulario — el reporte de mantenimiento
                // requiere que toda orden quede vinculada a una solicitud.
                $solicitud = SolicitudMantenimiento::create([
                    'id_vehiculo' => $data['id_vehiculo'],
                    'id_conductor' => $data['id_conductor'] ?? null,
                    'tipo_mantenimiento' => $data['tipo_mantenimiento'],
                    'descripcion_problema' => ($data['nota_emisor'] ?? null)
                        ?: 'Generado automáticamente al emitir la orden de trabajo, sin solicitud de origen.',
                    'kilometraje_actual' => $data['kilometraje_actual'] ?? null,
                    'horometro_actual' => $data['horometro_actual'] ?? null,
                    'fecha_solicitud' => now(),
                    'estado' => 'APROBADA',
                ]);
                $data['id_solicitud_mantenimiento'] = $solicitud->id;
            }

            $orden = OrdenTrabajo::create($data);

            // Marcar la solicitud origen como aprobada (la recién generada ya
            // nace así; sólo aplica cuando venía de una solicitud existente).
            if ($huboSolicitudOrigen) {
                $solicitud->update(['estado' => 'APROBADA']);
            }

            return $orden;
        });

        OrdenTrabajoAsignada::dispatch($orden);

        return $orden;
    }
}
