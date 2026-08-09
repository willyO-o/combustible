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


}
