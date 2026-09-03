<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'id_tipo_vehiculo',
    'id_tipo_mantenimiento',
    'tipo_medicion',
    'frecuencia',
    'estado',
])]

class IntervaloMantenimientoTipo extends Pivot
{
    protected $table = 'intervalo_mantenimiento_tipo';

    /**
     * Holgura relativa (±) sobre la frecuencia para considerar un mantenimiento
     * "próximo" en lugar de "vencido" o "al día".
     */
    private const HOLGURA = 0.05;

    public function tipoVehiculo()
    {
        return $this->belongsTo(TipoVehiculo::class, 'id_tipo_vehiculo');
    }

    public function tipoMantenimiento()
    {
        return $this->belongsTo(TipoMantenimiento::class, 'id_tipo_mantenimiento');
    }

    /**
     * Alertas de mantenimiento sugeridas por vehículo. Para cada intervalo
     * activo del tipo de vehículo compara la lectura actual del vehículo
     * (MAX de carga_combustible en la columna que indica tipo_medicion) contra
     * la última vez que se registró ese mantenimiento (MAX de
     * detalle_mantenimiento unido a orden_trabajo), y sugiere el siguiente
     * múltiplo de la frecuencia con una holgura del 5% (±).
     *
     * Las agregaciones (joins + subconsultas MAX) se resuelven en la base de
     * datos; en PHP sólo queda la aritmética por fila (múltiplo / estado).
     *
     * @param  array<int>|null  $idVehiculos  Si se pasa, acota a esos vehículos.
     * @return Collection<int, array{
     *     id_vehiculo: int, vehiculo: string, codigo: string|null, nro_placa: string|null,
     *     id_tipo_mantenimiento: int, tipo_mantenimiento: string, tipo_medicion: string,
     *     frecuencia: float, holgura: float, lectura_actual: float|null,
     *     ultimo_mantenimiento: float|null, proximo_objetivo: float, restante: float|null,
     *     estado: string
     * }>
     */
    public static function alertasMantenimiento(?array $idVehiculos = null): Collection
    {
        $lecturaActual = "(CASE WHEN imt.tipo_medicion = 'kilometraje'
                THEN (SELECT MAX(cc.kilometraje) FROM carga_combustible cc
                      WHERE cc.id_vehiculo = v.id AND (cc.estado_carga IS NULL OR cc.estado_carga <> 'ANULADO'))
                ELSE (SELECT MAX(cc.horometro) FROM carga_combustible cc
                      WHERE cc.id_vehiculo = v.id AND (cc.estado_carga IS NULL OR cc.estado_carga <> 'ANULADO'))
            END) as lectura_actual";

