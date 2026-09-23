<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'nombre_area',
    'descripcion_area',
    'estado_area',
])]
class Area extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'area';

    public function vehiculos()
    {
        return $this->belongsToMany(
            Vehiculo::class,
            'vehiculo_area',
            'id_area',
            'id_vehiculo'
        )->withPivot(['id', 'motivo_asignacion', 'fecha_reasignacion', 'estado_asignacion', 'fecha_asignacion', 'fecha_culminacion'])->withTimestamps();
    }

    public function vehiculosActivos()
    {
        return $this->vehiculos()
            ->where(function ($query) {
                $query->where('vehiculo_area.estado_asignacion', 'ACTIVO')
                    ->orWhere('vehiculo_area.estado_asignacion', 'PROVISIONAL');
            })->where(function ($query) {
                $query->whereNull('vehiculo_area.fecha_culminacion')
                    ->orWhere('vehiculo_area.fecha_culminacion', '>', now());
            })->orderBy('vehiculo_area.fecha_asignacion', 'desc');
    }

    public static function conductores(array|false $idArea = [])
    {
        if ($idArea === false) {
            return collect(); // Retorna una colección vacía si $idArea es false
        }

        return Conductor::whereExists(function ($query) use ($idArea) {
            $query->select(DB::raw(1))
                ->from('asignacion')
                ->whereColumn('asignacion.id_conductor', 'conductor.id')
                ->where(function ($query) {
                    $query->where('asignacion.estado_asignacion', 'ACTIVO')
                        ->orWhere('asignacion.estado_asignacion', 'PROVISIONAL');
                })
                ->where(function ($query) {
                    $query->whereNull('asignacion.fecha_culminacion')
                        ->orWhere('asignacion.fecha_culminacion', '>', now());
                })
                ->whereExists(function ($query) use ($idArea) {
                    $query->select(DB::raw(1))
                        ->from('vehiculo_area')
                        ->whereColumn('vehiculo_area.id_vehiculo', 'asignacion.id_vehiculo')
                        ->where(function ($query) {
                            $query->whereNull('vehiculo_area.fecha_culminacion')
                                ->orWhere('vehiculo_area.fecha_culminacion', '>', now());
                        })
                        ->where(function ($query) {
                            $query->where('vehiculo_area.estado_asignacion', 'ACTIVO')
                                ->orWhere('vehiculo_area.estado_asignacion', 'PROVISIONAL');
                        })
                        ->when($idArea, function ($query) use ($idArea) {
                            $query->whereIn('vehiculo_area.id_area', $idArea);
                        });
                });
        })->with('persona')->get(); // ahora devuelve un Eloquent Collection de objetos Conductor con la relación persona cargada
    }

    public function encargados()
    {
        return $this->belongsToMany(
            Persona::class,
            'encargado_area',
            'id_area',
            'id_persona'
        )->withPivot(['id', 'tipo_encargo', 'fecha_inicio', 'fecha_reasignacion', 'fecha_fin', 'motivo', 'estado_encargo'])->withTimestamps();
    }

    public function encargadosActivos()
    {
        return $this->encargados()->where(function ($query) {
            $query->where('encargado_area.estado_encargo', 'ACTIVO')
                ->where(function ($query) {
                    $query->whereNull('encargado_area.fecha_fin')
                        ->orWhere('encargado_area.fecha_fin', '>', now());
                });
        });
    }

    public function encargadosUser()
    {
        return $this->belongsToMany(
            User::class,
            'encargado_area',
            'id_area',
            'id_persona',
            null,
            // El pivote guarda id_persona: se une contra users.id_persona,
            // no contra users.id (que sólo coincidiría por casualidad).
            'id_persona'
        )->withPivot(['id', 'tipo_encargo', 'fecha_inicio', 'fecha_reasignacion', 'fecha_fin', 'motivo', 'estado_encargo'])->withTimestamps();
    }

    public function encargadosUserActivos()
    {
        return $this->encargadosUser()->where(function ($query) {
            $query->where('encargado_area.estado_encargo', 'ACTIVO')
                ->where(function ($query) {
                    $query->whereNull('encargado_area.fecha_fin')
                        ->orWhere('encargado_area.fecha_fin', '>', now());
                });
        });
    }
}
