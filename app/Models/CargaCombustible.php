<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'fecha_carga',
    'litros',
    'precio',
    'kilometraje',
    'horometro',
    'id_vehiculo',
    'id_grifo',
    'id_tipo_combustible',
    'id_conductor',
    'id_vale',
    'nro_factura',
    'tipo_carga',
    'estado_carga',
    'id_usuario',
    'nro_carga',
    'gestion',
    'concepto',

])]

class CargaCombustible extends Model
{
    protected $table = 'carga_combustible';

    protected function casts()
    {
        return [
            'fecha_carga' => 'date:Y-m-d H:i',
            'id_usuario' => 'integer',
        ];
    }

    protected $appends = [
        'fecha_carga_formateada',
        'nro',
    ];

    public function getFechaCargaFormateadaAttribute()
    {
        return $this->fecha_carga ? $this->fecha_carga->format('d/m/Y H:i') : '—';
    }

    public function getNroAttribute()
    {
        $digitos = ParametrosEmpresa::first()->parametros_vale->digitos_serie;

        return $this->nro_carga ? str_pad($this->nro_carga, $digitos, '0', STR_PAD_LEFT).'/'.$this->gestion : null;
    }

    protected function calcularGestion(): int
    {
        $mesCicloContable = ParametrosEmpresa::first()->parametros_vale->mes_ciclo_contable;

        // Si el mes actual alcanzó el mes de inicio del ciclo contable
        // configurado, la gestión ya pertenece al año siguiente.
        return now()->month >= $mesCicloContable ? now()->year + 1 : now()->year;
    }

    public static function siguienteNroCarga(int $gestion): int
    {
        $ultimo = self::where('gestion', $gestion)
            ->lockForUpdate()
            ->orderBy('nro_carga', 'desc')
            ->first();

        return $ultimo ? $ultimo->nro_carga + 1 : 1;
    }

    // Relaciones
    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'id_vehiculo');
    }

    public function grifo()
    {
        return $this->belongsTo(Grifo::class, 'id_grifo');
    }

    public function tipoCombustible()
    {
        return $this->belongsTo(TipoCombustible::class, 'id_tipo_combustible');
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class, 'id_conductor');
    }

    public function vale()
    {
        return $this->belongsTo(Vale::class, 'id_vale');
    }

    public function respaldosDigitales()
    {
        return $this->hasMany(RespaldoDigital::class, 'id_carga_combustible');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    // asigar id usuario al crear una carga de combustible
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id_usuario = Auth::id();

            $model->gestion = $model->calcularGestion();
            $model->nro_carga = self::siguienteNroCarga($model->gestion);
        });
    }

    public static function reporteCargaCombustibleMes($anio = null)
    {
        if (! $anio) {
            $anio = now()->year;
        }

        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        // reporte agrupado por meses de enero a diciembre, con total de litros y total de precio si el mes no tiene registros, debe aparecer con total 0 el formato deve ser un array [["mes" => 1, "total_litros" => 0, "total_precio" => 0], ["mes" => 2, "total_litros" => 0, "total_precio" => 0], ...]
        return self::selectRaw('MONTH(fecha_carga) as mes, SUM(litros) as total_litros, ROUND(SUM(precio * litros), 2) as total_precio')
            ->whereYear('fecha_carga', $anio)
            ->groupByRaw('MONTH(fecha_carga)')
            ->orderByRaw('MONTH(fecha_carga)')
            ->get()
            ->mapWithKeys(function ($item) use ($months) {
                return [$item->mes => [
                    'mes' => $months[$item->mes],
                    'total_litros' => $item->total_litros,
                    'total_precio' => $item->total_precio,
                ]];
            })
            ->union(collect(range(1, 12))->mapWithKeys(function ($mes) use ($months) {
                return [$mes => [
                    'mes' => $months[$mes],
                    'total_litros' => 0,
                    'total_precio' => 0,
                ]];
            })->except(self::selectRaw('MONTH(fecha_carga) as mes')->whereYear('fecha_carga', $anio)->pluck('mes')->toArray()))
            ->sortKeys()
            ->values()
            ->toArray();
    }

    // agregar id_condigor al crear una carga de combustible, si el usuario tiene rol conductor, asignar el id_conductor del usuario logueado

}
