<?php

namespace App\Listeners;

use App\Events\OrdenTrabajoVerificada;
use App\Notifications\OrdenTrabajoVerificadaNotification;
use Illuminate\Support\Facades\Notification;

class NotificarOrdenTrabajoVerificada
{
    public function __construct()
    {
        //
    }

    /**
     * Notifica al técnico responsable de la ejecución cuando el emisor
     * verifica y cierra definitivamente la orden.
     */
    public function handle(OrdenTrabajoVerificada $event): void
    {
        $tecnico = $event->orden->usuarioEjecuta;

        if (! $tecnico) {
            return;
        }

        Notification::send($tecnico, new OrdenTrabajoVerificadaNotification($event->orden));
    }
}
