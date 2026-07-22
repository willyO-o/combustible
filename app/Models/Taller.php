<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'razon_social',
    'nit',
    'direccion',
    'telefono',
    'contacto',
    'estado_taller',
])]
class Taller extends Model
{
    protected $table = 'taller';

    public function ordenesMantenimiento()
    {
        return $this->hasMany(PlanMantenimiento::class, 'id_taller');
    }
}
