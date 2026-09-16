<?php

namespace App\Notifications;

use App\Models\CargaCombustible;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CargaCombustibleRegistradaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public CargaCombustible $carga,
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
        return ['database'];
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
            'tipo' => 'carga_combustible_registrada',
            'id_carga_combustible' => $this->carga->id,
            'nro' => $this->carga->nro,
            'id_vale' => $this->carga->id_vale,
            'litros' => $this->carga->litros,
            'url' => route('cargas.index'),
        ];
    }
}
