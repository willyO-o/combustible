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
    public function __construct(private SincronizarActividadesRealizadasAction $sincronizarActividadesRealizadasAction) {}

    public function execute(OperacionDiaria $operacionDiaria, array $datos): OperacionDiaria
    {
        return DB::transaction(function () use ($operacionDiaria, $datos) {

            // Si viene un id_conductor explícito (combo mostrado a roles
            // distintos de conductor), verificar que siga siendo un
            // conductor realmente asignado al vehículo (el actual o el
            // nuevo, si también se cambió).
            if (! empty($datos['id_conductor'])) {
                $vehiculo = Vehiculo::find($datos['id_vehiculo'] ?? $operacionDiaria->id_vehiculo);

                if (! $vehiculo || ! $vehiculo->conductoresAsignados->contains('id', $datos['id_conductor'])) {
                    throw new ConductorNoAsignadoException('El conductor seleccionado no está asignado actualmente a este vehículo.');
                }
            }

            $observacionesOriginales = $operacionDiaria->observaciones;
            $operacionDiaria->update($datos);

            $this->sincronizarActividadesRealizadasAction->execute($operacionDiaria, $datos['actividades_realizadas'] ?? []);

            if (! empty($datos['observaciones']) && ! empty($datos['notificar_observaciones']) && $datos['observaciones'] !== $observacionesOriginales) {
                event(new ObservacionOperacionEvent($operacionDiaria));
            }

            return $operacionDiaria;
        });
    }
}
