<?php

namespace App\Listeners;

use App\Events\CargaMaterialRegistrada;
use App\Models\User;
use App\Notifications\CargaMaterialRegistradaNotification;
use Illuminate\Support\Facades\Notification;

class NotificarCargaMaterialRegistrada
{
    public function __construct()
    {
        //
    }

    /**
     * Notifica a los supervisores del módulo (jefe-area/administrador, que
     * ven todas las cargas de material sin restricción por área, ver
     * .ai/rules/models-models.md) cuando se abre un flete nuevo.
     *
     * Guard 'web' explícito: bajo auth:api el guard por defecto pasa a ser
     * 'api' (ver .ai/rules/v1.md "chequeo de permisos bajo auth:api"), y
     * todos los roles del proyecto están definidos con guard 'web'.
     */
    public function handle(CargaMaterialRegistrada $event): void
    {
        $supervisores = User::role(['jefe-area', 'administrador'], 'web')
            ->where('estado_usuario', 'ACTIVO')
            ->get();

        Notification::send($supervisores, new CargaMaterialRegistradaNotification($event->cargaMaterial));
    }
}
