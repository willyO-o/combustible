<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'id_vehiculo',
    'nro_solicitud',
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

    protected $appends = ["nro", "fecha"];

    public function getNroAttribute()
    {
        //rellenar con ceros a la izquierda hasta 6 dígitos
        return $this->nro_solicitud ? str_pad($this->nro_solicitud, 6, '0', STR_PAD_LEFT) : null;
    }

    public function getFechaAttribute()
    {
        return $this->fecha_solicitud?->format('d/m/Y');
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


    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($solicitudMantenimiento) {
            $ultimoNroSolicitud = self::max('nro_solicitud');
            $nuevoNroSolicitud = $ultimoNroSolicitud ? $ultimoNroSolicitud + 1 : 1;
            $solicitudMantenimiento->nro_solicitud = $nuevoNroSolicitud;
        });
    }
}
