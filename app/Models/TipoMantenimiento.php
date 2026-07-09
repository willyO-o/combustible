<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'tipo_mantenimiento',
    'estado_tipo_mantenimiento',
])]
class TipoMantenimiento extends Model
{
    protected $table = 'tipo_mantenimiento';


    // Relaciones
    public function mantenimientos()
    {
        return $this->hasMany(Mantenimiento::class, 'id_tipo_mantenimiento');
    }
}
