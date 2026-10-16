<?php

namespace App\Actions\OperacionDiaria;

use App\Actions\Actividades\SincronizarActividadesRealizadasAction;
use App\Events\ObservacionOperacionEvent;
use App\Exceptions\AreaNoAsignadaException;
use App\Exceptions\ConductorNoAsignadoException;
use App\Exceptions\OperacionDiariaDuplicadaException;
use App\Models\Conductor;
use App\Models\OperacionDiaria;
use App\Models\Vehiculo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateOperacionDiariaAction
{
    public function __construct(
        private SincronizarActividadesRealizadasAction $sincronizarActividadesRealizadasAction,
        private SincronizarMantenimientosOperacionAction $sincronizarMantenimientosOperacionAction,
    ) {}

    public function execute(array $datos): OperacionDiaria
    {
        //
        return DB::transaction(
            function () use ($datos) {

                // lockForUpdate serializa registros simultáneos del mismo
                // vehículo (doble clic, web + app a la vez): así la regla de
                // una operación por vehículo y por día (ya validada en
                // OperacionStoreRequest) no se puede saltar por concurrencia.
                $vehiculo = Vehiculo::lockForUpdate()->findOrFail($datos['id_vehiculo']);

                if (OperacionDiaria::delVehiculoEnElDia($vehiculo->id, Carbon::parse($datos['fecha_inicio']))) {
                    throw new OperacionDiariaDuplicadaException;
                }

                $area = $vehiculo->areasAsignadas()->first();

                if (! $area) {
                    throw new AreaNoAsignadaException;
                }

                $datos['id_area'] = $area->id;

                // Cualquier operario ACTIVO puede figurar en la operación,
                // esté o no asignado al vehículo (a pedido del usuario: la
                // asignación conductor-vehículo todavía no es confiable). Sólo
                // si no llega id_conductor (no debería ocurrir: la request lo
                // exige salvo al conductor puro, al que el controller le
                // completa el suyo) se recurre al titular o al primer
                // conductor asignado.
                if (! empty($datos['id_conductor'])) {
                    $operarioActivo = Conductor::whereKey($datos['id_conductor'])
                        ->where('estado_conductor', 'ACTIVO')
                        ->exists();

                    if (! $operarioActivo) {
                        throw new ConductorNoAsignadoException('El operario seleccionado no está activo.');
                    }
                } else {
                    $conductorPorDefecto = $vehiculo->conductorAsignado ?? $vehiculo->conductoresAsignados->first();

                    if (! $conductorPorDefecto) {
                        throw new ConductorNoAsignadoException('Debe seleccionar el operario de la operación.');
                    }

                    $datos['id_conductor'] = $conductorPorDefecto->id;
                }

                $operacionDiaria = OperacionDiaria::create($datos);

                $this->sincronizarActividadesRealizadasAction->execute($operacionDiaria, $datos['actividades_realizadas']);

                $this->sincronizarMantenimientosOperacionAction->execute($operacionDiaria, $datos['mantenimientos'] ?? []);

                if (! empty($datos['observaciones']) && ! empty($datos['notificar_observaciones'])) {
                    event(new ObservacionOperacionEvent($operacionDiaria));
                }

                return $operacionDiaria;
            }
        );
    }
}
