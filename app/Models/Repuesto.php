<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'nombre_repuesto',
    'codigo_repuesto',
    'descripcion_repuesto',
    'unidad_medida',
    'stock_actual',
    'estado_repuesto',
])]
class Repuesto extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'repuesto';

    public function detallesMantenimiento()
    {
        return $this->hasMany(DetalleMantenimiento::class, 'id_repuesto');
    }
}
