<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tipo_vehiculo',
    'estado_tipo_vehiculo',
    'id_grupo_vehiculo',
])]
class TipoVehiculo extends Model
{
    use HasFactory;

    protected $table = 'tipo_vehiculo';

    public function grupoVehiculo()
    {
        return $this->belongsTo(GrupoVehiculo::class, 'id_grupo_vehiculo');
    }

    // Relaciones
    public function vehiculos()
    {
        return $this->hasMany(Vehiculo::class, 'id_tipo_vehiculo');
    }
}
