<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'id',
    'estado_conductor',
])]

class Conductor extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

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
        return $this->belongsTo(Persona::class, 'id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id_persona', 'id');
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'id_conductor');
    }

    public function documentos()
    {
        return $this->hasMany(DocumentoConductor::class, 'id_conductor');
    }

    // extraer los datos de la licencia de conducir del conductor
    public function licenciaConducir()
    {
        return $this->documentos()->where('tipo_documento', 'LICENCIA_DE_CONDUCIR')->first();
    }

    public function asignacionesActivas()
    {
        return $this->belongsToMany(Vehiculo::class, 'asignacion', 'id_conductor', 'id_vehiculo')
            ->withPivot(['id', 'estado_asignacion', 'fecha_asignacion', 'fecha_culminacion', 'detalle', 'id_vehiculo', 'id_conductor'])
            ->where(function ($query) {
                $query->where('asignacion.estado_asignacion', 'ACTIVO')
                    ->orWhere('asignacion.estado_asignacion', 'PROVISIONAL');
            })
            ->where(function ($query) {
                $query->whereNull('asignacion.fecha_culminacion')
                    ->orWhere('asignacion.fecha_culminacion', '>', now());
            });
    }

    public function asignacionesActivasOpt()
    {
        // devolver en formato id, label y meta para usar en select2

        return $this->asignacionesActivas->map(function ($asignacion) {
            return [
                'id' => $asignacion->id,
                'label' => "{$asignacion->codigo} — {$asignacion->nro_placa} —  {$asignacion->marca} ({$asignacion->anio})",
                'meta' => [
                    'id_tipo_vehiculo' => $asignacion->id_tipo_vehiculo,
                    'id_tipo_combustible' => $asignacion->id_tipo_combustible,
                    'tipo_medicion' => $asignacion->tipo_medicion,
                    'marca' => $asignacion->marca,
                    'nro_placa' => $asignacion->nro_placa,
                    'anio' => $asignacion->anio,
                    'modelo' => $asignacion->modelo,
                    'codigo' => $asignacion->codigo,
                ],
            ];
        });
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

    public function mantenimientos()
    {
        return $this->hasMany(Mantenimiento::class, 'id_conductor');
    }

    public function areas()
    {
        return Area::whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('vehiculo_area')
                ->whereColumn('vehiculo_area.id_area', 'area.id')
                ->where(function ($query) {
                    $query->whereNull('vehiculo_area.fecha_culminacion')
                        ->orWhere('vehiculo_area.fecha_culminacion', '>', now());
                })
                ->where(function ($query) {
                    $query->where('vehiculo_area.estado_asignacion', 'ACTIVO')
                        ->orWhere('vehiculo_area.estado_asignacion', 'PROVISIONAL');
                })
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('asignacion')
                        ->whereColumn('asignacion.id_vehiculo', 'vehiculo_area.id_vehiculo')
                        ->where('asignacion.id_conductor', $this->id)
                        ->where(function ($query) {
                            $query->where('asignacion.estado_asignacion', 'ACTIVO')
                                ->orWhere('asignacion.estado_asignacion', 'PROVISIONAL');
                        })
                        ->where(function ($query) {
                            $query->whereNull('asignacion.fecha_culminacion')
                                ->orWhere('asignacion.fecha_culminacion', '>', now());
                        });
                });
        })->get();
    }
}
