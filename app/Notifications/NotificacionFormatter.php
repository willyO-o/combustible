<?php

namespace App\Notifications;

/**
 * Única fuente de verdad para el título/descripción/ícono de cada tipo de
 * notificación de base de datos (columna `data.tipo`), usada por los 3
 * consumidores del mismo dato: HandleInertiaRequests::formatearNotificacion()
 * (dropdown del panel web), Api\V1\NotificacionController::formatear() (API)
 * y App\Channels\FcmChannel (contenido del push).
 *
 * Cada nuevo tipo de notificación debe añadir su propio caso aquí — un único
 * lugar en vez de repetirlo en cada consumidor.
 */
class NotificacionFormatter
{
    /**
     * @param  array<string, mixed>  $data  El campo `data` de la notificación
     *                                      de base de datos (o el toArray()
     *                                      de una Notification antes de enviarse).
     * @return array{titulo: string, descripcion: string, icono: string}
     */
    public static function formatear(array $data): array
    {
        $tipo = $data['tipo'] ?? 'general';

        [$titulo, $descripcion, $icono] = match ($tipo) {
            'observacion_operacion' => [
                'Observación en operación diaria',
                $data['observaciones'] ?? '',
                'ri-error-warning-line',
            ],
            'orden_trabajo_asignada' => [
                'Orden de trabajo asignada',
                'Se te asignó la orden N° '.($data['nro_orden'] ?? '').' para su ejecución.',
                'ri-tools-line',
            ],
            'orden_trabajo_culminada' => [
                'Orden de trabajo culminada',
                'El técnico culminó la orden N° '.($data['nro_orden'] ?? '').'.',
                'ri-checkbox-circle-line',
            ],
            'orden_trabajo_verificada' => [
                'Orden de trabajo verificada',
                'Tu orden N° '.($data['nro_orden'] ?? '').' fue verificada y cerrada.',
                'ri-shield-check-line',
            ],
            'vale_emitido' => [
                'Vale de combustible emitido',
                'Se emitió el vale N° '.($data['nro'] ?? '').' por '.($data['litros'] ?? '').' Lt.',
                'ri-file-list-3-line',
            ],
            'vale_por_vencer' => [
                'Vale por vencer',
                'El vale N° '.($data['nro'] ?? '').' vence pronto, aún no se usó.',
                'ri-alarm-warning-line',
            ],
            'carga_combustible_registrada' => [
                'Carga de combustible registrada',
                'Se registró la carga N° '.($data['nro'] ?? '').' ('.($data['litros'] ?? '').' Lt) de tu vale.',
                'ri-gas-station-line',
            ],
            'carga_combustible_area' => [
                'Carga de combustible en tu área',
                'Se registró la carga N° '.($data['nro'] ?? '').' ('.($data['litros'] ?? '').' Lt) del vehículo '.($data['placa'] ?? '').'. Revísala.',
                'ri-gas-station-line',
            ],
            'solicitud_mantenimiento_registrada' => [
                'Nueva solicitud de mantenimiento',
                'Se registró la solicitud N° '.($data['nro'] ?? '').' del vehículo '.($data['placa'] ?? '').'. Revísala.',
                'ri-tools-line',
            ],
            'carga_material_registrada' => [
                'Nuevo flete registrado',
                'Se abrió el flete N° '.($data['nro'] ?? '').'.',
                'ri-truck-line',
            ],
            default => [
                'Notificación',
                $data['mensaje'] ?? '',
                'ri-notification-line',
            ],
        };

        return [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'icono' => $icono,
        ];
    }
}
