<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Models\HabilitacionRegularizacion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RegularizacionHabilitadaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public HabilitacionRegularizacion $habilitacion,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /**
     * El texto lo arma NotificacionFormatter (tipo regularizacion_habilitada).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'regularizacion_habilitada',
            'id_habilitacion' => $this->habilitacion->id,
            'desde' => $this->habilitacion->fecha_desde->format('d/m/Y'),
            'hasta' => $this->habilitacion->fecha_hasta->format('d/m/Y'),
            'vigente_hasta' => $this->habilitacion->vigente_hasta->format('d/m/Y H:i'),
            'url' => route('operacion-diaria.create'),
        ];
    }

    /**
     * Push FCM (ver App\Channels\FcmChannel).
     *
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
