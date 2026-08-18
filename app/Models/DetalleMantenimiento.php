<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Línea de detalle de una orden de trabajo: repuesto/insumo o mano de obra
 * aplicada, con su cantidad y costo unitario. Reemplaza a MantenimientoRepuesto.
 */
#[Fillable([
    'id_orden_trabajo',
    'id_repuesto',
    'id_tipo_mantenimiento',
    'detalle',
    'cantidad',
    'costo_unitario',
])]
class DetalleMantenimiento extends Model
{
    protected $table = 'detalle_mantenimiento';

    protected function casts(): array
    {
        return [
            'costo_unitario' => 'decimal:2',
        ];
    }

    protected $appends = ['subtotal'];

    public function getSubtotalAttribute()
    {
        return round($this->cantidad * $this->costo_unitario, 2);
    }

    // Relaciones
    public function ordenTrabajo()
    {
        return $this->belongsTo(OrdenTrabajo::class, 'id_orden_trabajo');
    }

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'id_repuesto');
    }

    public function tipoMantenimiento()
    {
        return $this->belongsTo(TipoMantenimiento::class, 'id_tipo_mantenimiento');
    }
}
