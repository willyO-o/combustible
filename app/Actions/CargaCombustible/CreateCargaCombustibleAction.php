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

                // El vehículo, conductor, grifo, tipo de combustible, litros y
                // precio vienen del vale y sólo se muestran como información en
                // el formulario: se fuerzan aquí para que no puedan alterarse
                // manipulando la petición. Si hay vale, el tipo de carga es
                // siempre VALE, independientemente de lo que se haya enviado.
                $datos['id_vehiculo'] = $vale->id_vehiculo;
                $datos['id_conductor'] = $vale->id_conductor;
                $datos['id_grifo'] = $vale->id_grifo;
                $datos['id_tipo_combustible'] = $vale->id_tipo_combustible;
                $datos['litros'] = $vale->litros;
                $datos['precio'] = $vale->precio;
                $datos['tipo_carga'] = 'VALE';

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
