<?php

namespace App\Actions\Areas;

use App\Models\Area;
use App\Models\Vehiculo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ListVehiculosVigentesAreaAction
{
    /**
     * Vehículos con asignación vigente al área (Area::vehiculosActivos),
     * con su conductor actual. Lo usan la ficha del área (AreaController::show)
     * y el perfil del jefe de área (ProfileController), con la misma forma.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(Area $area): Collection
    {
        $fmt = fn ($fecha): ?string => $fecha ? Carbon::parse($fecha)->format('d/m/Y') : null;

        return $area->vehiculosActivos()
            ->with([
                'tipoVehiculo:id,tipo_vehiculo',
                'tipoCombustible:id,tipo_combustible',
                'conductorAsignado.persona',
            ])
            ->get()
            ->map(fn (Vehiculo $vehiculo): array => [
                'id' => $vehiculo->id,
                'codigo' => $vehiculo->codigo,
                'nro_placa' => $vehiculo->nro_placa,
                'marca' => $vehiculo->marca,
                'modelo' => $vehiculo->modelo,
                'anio' => $vehiculo->anio,
                'estado_vehiculo' => $vehiculo->estado_vehiculo,
                'tipo_medicion' => $vehiculo->tipo_medicion,
                'url_fotografia' => $vehiculo->url_fotografia,
                'tipo_vehiculo' => $vehiculo->tipoVehiculo?->tipo_vehiculo,
                'tipo_combustible' => $vehiculo->tipoCombustible?->tipo_combustible,
                'conductor' => $vehiculo->conductorAsignado?->persona ? [
                    'nombre_completo' => $vehiculo->conductorAsignado->persona->nombre_completo,
                    'ci' => $vehiculo->conductorAsignado->persona->ci,
                    'celular' => $vehiculo->conductorAsignado->persona->celular,
                ] : null,
                'id_asignacion' => $vehiculo->pivot->id,
                'estado_asignacion' => $vehiculo->pivot->estado_asignacion,
                'fecha_asignacion' => $fmt($vehiculo->pivot->fecha_asignacion),
                'fecha_culminacion' => $fmt($vehiculo->pivot->fecha_culminacion),
                'motivo_asignacion' => $vehiculo->pivot->motivo_asignacion,
            ]);
    }
}
