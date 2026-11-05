<?php

namespace App\Actions\Vehiculo;

use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Support\Facades\DB;

class AsignarVehiculoAreaAction
{
    /**
     * Asigna (o reasigna) un vehículo a un área, de forma ACTIVA o
     * PROVISIONAL (préstamo temporal). La asignación vigente anterior del
     * vehículo (si la tiene, sin importar el área) queda REASIGNADA con
     * fecha_reasignacion = ahora: nunca conviven dos asignaciones vigentes
     * para el mismo vehículo.
     *
     * Única fuente de esta regla: la usan el modal del listado de vehículos,
     * la ficha del área y el registro de un vehículo nuevo.
     *
     * @param  array{id_area: int|string, estado_asignacion: string, fecha_culminacion?: ?string, motivo_asignacion?: ?string}  $datos
     */
    public function execute(Vehiculo $vehiculo, array $datos): VehiculoArea
    {
        return DB::transaction(function () use ($vehiculo, $datos) {
            VehiculoArea::where('id_vehiculo', $vehiculo->id)
                ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
                ->where(function ($query) {
                    $query->whereNull('fecha_culminacion')
                        ->orWhere('fecha_culminacion', '>', now());
                })
                ->update([
                    'estado_asignacion' => 'REASIGNADO',
                    'fecha_reasignacion' => now(),
                ]);

            $esProvisional = $datos['estado_asignacion'] === 'PROVISIONAL';

            return VehiculoArea::create([
                'id_vehiculo' => $vehiculo->id,
                'id_area' => $datos['id_area'],
                'fecha_asignacion' => now(),
                'fecha_culminacion' => $esProvisional ? ($datos['fecha_culminacion'] ?? null) : null,
                'estado_asignacion' => $datos['estado_asignacion'],
                'motivo_asignacion' => $datos['motivo_asignacion'] ?? null,
            ]);
        });
    }
}
