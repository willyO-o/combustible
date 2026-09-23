<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Models\CargaCombustible;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los jefes del área del vehículo de que se registró una carga de
 * combustible (con o sin vale) para que puedan revisarla. A quien emitió el
 * vale se le avisa con CargaCombustibleRegistradaNotification ("tu vale").
 */
class CargaCombustibleAreaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public CargaCombustible $carga,
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
            'tipo' => 'carga_combustible_area',
            'id_carga_combustible' => $this->carga->id,
            'nro' => $this->carga->nro,
            'id_vale' => $this->carga->id_vale,
            'id_vehiculo' => $this->carga->id_vehiculo,
            'placa' => $this->carga->vehiculo?->nro_placa,
            'litros' => $this->carga->litros,
            'url' => route('cargas.index'),
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
