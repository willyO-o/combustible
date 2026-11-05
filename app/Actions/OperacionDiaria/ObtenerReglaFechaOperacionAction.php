<?php

namespace App\Actions\OperacionDiaria;

use App\Http\Requests\OperacionStoreRequest;
use App\Models\HabilitacionRegularizacion;
use App\Models\ParametrosEmpresa;
use App\Models\User;

class ObtenerReglaFechaOperacionAction
{
    /**
     * Qué fechas de inicio puede registrar el usuario en una operación
     * diaria. Única fuente de la regla: la valida OperacionStoreRequest (web
     * y API) y la reciben el formulario web y la app para limitar su
     * calendario.
     *
     * - Modo transición (sin fecha de modo estricto, o aún no llega): jefe de
     *   área, administrador y super-admin, cualquier fecha (`libre`); el
     *   resto, hoy o ayer.
     * - Modo estricto: todos, sólo hoy; ayer sólo en turno NOCHE; nunca una
     *   fecha futura.
     * - En ambos modos, las habilitaciones vigentes del encargado suman sus
     *   días (siempre pasados) a lo permitido.
     *
     * @return array{
     *     modo: 'transicion'|'estricto',
     *     estricto_desde: ?string,
     *     libre: bool,
     *     dias_atras: int,
     *     habilitaciones: list<array{id: int, desde: string, hasta: string, vigente_hasta: string, otorgada_por: ?string, motivo: string}>
     * }
     */
    public function execute(User $user): array
    {
        $estrictoDesde = ParametrosEmpresa::regularizacionEstrictaDesde();
        $estricto = $estrictoDesde?->lte(today()) ?? false;

        $habilitaciones = $user->habilitacionesRegularizacion()
            ->whereNull('revocada_at')
            ->where('vigente_hasta', '>', now())
            ->with('otorgadaPor.persona')
            ->orderBy('fecha_desde')
            ->get()
            ->map(fn (HabilitacionRegularizacion $habilitacion) => [
                'id' => $habilitacion->id,
                'desde' => $habilitacion->fecha_desde->toDateString(),
                'hasta' => $habilitacion->fecha_hasta->toDateString(),
                'vigente_hasta' => $habilitacion->vigente_hasta->toIso8601String(),
                'otorgada_por' => $habilitacion->otorgadaPor?->nombreVisible(),
                'motivo' => $habilitacion->motivo,
            ])
            ->values()
            ->all();

        return [
            'modo' => $estricto ? 'estricto' : 'transicion',
            'estricto_desde' => $estrictoDesde?->toDateString(),
            'libre' => ! $estricto && $user->puedeRegularizarOperaciones(),
            // Días hacia atrás sin habilitación: en estricto, sólo la noche
            // de ayer (lo decide el turno, ver OperacionStoreRequest).
            'dias_atras' => $estricto ? 1 : OperacionStoreRequest::DIAS_ATRAS_SIN_REGULARIZAR,
            'habilitaciones' => $habilitaciones,
        ];
    }
}
