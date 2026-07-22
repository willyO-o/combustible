<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'id',
    'estado_conductor',
])]

class Conductor extends Model
{
    use SoftDeletes;

    protected $table = 'conductor';

    // protected $primaryKey = 'id_persona';


    public $incrementing = false;


    public function getFNacimientoFormattedAttribute()
    {
        if ($this->fecha_nacimiento) {
            return $this->fecha_nacimiento->format('d/m/Y');
        }
        return null;
    }

    public function getEdadAttribute()
    {
        if ($this->fecha_nacimiento) {
            return $this->fecha_nacimiento->age;
        }
        return null;
    }

    // Relaciones
    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona');
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'id_conductor');
    }

    public function asignacioneActivas()
    {
        return $this->belongsToMany(Vehiculo::class, 'asignacion', 'id_conductor', 'id_vehiculo')
            ->withPivot(['id', 'estado_asignacion', 'fecha_asignacion', 'detalle'])
            ->wherePivot('estado_asignacion', 'ACTIVO');
    }

    public function historialAsignacionesVehiculos()
    {
        return $this->belongsToMany(Vehiculo::class, 'asignacion', 'id_conductor', 'id_vehiculo')
            ->withPivot(['id', 'estado_asignacion', 'fecha_asignacion', 'fecha_culminacion', 'detalle'])
            ->wherePivot('estado_asignacion', '!=', 'ACTIVO')
            ->orderByPivot('fecha_asignacion', 'desc');
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
