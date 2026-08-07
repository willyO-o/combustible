<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'nombre_area',
    'descripcion_area',
    'estado_area',
])]
class Area extends Model
{
    //
    protected $table = 'area';
}
