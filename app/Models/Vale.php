<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
//soft delete
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nro_vale',
    'fecha_emision',
    'fecha_vencimiento',
    'litros',
    'precio',
    'id_vehiculo',
    'id_conductor',
    'id_grifo',
    'estado_vale',
    'id_tipo_combustible',
    'id_user',
])]

class Vale extends Model
{
    use SoftDeletes;
    protected $table = 'vale';


    protected $appends = ['nro', 'fecha_emision_f', 'fecha_vencimiento_f'];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime:Y-m-d H:i',
            'fecha_vencimiento' => 'datetime:Y-m-d H:i',
        ];
    }

    public function getNroAttribute()
    {
        return str_pad($this->nro_vale, 6, '0', STR_PAD_LEFT) . '/' . $this->gestion;
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
        // Si el mes actual es noviembre (11) o diciembre (12),
        // la gestión ya pertenece al año siguiente
        return now()->month >= 11 ? now()->year + 1 : now()->year;
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
        $gestion = now()->month >= 11 ? now()->year + 1 : now()->year;

        $ultimo = Vale::where('gestion', $gestion)
            ->orderBy('nro_vale', 'desc')
            ->first();

        $siguienteNro = $ultimo ? $ultimo->nro_vale + 1 : 1;

        return str_pad($siguienteNro, 6, '0', STR_PAD_LEFT) . '/' . $gestion;
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
