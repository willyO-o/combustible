<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'fecha_inicio',
    'fecha_fin',
    'detalle',
    'id_vehiculo',
    'id_conductor',
    'id_tipo_mantenimiento',
    'estado_mantenimiento',
])]
class Mantenimiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'mantenimiento';

    protected function casts()
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
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

    public function tipoMantenimiento()
    {
        return $this->belongsTo(TipoMantenimiento::class, 'id_tipo_mantenimiento');
    }
}
