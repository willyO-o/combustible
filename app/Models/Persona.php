<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    use HasFactory, SoftDeletes;

    protected $table = 'persona';

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    // agregar campos para foto en appends
    protected $appends = ['foto_url', 'edad', 'f_nacimiento_formatted', 'nombre_completo', 'tipo_actual'];

    public function getNombreCompletoAttribute()
    {
        return "{$this->nombres} {$this->paterno} {$this->materno}";
    }

    public function getEdadAttribute()
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->age : null;
    }

    public function getFNacimientoFormattedAttribute()
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->format('d/m/Y') : null;
    }

    public function getFotoUrlAttribute()
    {
        return $this->foto ? asset('storage/'.$this->foto) : null;
    }

    /**
     * Rol operativo actual de la persona, derivado de sus registros
     * relacionados: 'conductor', 'jefe-area', 'personal' (solo tiene
     * usuario) o null (sin usuario ni rol asignado).
     */
    public function getTipoActualAttribute()
    {
        if ($this->conductor) {
            return 'conductor';
        }

        if ($this->encargadoAreas()->exists()) {
            return 'jefe-area';
        }

        if ($this->user) {
            return 'personal';
        }

        return null;
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id_persona');
    }

    public function conductor()
    {
        return $this->hasOne(Conductor::class, 'id');
    }

    public function vehiculosAsignados()
    {
        return $this->hasManyThrough(
            Vehiculo::class,      // Modelo final que queremos obtener
            Asignacion::class,     // Modelo intermedio
            'id_conductor',         // Llave foránea en la tabla Asignacion
            'id',                  // Llave primaria en la tabla Vehiculo
            'id',                  // Llave primaria en la tabla Persona
            'id_vehiculo'         // Llave foránea en la tabla Asignacion que apunta al Vehiculo
        )->where('asignacion.estado_asignacion', 'ACTIVO');
    }

    public function areas()
    {
        return $this->belongsToMany(
            Area::class,
            'encargado_area',
            'id_persona',
            'id_area'
        )->using(EncargadoArea::class)
            ->withPivot('id', 'tipo_encargo', 'fecha_inicio', 'fecha_reasignacion', 'fecha_fin', 'motivo', 'estado_encargo');
    }

    public function encargadoAreas()
    {
        return $this->areas()->where(function ($query) {
            $query->where('encargado_area.estado_encargo', 'ACTIVO')
                ->where(function ($query) {
                    $query->whereNull('encargado_area.fecha_fin')
                        ->orWhere('encargado_area.fecha_fin', '>', now());
                });
        });
    }
}
