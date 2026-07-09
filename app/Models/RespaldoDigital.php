<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
#[Fillable([
    'ruta_respaldo',
    'tipo_respaldo',
    'tipo_archivo',
    'id_carga_combustible',
    'id_incidencia',
])]
class RespaldoDigital extends Model
{
    protected $table = 'respaldo_digital';


    // Relaciones
    public function cargaCombustible()
    {
        return $this->belongsTo(CargaCombustible::class, 'id_carga_combustible');
    }

    public function incidencia()
    {
        return $this->belongsTo(Incidencia::class, 'id_incidencia');
    }
}
