<?php

namespace App\Actions\OperacionDiaria;

use Illuminate\Support\Facades\DB;
use App\Models\OperacionDiaria;
use App\Models\Vehiculo;
use App\Exceptions\AreaNoAsignadaException;
use App\Events\ObservacionOperacionEvent;
use App\Actions\Actividades\SincronizarActividadesRealizadasAction;

class CreateOperacionDiariaAction
{
    public function __construct(private SincronizarActividadesRealizadasAction $sincronizarActividadesRealizadasAction)
    {}

    public function execute(array $datos) : OperacionDiaria
    {
        //
        return DB::transaction(
            function () use ($datos) {


                $vehiculo = Vehiculo::findOrFail($datos["id_vehiculo"]);

                $area = $vehiculo->areasAsignadas()->first();

                if (! $area) {
                    throw new AreaNoAsignadaException();
                }

                $datos['id_area']  = $area->id;

                $operacionDiaria = OperacionDiaria::create($datos);

                $this->sincronizarActividadesRealizadasAction->execute($operacionDiaria, $datos['actividades_realizadas']);

                if (!empty($datos['observaciones']) && !empty($datos['notificar_observaciones'])) {
                    event(new ObservacionOperacionEvent($operacionDiaria));
                }
                return $operacionDiaria;
            }
        );
    }

}
