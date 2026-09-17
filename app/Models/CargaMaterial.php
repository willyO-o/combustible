<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'nro_carga',
    'id_vehiculo_externo',
    'id_usuario_apertura',
    'id_usuario_cierre',
    'fecha_apertura',
    'fecha_cierre',
    'es_al_exterior',
    'pais',
    'nombre_conductor',
    'telefono',
    'fecha_pago',
    'monto_pago',
    'detalle',
    'observaciones',
    'estado_carga',
])]
class CargaMaterial extends Model implements Auditable
{
    use HasFactory, HasUuids;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'carga_material';

    protected $appends = ['nro'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'fecha_apertura' => 'datetime',
            'fecha_cierre' => 'datetime',
            'es_al_exterior' => 'boolean',
            'fecha_pago' => 'datetime',
        ];
    }

    public function getNroAttribute()
    {
        $digitos = ParametrosEmpresa::first()->parametros_vale->digitos_serie;

        return $this->nro_carga ? str_pad($this->nro_carga, $digitos, '0', STR_PAD_LEFT).'/'.$this->gestion : null;
    }

    protected function calcularGestion(): string
    {
        $mesCicloContable = ParametrosEmpresa::first()->parametros_vale->mes_ciclo_contable;

        // Si el mes actual alcanzó el mes de inicio del ciclo contable
        // configurado, la gestión ya pertenece al año siguiente.
        return (string) (now()->month >= $mesCicloContable ? now()->year + 1 : now()->year);
    }

    public static function siguienteNroCarga(string $gestion): int
    {
        $ultimo = self::where('gestion', $gestion)
            ->lockForUpdate()
            ->orderBy('nro_carga', 'desc')
            ->first();

        return $ultimo ? $ultimo->nro_carga + 1 : 1;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $carga) {
            $carga->gestion = $carga->calcularGestion();
            $carga->nro_carga = self::siguienteNroCarga($carga->gestion);

            $carga->id_usuario_apertura = Auth::id();
            // Fecha/hora actual del servidor, salvo que el controlador ya la
            // haya asignado explícitamente (registro offline sincronizado
            // desde la API — ver CargaMaterialController@store en Api/V1).
            $carga->fecha_apertura = $carga->fecha_apertura ?: now();
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
