<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
#[Fillable([
    'tipo_vehiculo',
    'estado_tipo_vehiculo',
])]
class TipoVehiculo extends Model
{
    protected $table = 'tipo_vehiculo';





    // Relaciones
    public function vehiculos()
    {
        return $this->hasMany(Vehiculo::class, 'id_tipo_vehiculo');
    }
}
