<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
#[Fillable([
    'incidencia',
    'detalle',
    'fecha_incidecia',
    'id_conductor',
])]
class Incidencia extends Model
{
    protected $table = 'incidencia';



    protected function casts()
    {
        return [
            'fecha_incidecia' => 'date',
        ];
    }

    // Relaciones
    public function conductor()
    {
        return $this->belongsTo(Conductor::class, 'id_conductor');
    }

    public function respaldosDigitales()
    {
        return $this->hasMany(RespaldoDigital::class, 'id_incidencia');
    }
}
