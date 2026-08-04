<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'id_vehiculo',
    'id_conductor',
    'id_usuario_registra',
    'tipo_mantenimiento',
    'descripcion_problema',
    'kilometraje_actual',
    'fecha_solicitud',
    'estado',
    'observacion',
])]
class SolicitudMantenimiento extends Model
{
    protected $table = 'solicitud_mantenimiento';

    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'datetime',
        ];
    }

    // Relaciones
    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'id_vehiculo');
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class, 'id_conductor');
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_conductor');
    }

    public function usuarioRegistra()
    {
        return $this->belongsTo(User::class, 'id_usuario_registra');
    }

    public function planMantenimiento()
    {
        return $this->hasOne(PlanMantenimiento::class, 'id_solicitud_mantenimiento');
    }
}
