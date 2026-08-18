<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;


#[Fillable([
    'id_tipo_vehiculo',
    'id_tipo_mantenimiento',
    'tipo_medicion',
    'frecuencia',
    'estado',
])]

class IntervaloMantenimientoTipo extends Model
{
    //

    protected $table = 'intervalo_mantenimiento_tipo';
}
