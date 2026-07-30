<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
//soft delete
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nro_vale',
    'fecha_emision',
    'litros',
    'precio',
    'id_vehiculo',
    'id_conductor',
    'id_grifo',
    'estado_vale',
    'id_tipo_combustible',
    'id_user',
])]

class Vale extends Model
{
    use SoftDeletes;
    protected $table = 'vale';


    protected $appends = ['nro','fecha_emision_f'];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
        ];
    }

    public function getNroAttribute()
    {
        return str_pad($this->nro_vale, 6, '0', STR_PAD_LEFT);
    }
    public function getFechaEmisionFAttribute()
    {
        return $this->fecha_emision ? $this->fecha_emision->format('d/m/Y') : null;
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
