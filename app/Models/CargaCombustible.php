<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'fecha_carga',
    'litros',
    'precio',
    'kilometraje',
    'id_vehiculo',
    'id_grifo',
    'id_tipo_combustible',
    'id_conductor',
    'id_vale',
    'nro_factura',
    'tipo_carga',
    'estado_carga'
])]

class CargaCombustible extends Model
{
    protected $table = 'carga_combustible';

    protected function casts()
    {
        return [
            'fecha_carga' => 'date',
        ];
    }


    // Relaciones
    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'id_vehiculo');
    }

    public function grifo()
    {
        return $this->belongsTo(Grifo::class, 'id_grifo');
    }

    public function tipoCombustible()
    {
        return $this->belongsTo(TipoCombustible::class, 'id_tipo_combustible');
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class, 'id_conductor');
    }

    public function vale()
    {
        return $this->belongsTo(Vale::class, 'id_vale');
    }

    public function respaldosDigitales()
    {
        return $this->hasMany(RespaldoDigital::class, 'id_carga_combustible');
    }
}
