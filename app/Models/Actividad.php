<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'nombre_actividad',
    'nombre_normalizado',
    'usos',
    'ultimo_uso',
    'id_area',
    'unidad_medida',
    'estado_actividad',
])]

class Actividad extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    //

    protected $table = 'actividad';
}
