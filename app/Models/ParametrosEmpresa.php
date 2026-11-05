<?php

namespace App\Models;

use App\Casts\ParametrosVale;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable(
    'nombre_empresa',
    'direccion_empresa',
    'telefono_empresa',
    'correo_empresa',
    'nit_empresa',
    'logo_empresa',
    'parametros_vale',
    'estado',
    'regularizacion_estricta_desde',
)]
class ParametrosEmpresa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    //
    protected $table = 'parametros_empresa';

    protected function casts(): array
    {
        return [
            'parametros_vale' => ParametrosVale::class,
            'regularizacion_estricta_desde' => 'date',
        ];
    }

    /**
     * Día desde el que rige el modo estricto de la operación diaria (sólo el
     * día actual, o la noche de ayer), o null si sigue el modo transición.
     * Sin fila de parámetros = transición (comportamiento anterior).
     */
    public static function regularizacionEstrictaDesde(): ?CarbonInterface
    {
        return self::query()->first(['id', 'regularizacion_estricta_desde'])?->regularizacion_estricta_desde;
    }

    /**
     * El modo estricto ya rige hoy (activado a mano o llegó la fecha programada).
     */
    public static function regularizacionEstrictaVigente(): bool
    {
        return self::regularizacionEstrictaDesde()?->lte(today()) ?? false;
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('parametros_empresa'));
        static::deleted(fn () => Cache::forget('parametros_empresa'));
    }
}
