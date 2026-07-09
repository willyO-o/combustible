<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
#[Fillable([
    'tipo_combustible',
    'estado_tipo_combustible',
])]
class TipoCombustible extends Model
{
    protected $table = 'tipo_combustible';


    // Relaciones
    public function vehiculos()
    {
        return $this->hasMany(Vehiculo::class, 'id_tipo_combustible');
    }
}
