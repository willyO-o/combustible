<?php

namespace App\Events;

use App\Models\OrdenTrabajo;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrdenTrabajoVerificada
{
    use Dispatchable, SerializesModels;

    public function __construct(public OrdenTrabajo $orden)
    {
        //
    }
}
