<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Models\SolicitudMantenimiento;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los jefes del área del vehículo de que se registró una nueva
 * solicitud de mantenimiento, para que puedan revisarla y emitir la orden.
 */
class SolicitudMantenimientoRegistradaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SolicitudMantenimiento $solicitud,
    ) {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'solicitud_mantenimiento_registrada',
            'id_solicitud' => $this->solicitud->id,
            'nro' => $this->solicitud->nro,
            'id_vehiculo' => $this->solicitud->id_vehiculo,
            'placa' => $this->solicitud->vehiculo?->nro_placa,
            'tipo_mantenimiento' => $this->solicitud->tipo_mantenimiento,
            'url' => route('mantenimiento.solicitudes.show', $this->solicitud->id),
        ];
    }

    /**
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);
        $formato = NotificacionFormatter::formatear($data);

        return [
            'title' => $formato['titulo'],
            'body' => $formato['descripcion'],
            'data' => $data,
        ];
    }
}
