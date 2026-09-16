<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Models\OrdenTrabajo;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrdenTrabajoVerificadaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public OrdenTrabajo $orden,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'orden_trabajo_verificada',
            'id_orden_trabajo' => $this->orden->id,
            'nro_orden' => $this->orden->nro,
            'id_vehiculo' => $this->orden->id_vehiculo,
            'url' => route('mantenimiento.ordenes.show', $this->orden->id),
        ];
    }

    /**
     * Get the FCM representation of the notification (ver App\Channels\FcmChannel).
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
