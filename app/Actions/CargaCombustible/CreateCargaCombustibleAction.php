<?php

namespace App\Actions\CargaCombustible;

use App\Actions\Respaldo\SincronizarRespaldoCargaAction;
use App\Models\CargaCombustible;
use App\Models\Vale;
use Illuminate\Support\Facades\DB;

class CreateCargaCombustibleAction
{
    public function __construct(private SincronizarRespaldoCargaAction $sincronizarRespaldos) {}

    public function execute($request)
    {
        //

        return DB::transaction(function () use ($request) {

            $datos = $request->all();

            if ($request->filled('id_vale')) {
                $vale = Vale::findOrFail($datos['id_vale']);

                $datos['litros'] = $vale->litros;
                $datos['precio'] = $vale->precio;
                $datos['id_grifo'] = $vale->id_grifo;
                $datos['id_tipo_combustible'] = $vale->id_tipo_combustible;

                $cargaCombustible = CargaCombustible::create($datos);

                $vale->update([
                    'estado_vale' => 'USADO',
                ]);
            } else {
                $cargaCombustible = CargaCombustible::create($datos);
            }

            $this->sincronizarRespaldos->execute($request, $cargaCombustible);

            return $cargaCombustible;
        });
    }
}
