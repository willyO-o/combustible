<?php

namespace App\Models;

use App\Casts\ParametrosVale;
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
    'estado'
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
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('parametros_empresa'));
        static::deleted(fn () => Cache::forget('parametros_empresa'));
    }
}
