<?php

namespace App\Events;

use App\Models\CargaMaterial;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CargaMaterialRegistrada
{
    use Dispatchable, SerializesModels;

    public function __construct(public CargaMaterial $cargaMaterial)
    {
        //
    }
}
