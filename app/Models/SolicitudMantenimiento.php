<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'id_vehiculo',
    'nro_solicitud',
    'gestion',
    'id_conductor',
    'id_usuario_registra',
    'tipo_mantenimiento',
    'descripcion_problema',
    'kilometraje_actual',
    'horometro_actual',
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

    protected $appends = ['nro', 'fecha'];

    public function getNroAttribute()
    {
        // rellenar con ceros a la izquierda hasta 6 dígitos
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

    public static function siguienteNroMantenimientoProvisional(int $gestion): string
    {
        $gestion = now()->month >= 11 ? now()->year + 1 : now()->year;

        $ultimo = self::where('gestion', $gestion)
            ->orderBy('nro_solicitud', 'desc')
            ->first();

        $siguienteNro = $ultimo ? $ultimo->nro_solicitud + 1 : 1;

        return str_pad($siguienteNro, 6, '0', STR_PAD_LEFT).'/'.$gestion;
    }

    protected function calcularGestion(): int
    {
        // Si el mes actual es noviembre (11) o diciembre (12),
        // la gestión ya pertenece al año siguiente
        return now()->month >= 11 ? now()->year + 1 : now()->year;
    }

    public static function siguienteNroVale(int $gestion): int
    {
        $ultimo = self::where('gestion', $gestion)
            ->lockForUpdate()
            ->orderBy('nro_solicitud', 'desc')
            ->first();

        return $ultimo ? $ultimo->nro_solicitud + 1 : 1;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($solicitudMantenimiento) {
            $solicitudMantenimiento->gestion = $solicitudMantenimiento->calcularGestion();
            $solicitudMantenimiento->nro_solicitud = self::siguienteNroVale($solicitudMantenimiento->gestion);

            $solicitudMantenimiento->fecha_solicitud = now();

            $solicitudMantenimiento->estado = 'PENDIENTE';
            $solicitudMantenimiento->id_conductor = auth()->user()->id_persona;
            $solicitudMantenimiento->id_usuario_registra = auth()->id();
        });
    }
}
