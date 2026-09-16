<?php

namespace App\Events;

use App\Models\CargaCombustible;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CargaCombustibleRegistrada
{
    use Dispatchable, SerializesModels;

    public function __construct(public CargaCombustible $carga)
    {
        //
    }
}
