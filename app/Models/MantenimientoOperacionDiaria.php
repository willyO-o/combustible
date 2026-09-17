<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Contracts\Auditable;

class MantenimientoOperacionDiaria extends Pivot implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'mantenimiento_operacion_diaria';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }
}
