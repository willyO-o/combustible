<?php

namespace App\Listeners;

use App\Events\CargaCombustibleRegistrada;
use App\Notifications\CargaCombustibleRegistradaNotification;
use Illuminate\Support\Facades\Notification;

class NotificarCargaCombustibleRegistrada
{
    public function __construct()
    {
        //
    }

    /**
     * Notifica a quien emitió el vale que la carga que lo usó ya fue
     * registrada. Una carga sin vale (PREPAGO) no tiene a quién confirmarle,
     * así que no genera notificación.
     */
    public function handle(CargaCombustibleRegistrada $event): void
    {
        $emisorVale = $event->carga->vale?->user;

        if (! $emisorVale) {
            return;
        }

        Notification::send($emisorVale, new CargaCombustibleRegistradaNotification($event->carga));
    }
}
