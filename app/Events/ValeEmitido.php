<?php

namespace App\Events;

use App\Models\Vale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ValeEmitido
{
    use Dispatchable, SerializesModels;

    public function __construct(public Vale $vale)
    {
        //
    }
}
