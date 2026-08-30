<?php

namespace App\Actions\OperacionDiaria;

use App\Actions\Actividades\SincronizarActividadesRealizadasAction;
use App\Events\ObservacionOperacionEvent;
use App\Exceptions\AreaNoAsignadaException;
use App\Exceptions\ConductorNoAsignadoException;
use App\Models\OperacionDiaria;
use App\Models\Vehiculo;
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

                $vehiculo = Vehiculo::findOrFail($datos['id_vehiculo']);

                $area = $vehiculo->areasAsignadas()->first();

                if (! $area) {
                    throw new AreaNoAsignadaException;
                }

                $datos['id_area'] = $area->id;

                // El registro ya no lo crea necesariamente el propio conductor
                // (ahora cualquier rol puede registrar la operación de un
                // vehículo que no maneja él mismo): un vehículo puede tener
                // varios conductores asignados a la vez (1 titular ACTIVO y
                // otros PROVISIONAL por permiso/vacaciones), así que el rol
                // conductor va directo (id_conductor = él mismo, resuelto en
                // el controller) y cualquier otro rol elige entre los
                // conductores realmente asignados al vehículo vía el combo
                // del formulario (id_conductor ya viene en $datos).
                $conductoresAsignados = $vehiculo->conductoresAsignados;

                if ($conductoresAsignados->isEmpty()) {
                    throw new ConductorNoAsignadoException;
                }

                if (! empty($datos['id_conductor'])) {
                    if (! $conductoresAsignados->contains('id', $datos['id_conductor'])) {
                        throw new ConductorNoAsignadoException('El conductor seleccionado no está asignado actualmente a este vehículo.');
                    }
                } else {
                    // Sin conductor explícito (no debería ocurrir si el
                    // formulario obliga a elegirlo): se usa el titular activo
                    // o, si sólo hay asignaciones provisionales, la primera.
                    $titular = $vehiculo->conductorAsignado;
                    $datos['id_conductor'] = $titular?->id ?? $conductoresAsignados->first()->id;
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
