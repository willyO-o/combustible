<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'id_vehiculo',
    'id_tipo_mantenimiento',
    'id_solicitud_mantenimiento',
    'id_taller',
    'id_usuario_jefe',
    'tipo_mantenimiento',
    'tipo_orden',
    'descripcion_trabajo_ordenado',
    'fecha_orden',
    'kilometraje_programado',
    'fecha_programada',
    'frecuencia_km',
    'frecuencia_mes',
    'fecha_inicio',
    'fecha_fin',
    'kilometraje_al_mantenimiento',
    'trabajo_realizado',
    'costo_mano_obra',
    'costo_total',
    'id_usuario_ejecuta',
    'estado_plan',
    'observacion',
])]
class PlanMantenimiento extends Model
{
    protected $table = 'plan_mantenimiento';

    protected function casts(): array
    {
        return [
            'fecha_orden'     => 'date',
            'fecha_programada'=> 'date',
            'fecha_inicio'    => 'date',
            'fecha_fin'       => 'date',
            'costo_mano_obra' => 'decimal:2',
            'costo_total'     => 'decimal:2',
        ];
    }

    // Relaciones
    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'id_vehiculo');
    }

    public function tipoMantenimiento()
    {
        return $this->belongsTo(TipoMantenimiento::class, 'id_tipo_mantenimiento');
    }

    public function solicitudMantenimiento()
    {
        return $this->belongsTo(SolicitudMantenimiento::class, 'id_solicitud_mantenimiento');
    }

    public function taller()
    {
        return $this->belongsTo(Taller::class, 'id_taller');
    }

    public function usuarioJefe()
    {
        return $this->belongsTo(User::class, 'id_usuario_jefe');
    }

    public function usuarioEjecuta()
    {
        return $this->belongsTo(User::class, 'id_usuario_ejecuta');
    }

    public function repuestos()
    {
        return $this->hasMany(MantenimientoRepuesto::class, 'id_plan_mantenimiento');
    }
}
