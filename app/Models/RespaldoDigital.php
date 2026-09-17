<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'ruta_respaldo',
    'tipo_respaldo',
    'tipo_archivo',
    'id_carga_combustible',
])]
class RespaldoDigital extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'respaldo_digital';

    // Relaciones
    public function cargaCombustible()
    {
        return $this->belongsTo(CargaCombustible::class, 'id_carga_combustible');
    }
}
