<?php

namespace App\Actions\SolicitudMantenimiento;

use App\Models\SolicitudMantenimiento;

class UpdateSolicitudMantenimientoAction
{
    public function execute(SolicitudMantenimiento $solicitud, array $datos): SolicitudMantenimiento
    {
        $solicitud->update($datos);

        return $solicitud;
    }
}
