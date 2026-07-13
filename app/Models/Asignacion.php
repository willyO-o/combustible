<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['id_vehiculo', 'id_conductor', 'fecha_asignacion','fecha_culminacion', 'estado_asignacion', 'detalle'])]
class Asignacion extends Model
{
    protected $table = 'asignacion';


    protected function casts(){
        return [
            'fecha_asignacion' => 'date',
            'fecha_culminacion' => 'date',
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
}
