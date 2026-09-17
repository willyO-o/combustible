<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'nro_placa',
    'propietario',
])]
class VehiculoExterno extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'vehiculo_externo';

    // La tabla `vehiculo_externo` no tiene columnas created_at/updated_at.
    public $timestamps = false;
}
