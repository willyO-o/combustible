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
    'estado_asignacion',
])]
class VehiculoArea extends Pivot
{
    // La tabla tiene su propia PK autoincremental (id), a diferencia de un
    // pivot tradicional sin clave propia: Pivot::$incrementing es false por
    // defecto, así que hay que reactivarlo para que create()/fresh()/refresh()
    // recuperen correctamente el id insertado.
    public $incrementing = true;

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
