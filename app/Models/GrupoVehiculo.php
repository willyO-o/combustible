<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['grupo_vehiculo', 'estado_grupo_vehiculo'])]

class GrupoVehiculo extends Model
{
    //
    protected $table = 'grupo_vehiculo';

    public function tiposVehiculos()
    {
        return $this->hasMany(TipoVehiculo::class, 'id_grupo_vehiculo');
    }
}
