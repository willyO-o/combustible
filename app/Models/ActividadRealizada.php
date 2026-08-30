<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ActividadRealizada extends Pivot
{
    //
    protected $table = 'actividad_realizada';

    protected function casts(): array
    {
        return [
            'hora_inicio' => 'datetime:H:i',
            'hora_fin' => 'datetime:H:i',
            'cantidad' => 'decimal:2',
        ];
    }

    /**
     * Material trasladado en la actividad (sólo aplica a vehículos con
     * medición por kilometraje: viajes y traslados). Puede ser nulo.
     */
    public function material()
    {
        return $this->belongsTo(Material::class, 'id_material');
    }
}
