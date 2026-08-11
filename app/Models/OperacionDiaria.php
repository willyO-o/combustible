<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'nro_operacion',
    'id_conductor',
    'id_vehiculo',
    'id_area',
    'turno',
    'fecha_inicio',
    'fecha_fin',
    'kilometraje_inicio',
    'kilometraje_fin',
    'horometro_inicio',
    'horometro_fin',
    'horas_trabajadas',
    'id_verificador',
    'estado',
    'observaciones'
])]

class OperacionDiaria extends Model
{
    //
    protected $table = 'operacion_diaria';

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime:d/m/Y H:i',
            'fecha_fin' => 'datetime:d/m/Y H:i',
            'kilometraje_inicio' => 'decimal:2',
            'kilometraje_fin' => 'decimal:2',
            'horometro_inicio' => 'decimal:2',
            'horometro_fin' => 'decimal:2',
            'horas_trabajadas' => 'decimal:2',
            'created_at' => 'datetime:d/m/Y H:i',
        ];
    }

    protected $appends = ['nro', 'fecha_i_f', 'fecha_f_f'];

    public function getNroAttribute()
    {
        return str_pad($this->nro_operacion, 5, '0', STR_PAD_LEFT);
    }

    public function getFechaIFAttribute()
    {
        return $this->fecha_inicio ? $this->fecha_inicio->format('Y-m-d H:i') : null;
    }
    public function getFechaFFAttribute()
    {
        return $this->fecha_fin ? $this->fecha_fin->format('Y-m-d H:i') : null;
    }


    protected static function booted()
    {
        static::creating(function ($operacion) {
            $ultimoNroOperacion = self::max('nro_operacion');
            $operacion->nro_operacion = $ultimoNroOperacion ? $ultimoNroOperacion + 1 : 1;

            $operacion->id_conductor = auth()->user()->id_persona;
        });
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class, 'id_conductor');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'id_vehiculo');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'id_area');
    }

    public function verificador()
    {
        return $this->belongsTo(Persona::class, 'id_verificador');
    }

    public function actividadesRealizadas()
    {
        return $this->belongsToMany(
            Actividad::class,
            'actividad_realizada',
            'id_operacion_diaria',
            'id_actividad'
        )->using(ActividadRealizada::class)
        ->withPivot('id', 'origen', 'destino', 'lugar', 'cantidad', 'unidad_medida', 'hora_inicio', 'hora_fin');
    }

    public function actividadesRealizadasEdit()
    {
        $actividades = $this->actividadesRealizadas()->get();
        return $actividades->map(function ($actividad) {
            return [
                'id' => $actividad->pivot->id,
                'actividad' => $actividad->nombre_actividad,
                'origen' => $actividad->pivot->origen,
                'destino' => $actividad->pivot->destino,
                'lugar' => $actividad->pivot->lugar,
                'cantidad' => $actividad->pivot->cantidad,
                'unidad_medida' => $actividad->pivot->unidad_medida,
                'hora_inicio' => $actividad->pivot->hora_inicio?->format('H:i'),
                'hora_fin' => $actividad->pivot->hora_fin?->format('H:i'),
            ];
        });
    }
}
