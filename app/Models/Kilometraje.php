<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
#[Fillable([
    'kilometraje',
    'fecha_kilometraje',
    'id_vehiculo',
])]
class Kilometraje extends Model
{
    protected $table = 'kilometraje';



    protected function casts()
    {
        return [
            'fecha_kilometraje' => 'datetime',
        ];
    }

    // Relaciones
    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'id_vehiculo');
    }
}
