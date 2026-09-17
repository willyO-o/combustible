<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'razon_social',
    'nit',
    'direccion',
    'telefono',
    'contacto',
    'estado_taller',
])]
class Taller extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'taller';

    public function ordenesMantenimiento()
    {
        return $this->hasMany(OrdenTrabajo::class, 'id_taller');
    }
}
