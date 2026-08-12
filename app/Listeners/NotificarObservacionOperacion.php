<?php

namespace App\Listeners;

use App\Events\ObservacionOperacionEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\ObservacionOperacionNotification;
use Illuminate\Support\Facades\Notification;


class NotificarObservacionOperacion
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ObservacionOperacionEvent $event): void
    {
        //
        $operacion = $event->operacion;
        $area = $operacion->area;
        $encargados = $area->encargadosUserActivos()->get();


        Notification::send($encargados, new ObservacionOperacionNotification($operacion));

    }
}
