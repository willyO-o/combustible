<?php

namespace App\Actions\SolicitudMantenimiento;

use App\Models\SolicitudMantenimiento;
use App\Models\User;

class CreateSolicitudMantenimientoAction
{
    public function execute(array $datos, User $user): SolicitudMantenimiento
    {
        if (empty($datos['id_conductor']) && $user->hasRole('conductor')) {
            $datos['id_conductor'] = $user->id_persona;
        }

        if(empty($datos['is_offline'])) {
            $datos['fecha_solicitud'] = now();
        }

        $datos['id_usuario_registra'] = $user->id;
        $datos['estado'] = 'PENDIENTE';

        return SolicitudMantenimiento::create($datos);
    }
}
