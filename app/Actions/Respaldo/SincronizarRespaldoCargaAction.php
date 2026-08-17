<?php

namespace App\Actions\Respaldo;

use App\Models\RespaldoDigital;
use App\Models\CargaCombustible;

class SincronizarRespaldoCargaAction
{
    public function execute( $request, CargaCombustible $carga)
    {

        //capturar respaldos
        $respaldos = $request->input('respaldos', []);


        foreach ($request->file('respaldos', []) as $index => $archivo) {
            if (!$archivo) {
                continue;
            }


            $tipoArchivo = str_starts_with($archivo["archivo"]->getMimeType(), 'image/') ? 'IMAGEN' : 'PDF';
            $ruta        = $archivo["archivo"]->store("respaldos/{$tipoArchivo}", 'public');

            RespaldoDigital::create([
                'ruta_respaldo'        => $ruta,
                'tipo_respaldo'        => $respaldos[$index]['tipo'] ?? 'OTRO',
                'tipo_archivo'         => $tipoArchivo,
                'id_carga_combustible' => $carga->id,
            ]);
        }
    }
}
