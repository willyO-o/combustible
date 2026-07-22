<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'ci',
    'nombres',
    'paterno',
    'materno',
    'foto',
    'celular',
    'direccion',
    'fecha_nacimiento',
    'estado_persona',
])]

class Persona extends Model
{
    //
    use SoftDeletes;

    protected $table = 'persona';


    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    //agregar campos para foto en appends
    protected $appends = ['foto_url', 'edad', 'f_nacimiento_formatted'];

    public function getEdadAttribute()
    {
        return  $this->fecha_nacimiento ? $this->fecha_nacimiento->age : null;
    }

    public function getFNacimientoFormattedAttribute()
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->format('d/m/Y') : null;
    }

    public function getFotoUrlAttribute()
    {
        return $this->foto ? asset('storage/' . $this->foto) : null;
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id_persona');
    }

    public function conductor()
    {
        return $this->hasOne(Conductor::class, 'id_persona');
    }
}
