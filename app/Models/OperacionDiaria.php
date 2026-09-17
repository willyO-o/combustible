<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Contracts\Auditable;

#[Fillable([
    'nro_operacion',
    'id_conductor',
    'id_vehiculo',
    'id_area',
    'turno',
    'fecha_inicio',
    'fecha_fin',
    'kilometraje_inicio',
    'kilometraje_fin',
    'horometro_inicio',
    'horometro_fin',
    'horas_trabajadas',
    'id_verificador',
    'estado',
    'observaciones',
])]

class OperacionDiaria extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    //
    protected $table = 'operacion_diaria';

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime:d/m/Y H:i',
            'fecha_fin' => 'datetime:d/m/Y H:i',
            'kilometraje_inicio' => 'decimal:2',
            'kilometraje_fin' => 'decimal:2',
            'horometro_inicio' => 'decimal:2',
            'horometro_fin' => 'decimal:2',
            'horas_trabajadas' => 'decimal:2',
            'created_at' => 'datetime:d/m/Y H:i',
        ];
    }

    protected $appends = ['nro', 'fecha_i_f', 'fecha_f_f'];

    public function getNroAttribute()
    {
        $digitos = ParametrosEmpresa::first()->parametros_vale->digitos_serie;

        return str_pad($this->nro_operacion, $digitos, '0', STR_PAD_LEFT);
    }

    public function getFechaIFAttribute()
    {
        return $this->fecha_inicio ? $this->fecha_inicio->format('Y-m-d H:i') : null;
    }

    public function getFechaFFAttribute()
    {
        return $this->fecha_fin ? $this->fecha_fin->format('Y-m-d H:i') : null;
    }

    protected static function booted()
    {
        static::creating(function ($operacion) {
            $ultimoNroOperacion = self::max('nro_operacion');
            $operacion->nro_operacion = $ultimoNroOperacion ? $ultimoNroOperacion + 1 : 1;

            // id_conductor ya viene resuelto por CreateOperacionDiariaAction a partir
            // del conductor actualmente asignado al vehículo (no del usuario
            // autenticado): ahora cualquier rol puede registrar una operación para
            // un vehículo que no necesariamente conduce él mismo.

            // horas trabajadas se calcula como la diferencia en horas entre fecha_fin y fecha_inicio
            // en formato con 1 decimal 4.5, 7.8, 8.0
            $operacion->horas_trabajadas = round(($operacion->fecha_fin->diffInMinutes($operacion->fecha_inicio, true) / 60), 1);
        });

        // para crea y actualizar
        static::updating(function ($operacion) {
            // horas trabajadas se calcula como la diferencia en horas entre fecha_fin y fecha_inicio
            // en formato con 1 decimal 4.5, 7.8, 8.0
            $operacion->horas_trabajadas = round(($operacion->fecha_fin->diffInMinutes($operacion->fecha_inicio, true) / 60), 1);
        });
    }

    public function conductor()
    {
        return $this->belongsTo(Conductor::class, 'id_conductor');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'id_vehiculo');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'id_area');
    }

    public function verificador()
    {
        return $this->belongsTo(Persona::class, 'id_verificador');
    }

    public function actividadesRealizadas()
    {
        return $this->belongsToMany(
            Actividad::class,
            'actividad_realizada',
            'id_operacion_diaria',
            'id_actividad'
        )->using(ActividadRealizada::class)
            ->withPivot('id', 'id_material', 'origen', 'destino', 'lugar', 'cantidad', 'unidad_medida', 'hora_inicio', 'hora_fin');
    }

    /**
     * Carga el material trasladado de cada actividad realizada (relación del
     * pivote), para exponer su nombre en el detalle y en la API sin un N+1.
     */
    public function cargarMaterialDeActividades(): static
    {
        $this->actividadesRealizadas->each(
            fn ($actividad) => $actividad->pivot->load('material:id,material')
        );

        return $this;
    }

    /**
     * Controles de mantenimiento registrados en la operación diaria
     * (tipo_mantenimiento de ámbito operacion_diaria). El pivote guarda
     * `valor` para los de tipo_valor "cantidad" y `realizado` (SI/NO) para
     * los de tipo "booleano"; ambos pueden ser nulos porque no todos los
     * controles aplican a cada operación.
     */
    public function mantenimientosOperacion()
    {
        return $this->belongsToMany(
            TipoMantenimiento::class,
            'mantenimiento_operacion_diaria',
            'id_operacion_diaria',
            'id_tipo_mantenimiento'
        )->using(MantenimientoOperacionDiaria::class)
            ->withPivot('id', 'valor', 'realizado', 'evidencia');
    }

    /**
     * Valores de mantenimiento ya guardados, en la forma que consume el
     * formulario (Operacion/Create.vue).
     *
     * @return Collection<int, array{id_tipo_mantenimiento: int, valor: string|null, realizado: string|null, evidencia: string|null}>
     */
    public function mantenimientosOperacionEdit()
    {
        return $this->mantenimientosOperacion()->get()->map(fn ($tipo) => [
            'id_tipo_mantenimiento' => $tipo->id,
            'valor' => $tipo->pivot->valor,
            'realizado' => $tipo->pivot->realizado,
            // Ruta ya guardada (para previsualizarla en el formulario de
            // edición) — NO se reenvía al servidor tal cual: sin un archivo
            // nuevo, SincronizarMantenimientosOperacionAction la conserva
            // sola a partir de lo que ya hay en la BD.
            'evidencia' => $tipo->pivot->evidencia,
        ]);
    }

    public function actividadesRealizadasEdit()
    {
        $actividades = $this->actividadesRealizadas()->get();

        return $actividades->map(function ($actividad) {
            return [
                'id' => $actividad->pivot->id,
                'actividad' => $actividad->nombre_actividad,
                'id_material' => $actividad->pivot->id_material,
                'origen' => $actividad->pivot->origen,
                'destino' => $actividad->pivot->destino,
                'lugar' => $actividad->pivot->lugar,
                'cantidad' => $actividad->pivot->cantidad,
                'unidad_medida' => $actividad->pivot->unidad_medida,
                'hora_inicio' => $actividad->pivot->hora_inicio?->format('H:i'),
                'hora_fin' => $actividad->pivot->hora_fin?->format('H:i'),
            ];
        });
    }
}
