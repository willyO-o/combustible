<?php

namespace App\Listeners;

use App\Events\OrdenTrabajoCulminada;
use App\Notifications\OrdenTrabajoCulminadaNotification;
use Illuminate\Support\Facades\Notification;

class NotificarOrdenTrabajoCulminada
{
    public function __construct()
    {
        //
    }

    /**
     * Notifica al jefe de área/administrador que emitió la orden cuando el
     * técnico culmina su ejecución.
     */
    public function handle(OrdenTrabajoCulminada $event): void
    {
        $emisor = $event->orden->usuarioEmite;

        if (! $emisor) {
            return;
        }

        Notification::send($emisor, new OrdenTrabajoCulminadaNotification($event->orden));
    }
}
