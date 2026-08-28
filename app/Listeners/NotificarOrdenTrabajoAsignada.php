<?php

namespace App\Listeners;

use App\Events\OrdenTrabajoAsignada;
use App\Notifications\OrdenTrabajoAsignadaNotification;
use Illuminate\Support\Facades\Notification;

class NotificarOrdenTrabajoAsignada
{
    public function __construct()
    {
        //
    }

    /**
     * Notifica al técnico de mantenimiento responsable de la ejecución.
     */
    public function handle(OrdenTrabajoAsignada $event): void
    {
        $tecnico = $event->orden->usuarioEjecuta;

        if (! $tecnico) {
            return;
        }

        Notification::send($tecnico, new OrdenTrabajoAsignadaNotification($event->orden));
    }
}
