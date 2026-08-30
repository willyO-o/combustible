<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tipo_mantenimiento',
    'estado_tipo_mantenimiento',
    'tipo_valor',
    'ambito',
    'unidad_medida',
])]
class TipoMantenimiento extends Model
{
    protected $table = 'tipo_mantenimiento';

    // Relaciones
    public function mantenimientos()
    {
        return $this->hasMany(Mantenimiento::class, 'id_tipo_mantenimiento');
    }

    public function detallesMantenimiento()
    {
        return $this->hasMany(DetalleMantenimiento::class, 'id_tipo_mantenimiento');
    }
}
