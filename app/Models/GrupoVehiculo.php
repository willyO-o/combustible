<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable(['grupo_vehiculo', 'estado_grupo_vehiculo'])]

class GrupoVehiculo extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'grupo_vehiculo';

    public function tiposVehiculos()
    {
        return $this->hasMany(TipoVehiculo::class, 'id_grupo_vehiculo');
    }
}
