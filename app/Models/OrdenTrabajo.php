<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Paso 2 y 3 del flujo de mantenimiento: la orden de trabajo emitida por el
 * Jefe de Transportes (con su responsable de ejecución asignado) y su
 * posterior registro de ejecución/culminación. El detalle de repuestos e
 * insumos utilizados vive en DetalleMantenimiento.
 */
#[Fillable([
    'id_solicitud_mantenimiento',
    'id_vehiculo',
    'id_conductor',
    'id_taller',
    'id_usuario_emite',
    'id_usuario_ejecuta',
    'nro_orden',
    'gestion',
    'fecha_emision',
    'fecha_ejecucion',
    'fecha_culminacion',
    'nota_emisor',
    'observacion',
    'tipo_mantenimiento',
    'kilometraje_actual',
    'horometro_actual',
    'estado_orden',
])]
class OrdenTrabajo extends Model
{
    protected $table = 'orden_trabajo';

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'fecha_ejecucion' => 'datetime',
            'fecha_culminacion' => 'datetime',
        ];
    }

    protected $appends = ['nro', 'tipo_orden'];

    public function getNroAttribute()
    {
        $digitos = ParametrosEmpresa::first()->parametros_vale->digitos_serie;

        return $this->nro_orden ? str_pad($this->nro_orden, $digitos, '0', STR_PAD_LEFT).'/'.$this->gestion : null;
    }

    /**
     * Interno cuando no se asignó un taller externo, externo en caso contrario.
     */
    public function getTipoOrdenAttribute(): string
    {
        return $this->id_taller ? 'EXTERNO' : 'INTERNO';
    }

    // Relaciones
    public function solicitudMantenimiento()
    {
        return $this->belongsTo(SolicitudMantenimiento::class, 'id_solicitud_mantenimiento');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'id_vehiculo');
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class, 'id_conductor');
    }

    public function taller()
    {
        return $this->belongsTo(Taller::class, 'id_taller');
    }

    public function usuarioEmite()
    {
        return $this->belongsTo(User::class, 'id_usuario_emite');
    }

    public function usuarioEjecuta()
    {
        return $this->belongsTo(User::class, 'id_usuario_ejecuta');
    }

    public function detalles()
    {
        return $this->hasMany(DetalleMantenimiento::class, 'id_orden_trabajo');
    }

    protected function calcularGestion(): string
    {
        $mesCicloContable = ParametrosEmpresa::first()->parametros_vale->mes_ciclo_contable;

        // Si el mes actual alcanzó el mes de inicio del ciclo contable
        // configurado, la gestión ya pertenece al año siguiente.
        return (string) (now()->month >= $mesCicloContable ? now()->year + 1 : now()->year);
    }

    public static function siguienteNroOrden(string $gestion): int
    {
        $ultimo = self::where('gestion', $gestion)
            ->lockForUpdate()
            ->orderBy('nro_orden', 'desc')
            ->first();

        return $ultimo ? $ultimo->nro_orden + 1 : 1;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OrdenTrabajo $orden) {
            $orden->gestion = $orden->calcularGestion();
            $orden->nro_orden = self::siguienteNroOrden($orden->gestion);

            $orden->fecha_emision = now();
            $orden->estado_orden = 'PENDIENTE';
            $orden->id_usuario_emite = auth()->id();
        });
    }
}
