<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
#[Fillable([
    'nro_placa',
    'anio',
    'marca',
    'estado_vehiculo',
    'id_tipo_combustible',
    'id_tipo_vehiculo',
    'fotografia',
])]
class Vehiculo extends Model
{
    use SoftDeletes;

    protected $table = 'vehiculo';

    // Relaciones
    public function tipoCombustible()
    {
        return $this->belongsTo(TipoCombustible::class, 'id_tipo_combustible');
    }

    public function tipoVehiculo()
    {
        return $this->belongsTo(TipoVehiculo::class, 'id_tipo_vehiculo');
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'id_vehiculo');
    }

    public function vales()
    {
        return $this->hasMany(Vale::class, 'id_vehiculo');
    }

    public function cargasCombustible()
    {
        return $this->hasMany(CargaCombustible::class, 'id_vehiculo');
    }

    public function kilometrajes()
    {
        return $this->hasMany(Kilometraje::class, 'id_vehiculo');
    }

    public function mantenimientos()
    {
        return $this->hasMany(Mantenimiento::class, 'id_vehiculo');
    }
}
