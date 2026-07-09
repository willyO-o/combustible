<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'nro_vale',
    'fecha_emision',
    'litros',
    'precio',
    'id_vehiculo',
    'id_conductor',
    'id_grifo',
    'estado_vale',
])]

class Vale extends Model
{
    protected $table = 'vale';



    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
        ];
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

    public function grifo()
    {
        return $this->belongsTo(Grifo::class, 'id_grifo');
    }

    public function cargasCombustible()
    {
        return $this->hasMany(CargaCombustible::class, 'id_vale');
    }
}
