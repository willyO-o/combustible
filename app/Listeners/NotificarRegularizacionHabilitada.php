<?php

namespace App\Listeners;

use App\Events\RegularizacionHabilitada;
use App\Notifications\RegularizacionHabilitadaNotification;

class NotificarRegularizacionHabilitada
{
    /**
     * Avisa al usuario habilitado (campana del sistema + push en la app).
     */
    public function handle(RegularizacionHabilitada $event): void
    {
        $event->habilitacion->usuario?->notify(new RegularizacionHabilitadaNotification($event->habilitacion));
    }
}
