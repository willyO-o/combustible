<?php

namespace App\Actions\Areas;

use App\Models\Area;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListHistorialVehiculosAreaAction
{
    /**
     * Histórico completo de asignaciones de vehículos al área (incluye
     * REASIGNADO/CULMINADO), más reciente primero. Se resuelve con query
     * builder para traer una fila por asignación (belongsToMany deduplicaría
     * por vehículo). Lo usan la ficha del área y el perfil del jefe de área.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(Area $area): Collection
    {
        $fmt = fn ($fecha): ?string => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : null;

        return DB::table('vehiculo_area as va')
            ->join('vehiculo as v', 'v.id', '=', 'va.id_vehiculo')
            ->where('va.id_area', $area->id)
            ->orderByDesc('va.fecha_asignacion')
            ->orderByDesc('va.id')
            ->get([
                'va.id',
                'va.id_vehiculo',
                'va.estado_asignacion',
                'va.fecha_asignacion',
                'va.fecha_reasignacion',
                'va.fecha_culminacion',
                'va.motivo_asignacion',
                'v.codigo',
                'v.nro_placa',
                'v.marca',
                'v.modelo',
            ])
            ->map(fn ($fila): array => [
                'id' => $fila->id,
                'id_vehiculo' => $fila->id_vehiculo,
                'codigo' => $fila->codigo,
                'nro_placa' => $fila->nro_placa,
                'marca' => $fila->marca,
                'modelo' => $fila->modelo,
                'estado_asignacion' => $fila->estado_asignacion,
                'fecha_asignacion' => $fmt($fila->fecha_asignacion),
                'fecha_reasignacion' => $fmt($fila->fecha_reasignacion),
                'fecha_culminacion' => $fmt($fila->fecha_culminacion),
                'motivo_asignacion' => $fila->motivo_asignacion,
            ]);
    }
}
