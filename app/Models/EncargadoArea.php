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
    // La tabla tiene su propia PK autoincremental (id), a diferencia de un
    // pivot tradicional sin clave propia: Pivot::$incrementing es false por
    // defecto, así que hay que reactivarlo para que create()/fresh()/refresh()
    // recuperen correctamente el id insertado.
    public $incrementing = true;

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
