<?php

namespace App\Actions\OperacionDiaria;

use Illuminate\Support\Facades\DB;
use App\Models\OperacionDiaria;
use App\Events\ObservacionOperacionEvent;
use App\Actions\Actividades\SincronizarActividadesRealizadasAction;

class UpdateOperacionDiariaAction
{
    public function __construct(private SincronizarActividadesRealizadasAction $sincronizarActividadesRealizadasAction) {}

    public function execute(OperacionDiaria $operacionDiaria, array $datos): OperacionDiaria
    {
        return DB::transaction(function () use ($operacionDiaria, $datos) {

            $observacionesOriginales = $operacionDiaria->observaciones;
            $operacionDiaria->update($datos);

            $this->sincronizarActividadesRealizadasAction->execute($operacionDiaria, $datos['actividades_realizadas'] ?? []);

            if (!empty($datos['observaciones']) && !empty($datos['notificar_observaciones']) && $datos['observaciones'] !== $observacionesOriginales) {
                event(new ObservacionOperacionEvent($operacionDiaria));
            }

            return $operacionDiaria;
        });
    }
}
