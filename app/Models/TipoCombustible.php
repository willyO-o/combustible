<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tipo_combustible',
    'estado_tipo_combustible',
])]
class TipoCombustible extends Model
{
    use HasFactory;

    protected $table = 'tipo_combustible';

    // Relaciones
    public function vehiculos()
    {
        return $this->hasMany(Vehiculo::class, 'id_tipo_combustible');
    }
}
