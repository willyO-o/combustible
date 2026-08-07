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
        ];
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
        )
        ->withPivot('origen', 'destino', 'cantidad', 'unidad_medida', 'hora_inicio', 'hora_fin');
    }
}
