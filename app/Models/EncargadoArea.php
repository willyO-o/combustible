<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'id_persona',
    'id_area',
    'tipo_encargo',
    'fecha_inicio',
    'fecha_reasignacion',
    'fecha_fin',
    'motivo',
    'estado_encargo',
])]

class EncargadoArea extends Pivot
{
    //
    protected $table = 'encargado_area';

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_reasignacion' => 'date',
            'fecha_fin' => 'date',
        ];
    }
}
