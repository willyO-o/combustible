<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'id_plan_mantenimiento',
    'id_repuesto',
    'tipo_item',
    'nombre_item',
    'unidad_medida',
    'cantidad_utilizada',
    'costo_unitario',
    'subtotal',
    'observacion',
])]
class MantenimientoRepuesto extends Model
{
    protected $table = 'mantenimiento_repuesto';

    protected function casts(): array
    {
        return [
            'cantidad_utilizada' => 'decimal:2',
            'costo_unitario'     => 'decimal:2',
            'subtotal'           => 'decimal:2',
        ];
    }

    public function planMantenimiento()
    {
        return $this->belongsTo(PlanMantenimiento::class, 'id_plan_mantenimiento');
    }

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'id_repuesto');
    }
}
