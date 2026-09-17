<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'nro_placa',
    'codigo',
    'anio',
    'marca',
    'modelo',
    'estado_vehiculo',
    'id_tipo_combustible',
    'id_tipo_vehiculo',
    'fotografia',
    'uuid',
    'detalles',
    'tipo_medicion',
    'capacidad',
    'capacidad_unidad',
])]
class Vehiculo extends Model implements Auditable
{
    use HasFactory, HasUuids, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'vehiculo';

    protected $appends = [
        'url_fotografia',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getUrlFotografiaAttribute()
    {
        if ($this->fotografia) {
            return asset('storage/'.$this->fotografia);
        }

        return null;
    }

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
        )->where(function ($query) {
            $query->where('asignacion.estado_asignacion', 'ACTIVO')
                ->where(function ($query) {
                    $query->whereNull('asignacion.fecha_culminacion')
                        ->orWhere('asignacion.fecha_culminacion', '>', now());
                });
        });
    }

    public function conductores()
    {
        return $this->hasManyThrough(
            Conductor::class,      // Modelo final que queremos obtener
            Asignacion::class,     // Modelo intermedio
            'id_vehiculo',         // Llave foránea en la tabla Asignacion
            'id',                  // Llave primaria en la tabla Conductor
            'id',                  // Llave primaria en la tabla Vehiculo
            'id_conductor'         // Llave foránea en la tabla Asignacion que apunta al Conductor
        );
    }

    // Estado PROVISIONAL o ACTIVO Y fecha_culminacion nula o futura (antes se
    // evaluaba con "orWhere" entre los tres grupos, lo que hacía que
    // cualquier asignación con fecha_culminacion nula -incluida una ya
    // REASIGNADA/CULMINADA sin ese campo registrado- contara como
    // "asignada", el mismo bug ya corregido en areasAsignadas()).
    public function conductoresAsignados()
    {
        return $this->conductores()->where(function ($query) {
            $query->where('asignacion.estado_asignacion', 'PROVISIONAL')
                ->orWhere('asignacion.estado_asignacion', 'ACTIVO');
        })->where(function ($query) {
            $query->whereNull('asignacion.fecha_culminacion')
                ->orWhere('asignacion.fecha_culminacion', '>', now());
        });
    }

    public function conductoresAsignadosOpt()
    {
        return $this->conductoresAsignados->map(function ($conductor) {
            return [
                'id' => $conductor->id,
                'label' => "{$conductor->persona->nombre_completo} (CI: {$conductor->persona->ci})",
                'meta' => [
                    'ci' => $conductor->persona->ci,
                    'nombre_completo' => $conductor->persona->nombre_completo,
                ],
            ];
        });
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

    public function areas()
    {
        return $this->belongsToMany(
            Area::class,
            'vehiculo_area',
            'id_vehiculo',
            'id_area'
        )->using(VehiculoArea::class)
            ->withPivot([
                'id',
                'motivo_asignacion',
                'fecha_asignacion',
                'fecha_reasignacion',
                'fecha_culminacion',
                'estado_asignacion',
            ])
            ->withTimestamps();
    }

    // Estado PROVISIONAL o ACTIVO Y fecha_culminacion nula o futura (antes se
    // evaluaba con "orWhere" entre ambos grupos, lo que hacía que cualquier
    // fila con fecha_culminacion nula -incluida una ya REASIGNADA/CULMINADA
    // sin ese campo registrado- contara como "asignada").
    public function areasAsignadas()
    {
        return $this->areas()->where(function ($query) {
            $query->where('vehiculo_area.estado_asignacion', 'PROVISIONAL')
                ->orWhere('vehiculo_area.estado_asignacion', 'ACTIVO');
        })->where(function ($query) {
            $query->whereNull('vehiculo_area.fecha_culminacion')
                ->orWhere('vehiculo_area.fecha_culminacion', '>', now());
        })->orderBy('vehiculo_area.fecha_asignacion', 'desc');
    }
}
