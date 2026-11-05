<?php

namespace App\Events;

use App\Models\HabilitacionRegularizacion;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un encargado habilitó a un usuario a regularizar operaciones diarias de
 * días pasados (ver HabilitarRegularizacionAction).
 */
class RegularizacionHabilitada
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public HabilitacionRegularizacion $habilitacion,
    ) {}
}
