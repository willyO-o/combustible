<?php

namespace App\Actions\OperacionDiaria;

use App\Actions\Actividades\SincronizarActividadesRealizadasAction;
use App\Events\ObservacionOperacionEvent;
use App\Exceptions\ConductorNoAsignadoException;
use App\Models\OperacionDiaria;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\DB;

class UpdateOperacionDiariaAction
{
    public function __construct(
        private SincronizarActividadesRealizadasAction $sincronizarActividadesRealizadasAction,
        private SincronizarMantenimientosOperacionAction $sincronizarMantenimientosOperacionAction,
    ) {}

    public function execute(OperacionDiaria $operacionDiaria, array $datos): OperacionDiaria
    {
        return DB::transaction(function () use ($operacionDiaria, $datos) {

            // Sin id_conductor (p.ej. el combo quedó vacío) se conserva el
            // de la operación: la columna no admite null. Conservar el mismo
            // conductor siempre es válido aunque ya no esté asignado al
            // vehículo (operaciones históricas tras una reasignación); sólo
            // un CAMBIO de conductor exige que el nuevo esté asignado hoy.
            if (empty($datos['id_conductor'])) {
                unset($datos['id_conductor']);
            } elseif ((int) $datos['id_conductor'] !== (int) $operacionDiaria->id_conductor) {
                $vehiculo = Vehiculo::find($datos['id_vehiculo'] ?? $operacionDiaria->id_vehiculo);

                if (! $vehiculo || ! $vehiculo->conductoresAsignados->contains('id', $datos['id_conductor'])) {
                    throw new ConductorNoAsignadoException('El conductor seleccionado no está asignado actualmente a este vehículo.');
                }
            }

            $observacionesOriginales = $operacionDiaria->observaciones;
            $operacionDiaria->update($datos);

            $this->sincronizarActividadesRealizadasAction->execute($operacionDiaria, $datos['actividades_realizadas'] ?? []);

            $this->sincronizarMantenimientosOperacionAction->execute($operacionDiaria, $datos['mantenimientos'] ?? []);

            if (! empty($datos['observaciones']) && ! empty($datos['notificar_observaciones']) && $datos['observaciones'] !== $observacionesOriginales) {
                event(new ObservacionOperacionEvent($operacionDiaria));
            }

            return $operacionDiaria;
        });
    }
}
