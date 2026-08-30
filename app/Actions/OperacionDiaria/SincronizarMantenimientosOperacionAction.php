<?php

namespace App\Actions\OperacionDiaria;

use App\Models\OperacionDiaria;

class SincronizarMantenimientosOperacionAction
{
    /**
     * Reemplaza los controles de mantenimiento de la operación por los
     * enviados. Sólo se persisten los tipos que el operador efectivamente
     * cargó (un valor o la casilla marcada): los demás no aplican a esta
     * operación y no dejan registro.
     *
     * @param  array<int, array{id_tipo_mantenimiento: int|string, valor?: mixed, realizado?: mixed}>  $mantenimientos
     */
    public function execute(OperacionDiaria $operacionDiaria, array $mantenimientos): void
    {
        $operacionDiaria->mantenimientosOperacion()->detach();

        foreach ($mantenimientos as $mantenimiento) {
            $valor = $mantenimiento['valor'] ?? null;
            $realizado = $mantenimiento['realizado'] ?? null;

            if (($valor === null || $valor === '') && $realizado === null) {
                continue;
            }

            $operacionDiaria->mantenimientosOperacion()->attach($mantenimiento['id_tipo_mantenimiento'], [
                'valor' => ($valor === null || $valor === '') ? null : $valor,
                'realizado' => $realizado,
            ]);
        }
    }
}
