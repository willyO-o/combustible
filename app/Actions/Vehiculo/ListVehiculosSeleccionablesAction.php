<?php

namespace App\Actions\Vehiculo;

use App\Models\Asignacion;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoArea;
use Illuminate\Support\Collection;

class ListVehiculosSeleccionablesAction
{
    /**
     * Vehículos que se ofrecen, ya precargados, en los selects de los
     * formularios (vales, cargas de combustible, solicitudes y órdenes de
     * trabajo):
     * - Cualquier rol o combinación de roles (administrador, jefe de área,
     *   técnico, jefe + conductor, ...): TODOS los vehículos ACTIVO (a pedido
     *   del usuario, la asignación a áreas y conductores todavía no es
     *   confiable).
     * - Conductor puro: sólo sus vehículos asignados y ACTIVOS
     *   (User::idsVehiculosAsignados()). Mismo criterio que valida la regla
     *   VehiculoSeleccionable al registrar.
     *
     * Los "propios" del usuario (los que tiene asignados como conductor y los
     * de las áreas que tiene a cargo como jefe) llevan meta.propio y van
     * primero, sólo como sugerencia. meta.id_conductor (titular) y
     * meta.conductores_asignados (titular + provisionales vigentes) sirven
     * para sugerir el operario al elegir el vehículo.
     *
     * $incluirIdVehiculo agrega un vehículo aunque ya no esté activo (el de un
     * registro existente que se está editando).
     *
     * @return Collection<int, array{id: int, label: string, meta: array<string, mixed>}>
     */
    public function execute(?User $user, ?int $incluirIdVehiculo = null): Collection
    {
        $soloAsignados = $user?->esConductorPuro() ? $user->idsVehiculosAsignados() : null;

        $vehiculos = Vehiculo::query()
            ->select(['id', 'codigo', 'nro_placa', 'marca', 'modelo', 'anio', 'id_tipo_vehiculo', 'id_tipo_combustible', 'tipo_medicion'])
            ->where(function ($query) use ($incluirIdVehiculo, $soloAsignados) {
                $query->where(function ($query) use ($soloAsignados) {
                    $query->where('estado_vehiculo', 'ACTIVO');

                    if ($soloAsignados !== null) {
                        $query->whereIn('id', $soloAsignados);
                    }
                });

                if ($incluirIdVehiculo) {
                    $query->orWhere('id', $incluirIdVehiculo);
                }
            })
            ->get();

        $asignacionesPorVehiculo = $this->asignacionesVigentesPorVehiculo($vehiculos->pluck('id'));
        $idsPropios = $this->idsVehiculosPropios($user);

        return $vehiculos
            ->map(function (Vehiculo $vehiculo) use ($asignacionesPorVehiculo, $idsPropios) {
                $asignaciones = $asignacionesPorVehiculo->get($vehiculo->id, collect());
                $titular = $asignaciones->firstWhere('estado_asignacion', 'ACTIVO');

                return [
                    'id' => $vehiculo->id,
                    'label' => $vehiculo->etiquetaSeleccion(),
                    'meta' => [
                        'codigo' => $vehiculo->codigo,
                        'nro_placa' => $vehiculo->nro_placa,
                        'marca' => $vehiculo->marca,
                        'modelo' => $vehiculo->modelo,
                        'anio' => $vehiculo->anio,
                        'id_tipo_vehiculo' => $vehiculo->id_tipo_vehiculo,
                        'id_tipo_combustible' => $vehiculo->id_tipo_combustible,
                        'tipo_medicion' => $vehiculo->tipo_medicion,
                        'id_conductor' => $titular?->id_conductor,
                        // Titular primero, luego los provisionales.
                        'conductores_asignados' => $asignaciones
                            ->sortBy(fn ($asignacion) => $asignacion->estado_asignacion === 'ACTIVO' ? 0 : 1)
                            ->pluck('id_conductor')
                            ->unique()
                            ->values()
                            ->all(),
                        'propio' => $idsPropios->has($vehiculo->id),
                    ],
                ];
            })
            ->sortBy([
                fn ($a, $b) => (int) $b['meta']['propio'] <=> (int) $a['meta']['propio'],
                fn ($a, $b) => strnatcasecmp((string) $a['meta']['codigo'], (string) $b['meta']['codigo']),
            ])
            ->values();
    }

    /**
     * Asignaciones vigentes (ACTIVO = titular, PROVISIONAL = reemplazo) de
     * cada vehículo, en una sola consulta agrupada (evita el N+1 de un
     * conductoresAsignados() por vehículo con ~200 vehículos).
     *
     * @param  Collection<int, int>  $idsVehiculo
     * @return Collection<int, Collection<int, Asignacion>>
     */
    private function asignacionesVigentesPorVehiculo(Collection $idsVehiculo): Collection
    {
        if ($idsVehiculo->isEmpty()) {
            return collect();
        }

        return Asignacion::query()
            ->select(['id_vehiculo', 'id_conductor', 'estado_asignacion', 'fecha_asignacion'])
            ->whereIn('id_vehiculo', $idsVehiculo)
            ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
            ->where(fn ($query) => $query->whereNull('fecha_culminacion')->orWhere('fecha_culminacion', '>', now()))
            ->orderByDesc('fecha_asignacion')
            ->get()
            ->groupBy('id_vehiculo');
    }

    /**
     * Ids (como llaves, lookup O(1)) de los vehículos asignados al usuario
     * como conductor y de los de las áreas que tiene a cargo como jefe.
     *
     * @return Collection<int, int>
     */
    private function idsVehiculosPropios(?User $user): Collection
    {
        $persona = $user?->persona;

        if (! $persona) {
            return collect();
        }

        $ids = collect();

        if ($user->hasRole('conductor')) {
            $ids = $ids->merge(
                Asignacion::query()
                    ->where('id_conductor', $persona->id)
                    ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
                    ->where(fn ($query) => $query->whereNull('fecha_culminacion')->orWhere('fecha_culminacion', '>', now()))
                    ->pluck('id_vehiculo')
            );
        }

        if ($user->hasRole('jefe-area')) {
            $idsArea = $persona->encargadoAreas()->pluck('id_area');

            if ($idsArea->isNotEmpty()) {
                $ids = $ids->merge(
                    VehiculoArea::query()
                        ->whereIn('id_area', $idsArea)
                        ->whereIn('estado_asignacion', ['ACTIVO', 'PROVISIONAL'])
                        ->where(fn ($query) => $query->whereNull('fecha_culminacion')->orWhere('fecha_culminacion', '>', now()))
                        ->pluck('id_vehiculo')
                );
            }
        }

        return $ids->unique()->flip();
    }
}
