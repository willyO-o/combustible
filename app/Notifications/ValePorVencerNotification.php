<?php

namespace App\Notifications;

use App\Channels\FcmChannel;
use App\Models\Vale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ValePorVencerNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Vale $vale,
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
            'tipo' => 'vale_por_vencer',
            'id_vale' => $this->vale->id,
            'nro' => $this->vale->nro,
            // absolute=true: este aviso sólo se dispara mientras la fecha de
            // vencimiento sigue en el futuro (ver NotificarValesPorVencerCommand).
            'dias_restantes' => now()->diffInDays($this->vale->fecha_vencimiento),
            'fecha_vencimiento' => $this->vale->fecha_vencimiento->toIso8601String(),
            'url' => route('vales.index'),
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
