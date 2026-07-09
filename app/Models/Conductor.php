<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'nombres',
    'paterno',
    'materno',
    'foto',
    'ci',
    'nro_licencia',
    'categoria',
    'celular',
    'direccion',
    'fecha_nacimiento',
    'estado_conductor'
])]

class Conductor extends Model
{
    use SoftDeletes;

    protected $table = 'conductor';



    protected function casts(){
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    // Relaciones
    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'id_conductor');
    }

    public function vales()
    {
        return $this->hasMany(Vale::class, 'id_conductor');
    }

    public function cargasCombustible()
    {
        return $this->hasMany(CargaCombustible::class, 'id_conductor');
    }

    public function incidencias()
    {
        return $this->hasMany(Incidencia::class, 'id_conductor');
    }

    public function mantenimientos()
    {
        return $this->hasMany(Mantenimiento::class, 'id_conductor');
    }
}
