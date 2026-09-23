<?php

namespace App\Listeners;

use App\Events\MantenimientoSolicitado;
use App\Notifications\SolicitudMantenimientoRegistradaNotification;
use Illuminate\Support\Facades\Notification;

class NotificarMantenimientoSolicitado
{
    public function __construct()
    {
        //
    }

    /**
     * Notifica a los jefes del área (o áreas) donde está el vehículo de la
     * solicitud. Si quien la registró es uno de ellos no se le avisa a sí mismo.
     */
    public function handle(MantenimientoSolicitado $event): void
    {
        $solicitud = $event->solicitud;

        $jefes = $solicitud->vehiculo?->usuariosEncargadosActivos()
            ->when($solicitud->id_usuario_registra, fn ($query, $idUsuario) => $query->whereKeyNot($idUsuario))
            ->get();

        if (! $jefes || $jefes->isEmpty()) {
            return;
        }

        Notification::send($jefes, new SolicitudMantenimientoRegistradaNotification($solicitud));
    }
}
