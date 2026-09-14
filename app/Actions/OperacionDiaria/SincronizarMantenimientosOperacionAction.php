<?php

namespace App\Actions\OperacionDiaria;

use App\Models\OperacionDiaria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SincronizarMantenimientosOperacionAction
{
    /**
     * Directorio (disco 'public') donde se guarda la evidencia fotográfica
     * de los controles de mantenimiento, ya convertida a .webp.
     */
    private const DIRECTORIO_EVIDENCIA = 'operacion-diaria/mantenimiento';

    /**
     * Reemplaza los controles de mantenimiento de la operación por los
     * enviados. Sólo se persisten los tipos que el operador efectivamente
     * cargó (un valor, la casilla marcada, o una evidencia fotográfica): los
     * demás no aplican a esta operación y no dejan registro.
     *
     * La evidencia de un control que ya la tenía se conserva automáticamente
     * si no llega un archivo nuevo para ese mismo id_tipo_mantenimiento (no
     * hace falta que el cliente la reenvíe); `eliminar_evidencia: true` la
     * borra sin reemplazarla. Las evidencias de controles que quedan sin
     * registrar tras esta sincronización (se destildaron, o se quitaron del
     * envío) se borran del disco para no dejar archivos huérfanos.
     *
     * @param  array<int, array{id_tipo_mantenimiento: int|string, valor?: mixed, realizado?: mixed, evidencia?: UploadedFile|null, eliminar_evidencia?: mixed}>  $mantenimientos
     */
    public function execute(OperacionDiaria $operacionDiaria, array $mantenimientos): void
    {
        // Se lee ANTES del detach(): es la única forma de conservar la
        // evidencia de un control que se reenvía sin archivo nuevo (el
        // cliente nunca reenvía la ruta ya guardada, sólo un File cuando
        // cambia).
        $evidenciasExistentes = $operacionDiaria->mantenimientosOperacion()
            ->get()
            ->mapWithKeys(fn ($tipo) => [$tipo->id => $tipo->pivot->evidencia]);

        $operacionDiaria->mantenimientosOperacion()->detach();

        $tiposConservados = [];

        foreach ($mantenimientos as $mantenimiento) {
            $idTipo = $mantenimiento['id_tipo_mantenimiento'];
            $valor = $mantenimiento['valor'] ?? null;
            $realizado = $mantenimiento['realizado'] ?? null;
            $archivoEvidencia = $mantenimiento['evidencia'] ?? null;

            $evidencia = $evidenciasExistentes->get($idTipo);

            if ($archivoEvidencia instanceof UploadedFile) {
                if ($evidencia) {
                    Storage::disk('public')->delete($evidencia);
                }
                $evidencia = convertirImagenAWebp($archivoEvidencia, self::DIRECTORIO_EVIDENCIA);
            } elseif (! empty($mantenimiento['eliminar_evidencia']) && $evidencia) {
                Storage::disk('public')->delete($evidencia);
                $evidencia = null;
            }

            if (($valor === null || $valor === '') && $realizado === null && ! $evidencia) {
                continue;
            }

            $tiposConservados[] = $idTipo;

            $operacionDiaria->mantenimientosOperacion()->attach($idTipo, [
                'valor' => ($valor === null || $valor === '') ? null : $valor,
                'realizado' => $realizado,
                'evidencia' => $evidencia,
            ]);
        }

        // Evidencias de controles que ya no quedaron adjuntos (se
        // destildaron, o directamente no vinieron en este envío).
        foreach ($evidenciasExistentes as $idTipo => $evidencia) {
            if ($evidencia && ! in_array($idTipo, $tiposConservados, true)) {
                Storage::disk('public')->delete($evidencia);
            }
        }
    }
}
