<?php

namespace App\Actions\OperacionDiaria;

use App\Actions\Actividades\SincronizarActividadesRealizadasAction;
use App\Events\ObservacionOperacionEvent;
use App\Exceptions\ConductorNoAsignadoException;
use App\Exceptions\OperacionDiariaDuplicadaException;
use App\Models\Conductor;
use App\Models\OperacionDiaria;
use App\Models\Vehiculo;
use Illuminate\Support\Carbon;
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

            // Mover la fecha de inicio a un día en que el vehículo ya tiene
            // otra operación rompería la regla de una por vehículo y por día.
            if (! empty($datos['fecha_inicio'])) {
                Vehiculo::lockForUpdate()->find($operacionDiaria->id_vehiculo);

                if (OperacionDiaria::delVehiculoEnElDia($operacionDiaria->id_vehiculo, Carbon::parse($datos['fecha_inicio']), $operacionDiaria->id)) {
                    throw new OperacionDiariaDuplicadaException;
                }
            }

            // Sin id_conductor (p.ej. el combo quedó vacío) se conserva el
            // de la operación: la columna no admite null. Conservar el mismo
            // operario siempre es válido aunque ya no esté activo (operaciones
            // históricas); un CAMBIO sólo exige que el nuevo esté ACTIVO — ya
            // no se exige que esté asignado al vehículo.
            if (empty($datos['id_conductor'])) {
                unset($datos['id_conductor']);
            } elseif ((int) $datos['id_conductor'] !== (int) $operacionDiaria->id_conductor) {
                $operarioActivo = Conductor::whereKey($datos['id_conductor'])
                    ->where('estado_conductor', 'ACTIVO')
                    ->exists();

                if (! $operarioActivo) {
                    throw new ConductorNoAsignadoException('El operario seleccionado no está activo.');
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
