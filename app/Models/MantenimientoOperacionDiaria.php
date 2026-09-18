<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use OwenIt\Auditing\Contracts\Auditable;

class MantenimientoOperacionDiaria extends Pivot implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    // La tabla tiene su propia PK autoincremental (id), a diferencia de un
    // pivot tradicional sin clave propia: Pivot::$incrementing es false por
    // defecto, así que hay que reactivarlo para que create()/fresh()/refresh()
    // recuperen correctamente el id insertado.
    public $incrementing = true;

    protected $table = 'mantenimiento_operacion_diaria';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }
}
