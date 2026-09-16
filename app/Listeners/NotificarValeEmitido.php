<?php

namespace App\Listeners;

use App\Events\ValeEmitido;
use App\Notifications\ValeEmitidoNotification;
use Illuminate\Support\Facades\Notification;

class NotificarValeEmitido
{
    public function __construct()
    {
        //
    }

    /**
     * Notifica al conductor asignado al vale, si tiene cuenta de usuario
     * propia (no todo conductor la tiene).
     */
    public function handle(ValeEmitido $event): void
    {
        $usuario = $event->vale->conductor?->user;

        if (! $usuario) {
            return;
        }

        Notification::send($usuario, new ValeEmitidoNotification($event->vale));
    }
}
