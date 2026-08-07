<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'id_vehiculo',
    'id_area',
    'motivo_asignacion',
    'fecha_asignacion',
    'fecha_reasignacion',
    'fecha_culminacion',
    'estado_asignacion'
])]
class VehiculoArea extends Pivot
{
    //

    protected $table = 'vehiculo_area';

    protected function casts(): array
    {
        return [
            'fecha_asignacion' => 'date:d/m/Y',
            'fecha_reasignacion' => 'date:d/m/Y',
            'fecha_culminacion' => 'date:d/m/Y',
        ];
    }
}
