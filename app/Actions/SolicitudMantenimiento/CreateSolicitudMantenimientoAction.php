<?php

namespace App\Actions\SolicitudMantenimiento;

use App\Events\MantenimientoSolicitado;
use App\Models\SolicitudMantenimiento;
use App\Models\User;

class CreateSolicitudMantenimientoAction
{
    public function execute(array $datos, User $user): SolicitudMantenimiento
    {
        // Un conductor que además es jefe de área elige explícitamente el
        // vehículo (de sus áreas a cargo) y el conductor de la solicitud —
        // el rol jefe-area prevalece sobre conductor (a diferencia de
        // Operación Diaria, ver .ai/rules/operacion.md). Sólo un conductor
        // "puro" queda siempre atado a sí mismo, ignorando cualquier
        // id_conductor que llegue en el payload (evita que se pueda
        // suplantar a otro conductor).
        if ($user->esConductorPuro() && $user->persona?->conductor) {
            $datos['id_conductor'] = $user->id_persona;
        }

        if (empty($datos['is_offline'])) {
            $datos['fecha_solicitud'] = now();
        }

        $datos['id_usuario_registra'] = $user->id;
        $datos['estado'] = 'PENDIENTE';

        $solicitud = SolicitudMantenimiento::create($datos);

        MantenimientoSolicitado::dispatch($solicitud);

        return $solicitud;
    }
}
