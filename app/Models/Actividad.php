<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'nombre_actividad',
    'nombre_normalizado',
    'usos',
    'ultimo_uso',
    'id_area',
    'unidad_medida',
    'estado_actividad'
])]

class Actividad extends Model
{
    //

    protected $table = 'actividad';
}
