<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'id_tipo_vehiculo',
    'id_tipo_mantenimiento',
    'tipo_medicion',
    'frecuencia',
    'estado',
])]

class IntervaloMantenimientoTipo extends Pivot
{
    protected $table = 'intervalo_mantenimiento_tipo';

    public function tipoVehiculo()
    {
        return $this->belongsTo(TipoVehiculo::class, 'id_tipo_vehiculo');
    }

    public function tipoMantenimiento()
    {
        return $this->belongsTo(TipoMantenimiento::class, 'id_tipo_mantenimiento');
    }
}
