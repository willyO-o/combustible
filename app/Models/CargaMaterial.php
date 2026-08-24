<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'nro_carga',
    'id_vehiculo_externo',
    'id_usuario_apertura',
    'id_usuario_cierre',
    'fecha_apertura',
    'fecha_cierre',
    'nombre_conductor',
    'telefono',
    'fecha_pago',
    'monto_pago',
    'observaciones',
    'estado_carga',
])]
class CargaMaterial extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'carga_material';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
            'fecha_pago' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $carga) {
            $ultimoNroCarga = self::max('nro_carga');
            $carga->nro_carga = $ultimoNroCarga ? $ultimoNroCarga + 1 : 1;

            $carga->id_usuario_apertura = Auth::id();
            $carga->fecha_apertura = now();
            $carga->estado_carga = 'ABIERTA';
        });
    }

    // Relaciones
    public function vehiculoExterno()
    {
        return $this->belongsTo(VehiculoExterno::class, 'id_vehiculo_externo');
    }

    public function usuarioApertura()
    {
        return $this->belongsTo(User::class, 'id_usuario_apertura');
    }

    public function usuarioCierre()
    {
        return $this->belongsTo(User::class, 'id_usuario_cierre');
    }

    public function viajes()
    {
        return $this->hasMany(Viaje::class, 'id_carga_material');
    }
}
