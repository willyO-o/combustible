<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'tipo_vehiculo',
    'estado_tipo_vehiculo',
    'id_grupo_vehiculo',
    'unidad_capacidad_sugerida',
])]
class TipoVehiculo extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'tipo_vehiculo';

    public function grupoVehiculo()
    {
        return $this->belongsTo(GrupoVehiculo::class, 'id_grupo_vehiculo');
    }

    // Relaciones
    public function vehiculos()
    {
        return $this->hasMany(Vehiculo::class, 'id_tipo_vehiculo');
    }

    public function tipoMantenimientos()
    {
        return $this->belongsToMany(
            TipoMantenimiento::class,
            'intervalo_mantenimiento_tipo',
            'id_tipo_vehiculo',
            'id_tipo_mantenimiento')
            ->using(IntervaloMantenimientoTipo::class)
            ->withPivot('id', 'tipo_medicion', 'frecuencia', 'estado');
    }

    /**
     * Intervalos de mantenimiento configurados para este tipo de vehículo
     * (referencia para futuras alertas de mantenimiento).
     */
    public function intervalos()
    {
        return $this->hasMany(IntervaloMantenimientoTipo::class, 'id_tipo_vehiculo');
    }
}
