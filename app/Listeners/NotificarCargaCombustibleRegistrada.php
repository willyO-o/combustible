<?php

namespace App\Listeners;

use App\Events\CargaCombustibleRegistrada;
use App\Notifications\CargaCombustibleAreaNotification;
use App\Notifications\CargaCombustibleRegistradaNotification;
use Illuminate\Support\Facades\Notification;

class NotificarCargaCombustibleRegistrada
{
    public function __construct()
    {
        //
    }

    /**
     * Avisa de la carga registrada a:
     *  - Quien emitió el vale que se usó ("tu vale"). Una carga sin vale
     *    (PREPAGO) no tiene emisor a quien confirmarle.
     *  - Los jefes del área del vehículo, con o sin vale, para que la revisen.
     *    Si el emisor del vale también es jefe del área recibe sólo el aviso
     *    de "tu vale", y quien registró la carga no se notifica a sí mismo.
     */
    public function handle(CargaCombustibleRegistrada $event): void
    {
        $carga = $event->carga;
        $emisorVale = $carga->vale?->user;

        if ($emisorVale) {
            Notification::send($emisorVale, new CargaCombustibleRegistradaNotification($carga));
        }

        $jefes = $carga->vehiculo?->usuariosEncargadosActivos()
            ->when($emisorVale, fn ($query, $emisor) => $query->whereKeyNot($emisor->id))
            ->when($carga->id_usuario, fn ($query, $idUsuario) => $query->whereKeyNot($idUsuario))
            ->get();

        if (! $jefes || $jefes->isEmpty()) {
            return;
        }

        Notification::send($jefes, new CargaCombustibleAreaNotification($carga));
    }
}
