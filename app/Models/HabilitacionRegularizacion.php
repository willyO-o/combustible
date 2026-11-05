<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\HabilitacionRegularizacionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Permiso temporal que otorga un encargado de regularización para que un
 * usuario registre operaciones diarias de días pasados (ver
 * ObtenerReglaFechaOperacionAction). Sólo cubre los días entre fecha_desde y
 * fecha_hasta, y sólo hasta vigente_hasta o hasta que se revoque.
 */
#[Fillable([
    'id_usuario',
    'fecha_desde',
    'fecha_hasta',
    'vigente_hasta',
    'motivo',
    'id_usuario_otorga',
    'revocada_at',
    'id_usuario_revoca',
])]
class HabilitacionRegularizacion extends Model implements Auditable
{
    /** @use HasFactory<HabilitacionRegularizacionFactory> */
    use HasFactory;

    use \OwenIt\Auditing\Auditable;

    protected $table = 'habilitacion_regularizacion';

    protected function casts(): array
    {
        return [
            'fecha_desde' => 'date',
            'fecha_hasta' => 'date',
            'vigente_hasta' => 'datetime',
            'revocada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function otorgadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_otorga');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revocadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_revoca');
    }

    /**
     * VIGENTE, VENCIDA o REVOCADA (derivado: no se guarda).
     */
    public function getEstadoAttribute(): string
    {
        return match (true) {
            $this->revocada_at !== null => 'REVOCADA',
            $this->vigente_hasta->isPast() => 'VENCIDA',
            default => 'VIGENTE',
        };
    }

    /**
     * El día de la fecha indicada está dentro de los días habilitados.
     */
    public function cubreElDia(CarbonInterface $fecha): bool
    {
        return $fecha->copy()->startOfDay()->betweenIncluded($this->fecha_desde, $this->fecha_hasta);
    }
}
