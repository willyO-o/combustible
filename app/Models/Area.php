<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'nombre_area',
    'descripcion_area',
    'estado_area',
])]
class Area extends Model
{
    //
    protected $table = 'area';


    public function vehiculos()
    {
        return $this->hasMany(Vehiculo::class, 'id_area');
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


    public static function conductoressss(string|int|null $idArea = null)
    {
        DB::table('conductor')
            ->whereExists(function ($query) use ($idArea) {
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
                                $query->whereNull('vehiculo_area.fecha_fin')
                                    ->orWhere('vehiculo_area.fecha_fin', '>', now());
                            })->where(function ($query) {
                                $query->where('vehiculo_area.estado_asignacion', 'ACTIVO')
                                    ->orWhere('vehiculo_area.estado_asignacion', 'PROVISIONAL');
                            })
                            ->when($idArea, function ($query) use ($idArea) {
                                $query->where('vehiculo_area.id_area', $idArea);
                            });
                    });
            })->select('conductor.*')
            ->get();
    }
}
