<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'nombre_repuesto',
    'codigo_repuesto',
    'descripcion_repuesto',
    'unidad_medida',
    'stock_actual',
    'estado_repuesto',
])]
class Repuesto extends Model
{
    protected $table = 'repuesto';

    public function mantenimientosRepuesto()
    {
        return $this->hasMany(MantenimientoRepuesto::class, 'id_repuesto');
    }
}
