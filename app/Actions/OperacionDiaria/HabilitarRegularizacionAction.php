<?php

namespace App\Actions\OperacionDiaria;

use App\Events\RegularizacionHabilitada;
use App\Models\HabilitacionRegularizacion;
use App\Models\User;
use Carbon\CarbonInterface;

class HabilitarRegularizacionAction
{
    /**
     * Habilita a un usuario a registrar operaciones diarias de los días
     * [fecha_desde, fecha_hasta] hasta $vigenteHasta, y le avisa
     * (RegularizacionHabilitada -> notificación en la campana y push).
     *
     * @param  array{id_usuario: int|string, fecha_desde: string, fecha_hasta: string, motivo: string}  $datos
     */
    public function execute(User $encargado, array $datos, CarbonInterface $vigenteHasta): HabilitacionRegularizacion
    {
        $habilitacion = HabilitacionRegularizacion::create([
            'id_usuario' => $datos['id_usuario'],
            'fecha_desde' => $datos['fecha_desde'],
            'fecha_hasta' => $datos['fecha_hasta'],
            'vigente_hasta' => $vigenteHasta,
            'motivo' => trim($datos['motivo']),
            'id_usuario_otorga' => $encargado->id,
        ]);

        RegularizacionHabilitada::dispatch($habilitacion);

        return $habilitacion;
    }
}
