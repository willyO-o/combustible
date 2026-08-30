<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MantenimientoOperacionDiaria extends Pivot
{
    protected $table = 'mantenimiento_operacion_diaria';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }
}
