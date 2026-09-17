<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Línea de detalle de una orden de trabajo: un repuesto/insumo aplicado (o
 * una acción de mano de obra sin repuesto asociado) en una fecha puntual,
 * con la lectura de horómetro/kilometraje del vehículo en ese momento.
 * Reemplaza a MantenimientoRepuesto. El técnico va registrando estas líneas
 * una a una a medida que avanza el trabajo (ver OrdenTrabajoController).
 */
#[Fillable([
    'id_orden_trabajo',
    'id_repuesto',
    'id_tipo_mantenimiento',
    'fecha',
    'horometro',
    'kilometraje',
    'cantidad',
])]
class DetalleMantenimiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'detalle_mantenimiento';

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'horometro' => 'decimal:2',
            'kilometraje' => 'decimal:2',
        ];
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
