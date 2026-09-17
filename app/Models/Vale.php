<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
// soft delete
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'nro_vale',
    'fecha_emision',
    'fecha_vencimiento',
    'notificado_vencimiento_at',
    'litros',
    'precio',
    'id_vehiculo',
    'id_conductor',
    'id_grifo',
    'estado_vale',
    'id_tipo_combustible',
    'id_user',
])]

class Vale extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use SoftDeletes;

    protected $table = 'vale';

    protected $appends = ['nro', 'fecha_emision_f', 'fecha_vencimiento_f'];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime:Y-m-d H:i',
            'fecha_vencimiento' => 'datetime:Y-m-d H:i',
            'notificado_vencimiento_at' => 'datetime',
        ];
    }

    public function getNroAttribute()
    {
        $digitos = ParametrosEmpresa::first()->parametros_vale->digitos_serie;

        return str_pad($this->nro_vale, $digitos, '0', STR_PAD_LEFT).'/'.$this->gestion;
    }

    public function getFechaEmisionFAttribute()
    {
        return $this->fecha_emision ? $this->fecha_emision->format('d/m/Y H:i') : null;
    }

    public function getFechaVencimientoFAttribute()
    {
        return $this->fecha_vencimiento ? $this->fecha_vencimiento->format('d/m/Y H:i') : null;
    }

    // Relaciones
    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'id_vehiculo');
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class, 'id_conductor');
    }

    public function grifo()
    {
        return $this->belongsTo(Grifo::class, 'id_grifo');
    }

    public function cargasCombustible()
    {
        return $this->hasMany(CargaCombustible::class, 'id_vale');
    }

    public function tipoCombustible()
    {
        return $this->belongsTo(TipoCombustible::class, 'id_tipo_combustible');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function generarFechaVencimiento()
    {
        $parametrosEmpresa = ParametrosEmpresa::first();

        $dias = $parametrosEmpresa->parametros_vale->tiempo_expiracion;

        return $this->fecha_emision->copy()->addDays($dias);
    }

    protected function calcularGestion(): int
    {
        $mesCicloContable = ParametrosEmpresa::first()->parametros_vale->mes_ciclo_contable;

        // Si el mes actual alcanzó el mes de inicio del ciclo contable
        // configurado, la gestión ya pertenece al año siguiente.
        return now()->month >= $mesCicloContable ? now()->year + 1 : now()->year;
    }

    public static function siguienteNroVale(int $gestion): int
    {
        $ultimo = Vale::where('gestion', $gestion)
            ->lockForUpdate()
            ->orderBy('nro_vale', 'desc')
            ->first();

        return $ultimo ? $ultimo->nro_vale + 1 : 1;
    }

    public static function siguienteNroValeProvisional(): string
    {
        $parametrosVale = ParametrosEmpresa::first()->parametros_vale;

        $gestion = now()->month >= $parametrosVale->mes_ciclo_contable ? now()->year + 1 : now()->year;

        $ultimo = Vale::where('gestion', $gestion)
            ->orderBy('nro_vale', 'desc')
            ->first();

        $siguienteNro = $ultimo ? $ultimo->nro_vale + 1 : 1;

        return str_pad($siguienteNro, $parametrosVale->digitos_serie, '0', STR_PAD_LEFT).'/'.$gestion;
    }

    protected static function boot()
    {
        parent::boot();

        // Asignar el ID del usuario autenticado al crear un nuevo registro
        static::creating(function ($model) {
            $model->id_user = auth()->id();

            $model->fecha_emision = now();

            $gestion = $model->calcularGestion();
            $model->gestion = $gestion;
            $model->nro_vale = self::siguienteNroVale($gestion);

            $model->fecha_vencimiento = $model->generarFechaVencimiento();
        });
    }
}
