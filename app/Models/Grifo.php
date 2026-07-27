<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
#[Fillable([
    'razon_social',
    'nit',
    'direccion',
    'ciudad',
    'telefono',
    'estado_grifo',
    'es_principal',
])]


class Grifo extends Model
{
    use SoftDeletes;

    protected $table = 'grifo';


     protected function casts()
    {
        return [
            'es_principal' => 'boolean',
        ];
    }

    // Relaciones
    public function vales()
    {
        return $this->hasMany(Vale::class, 'id_grifo');
    }

    public function cargasCombustible()
    {
        return $this->hasMany(CargaCombustible::class, 'id_grifo');
    }
}