        $ultimoMantenimiento = "(CASE WHEN imt.tipo_medicion = 'kilometraje'
                THEN (SELECT MAX(dm.kilometraje) FROM detalle_mantenimiento dm
                      JOIN orden_trabajo ot ON ot.id = dm.id_orden_trabajo
                      WHERE ot.id_vehiculo = v.id AND dm.id_tipo_mantenimiento = imt.id_tipo_mantenimiento)
                ELSE (SELECT MAX(dm.horometro) FROM detalle_mantenimiento dm
                      JOIN orden_trabajo ot ON ot.id = dm.id_orden_trabajo
                      WHERE ot.id_vehiculo = v.id AND dm.id_tipo_mantenimiento = imt.id_tipo_mantenimiento)
            END) as ultimo_mantenimiento";

        $filas = DB::table('intervalo_mantenimiento_tipo as imt')
            ->join('vehiculo as v', 'v.id_tipo_vehiculo', '=', 'imt.id_tipo_vehiculo')
            ->join('tipo_mantenimiento as tm', 'tm.id', '=', 'imt.id_tipo_mantenimiento')
            ->whereNull('v.deleted_at')
            ->where('v.estado_vehiculo', 'ACTIVO')
            ->where('imt.estado', 'ACTIVO')
            ->where('imt.frecuencia', '>', 0)
            ->when($idVehiculos !== null, fn ($query) => $query->whereIn('v.id', $idVehiculos))
            ->selectRaw("v.id as id_vehiculo, v.codigo, v.nro_placa, v.marca, v.modelo,
                imt.id_tipo_mantenimiento, tm.tipo_mantenimiento, imt.tipo_medicion, imt.frecuencia,
                {$lecturaActual}, {$ultimoMantenimiento}")
            ->orderBy('v.codigo')
            ->orderBy('tm.tipo_mantenimiento')
            ->get();

        return $filas->map(fn ($fila) => self::evaluarAlerta($fila));
    }

    /**
     * Resumen para el dashboard: sólo los vehículos con al menos un
     * mantenimiento vencido o próximo, agrupados por vehículo.
     *
     * @param  array<int>|null  $idVehiculos
     * @return array<int, array{
     *     id_vehiculo: int, vehiculo: string, codigo: string|null, nro_placa: string|null,
     *     vencidos: int, proximos: int, estado: string, items: array<int, array<string, mixed>>
     * }>
     */
    public static function resumenAlertasVehiculos(?array $idVehiculos = null): array
    {
        return self::alertasMantenimiento($idVehiculos)
            ->filter(fn ($alerta) => in_array($alerta['estado'], ['VENCIDO', 'PROXIMO'], true))
            ->groupBy('id_vehiculo')
            ->map(function (Collection $items) {
                $primero = $items->first();
                $vencidos = $items->where('estado', 'VENCIDO')->count();
                $proximos = $items->where('estado', 'PROXIMO')->count();

                return [
                    'id_vehiculo' => $primero['id_vehiculo'],
                    'vehiculo' => $primero['vehiculo'],
                    'codigo' => $primero['codigo'],
                    'nro_placa' => $primero['nro_placa'],
                    'vencidos' => $vencidos,
                    'proximos' => $proximos,
                    'estado' => $vencidos > 0 ? 'VENCIDO' : 'PROXIMO',
                    'items' => $items->map(fn ($item) => [
                        'tipo_mantenimiento' => $item['tipo_mantenimiento'],
                        'tipo_medicion' => $item['tipo_medicion'],
                        'proximo_objetivo' => $item['proximo_objetivo'],
                        'lectura_actual' => $item['lectura_actual'],
                        'restante' => $item['restante'],
                        'estado' => $item['estado'],
                    ])->values()->all(),
                ];
            })
            ->sortByDesc(fn ($vehiculo) => $vehiculo['vencidos'] * 1000 + $vehiculo['proximos'])
            ->values()
            ->all();
    }

    /**
     * Aplica la fórmula de múltiplos + holgura del 5% a una fila cruda de la
     * consulta de alertas.
     *
     * @return array<string, mixed>
     */
    private static function evaluarAlerta(object $fila): array
    {
        $frecuencia = (float) $fila->frecuencia;
        $holgura = round($frecuencia * self::HOLGURA, 2);

        $lecturaActual = $fila->lectura_actual !== null ? (float) $fila->lectura_actual : null;
        $ultimoMantenimiento = $fila->ultimo_mantenimiento !== null ? (float) $fila->ultimo_mantenimiento : null;

        $ciclosCubiertos = $ultimoMantenimiento !== null
            ? (int) floor(($ultimoMantenimiento + $holgura) / $frecuencia)
            : 0;
        $proximoObjetivo = ($ciclosCubiertos + 1) * $frecuencia;
        $restante = $lecturaActual !== null ? round($proximoObjetivo - $lecturaActual, 2) : null;

        $estado = match (true) {
            $restante === null => 'SIN_DATOS',
            $restante < -$holgura => 'VENCIDO',
            $restante <= $holgura => 'PROXIMO',
            default => 'AL_DIA',
        };

        return [
            'id_vehiculo' => (int) $fila->id_vehiculo,
            'vehiculo' => collect([$fila->codigo, $fila->nro_placa])->filter()->implode(' · ')
                ?: trim("{$fila->marca} {$fila->modelo}"),
            'codigo' => $fila->codigo,
            'nro_placa' => $fila->nro_placa,
            'id_tipo_mantenimiento' => (int) $fila->id_tipo_mantenimiento,
            'tipo_mantenimiento' => $fila->tipo_mantenimiento,
            'tipo_medicion' => $fila->tipo_medicion,
            'frecuencia' => $frecuencia,
            'holgura' => $holgura,
            'lectura_actual' => $lecturaActual,
            'ultimo_mantenimiento' => $ultimoMantenimiento,
            'proximo_objetivo' => $proximoObjetivo,
            'restante' => $restante,
            'estado' => $estado,
        ];
    }
}
