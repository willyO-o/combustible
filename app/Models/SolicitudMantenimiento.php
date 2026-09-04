<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
        $digitos = ParametrosEmpresa::first()->parametros_vale->digitos_serie;

        return $this->nro_solicitud ? str_pad($this->nro_solicitud, $digitos, '0', STR_PAD_LEFT).'/'.$this->gestion : null;
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

    public function ordenTrabajo()
    {
        return $this->hasOne(OrdenTrabajo::class, 'id_solicitud_mantenimiento');
    }

    protected function calcularGestion(): int
    {
        $mesCicloContable = ParametrosEmpresa::first()->parametros_vale->mes_ciclo_contable;

        // Si el mes actual alcanzó el mes de inicio del ciclo contable
        // configurado, la gestión ya pertenece al año siguiente.
        return now()->month >= $mesCicloContable ? now()->year + 1 : now()->year;
    }

    public static function siguienteNroSolicitud(int $gestion): int
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
            $solicitudMantenimiento->nro_solicitud = self::siguienteNroSolicitud($solicitudMantenimiento->gestion);

            // Sólo se completa aquí si el creador no lo trajo ya resuelto:
            // OrdenTrabajoController::store() genera una solicitud ya
            // APROBADA cuando la orden se emite sin solicitud de origen (ver
            // .ai/rules/http-controllers-http-requests.md), así que forzar
            // siempre PENDIENTE rompería ese caso.
            if (empty($solicitudMantenimiento->estado)) {
                $solicitudMantenimiento->estado = 'PENDIENTE';
            }

            // Sólo se completa aquí si el creador (Action/seeder/test) no lo
            // trajo ya resuelto: desde que un jefe de área o administrador
            // puede elegir explícitamente el conductor de la solicitud (ver
            // CreateSolicitudMantenimientoAction), forzar siempre el usuario
            // autenticado rompería esa selección.
            if (empty($solicitudMantenimiento->id_conductor)) {
                $solicitudMantenimiento->id_conductor = Auth::user()?->id_persona;
            }

            $solicitudMantenimiento->id_usuario_registra = Auth::id();
        });
    }
}
