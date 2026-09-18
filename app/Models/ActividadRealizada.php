<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Contracts\Auditable;

class ActividadRealizada extends Pivot implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    //
    protected $table = 'actividad_realizada';

    /**
     * A diferencia de un Pivot típico, `actividad_realizada` tiene su propia
     * columna `id` autoincremental (usada en withPivot y para editar/borrar
     * actividades individuales), así que Eloquent debe recuperarla tras el
     * insert.
     */
    public $incrementing = true;

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
