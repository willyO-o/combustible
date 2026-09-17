<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'kilometraje',
    'fecha_kilometraje',
    'id_vehiculo',
])]
class Kilometraje extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

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
