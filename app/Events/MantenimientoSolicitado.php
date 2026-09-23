<?php

namespace App\Events;

use App\Models\SolicitudMantenimiento;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MantenimientoSolicitado
{
    use Dispatchable, SerializesModels;

    public function __construct(public SolicitudMantenimiento $solicitud)
    {
        //
    }
}
