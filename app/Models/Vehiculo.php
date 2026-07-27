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
    'detalles',
    'tipo_medicion',
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

    public function conductorAsignado()
    {
        // return $this->belongsToMany(Conductor::class, 'asignacion', 'id_vehiculo', 'id_conductor')
        //     ->withPivot(['id', 'estado_asignacion', 'fecha_asignacion', 'detalle'])
        //     ->wherePivot('estado_asignacion', 'ACTIVO');
        return $this->hasOneThrough(
            Conductor::class,      // Modelo final que queremos obtener
            Asignacion::class,     // Modelo intermedio
            'id_vehiculo',         // Llave foránea en la tabla Asignacion
            'id',                  // Llave primaria en la tabla Conductor
            'id',                  // Llave primaria en la tabla Vehiculo
            'id_conductor'         // Llave foránea en la tabla Asignacion que apunta al Conductor
        )->where('asignacion.estado_asignacion', 'ACTIVO');
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
