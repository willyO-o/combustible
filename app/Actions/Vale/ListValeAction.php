<?php

namespace App\Actions\Vale;

use App\Models\User;
use App\Models\Vale;
use Illuminate\Pagination\LengthAwarePaginator;

class ListValeAction
{
    /**
     * @param  bool  $aplicarAlcanceGestion  Cuando es `true`, un jefe de área
     *                                       (puro o combinado con `conductor`, pero sin `administrador`/`super-admin`)
     *                                       ve sólo los vales de vehículos asignados a sus áreas a cargo, en vez de
     *                                       todos los vales del sistema. `false` (comportamiento previo/por
     *                                       defecto, usado por la web) no aplica ningún filtro para jefe-area.
     */
    public function execute(array $filtros, User $user, int $perPage = 10, $soloPendientes = false, bool $aplicarAlcanceGestion = false): LengthAwarePaginator
    {

        $query = Vale::select([
            'id', 'nro_vale', 'gestion', 'fecha_emision', 'fecha_vencimiento', 'litros', 'precio', 'id_vehiculo', 'id_conductor', 'id_grifo', 'estado_vale', 'id_tipo_combustible', 'id_user',
        ])
            ->with([
                'vehiculo:id,nro_placa,codigo,marca,modelo,fotografia',
                'conductor:id',
                'conductor.persona:id,nombres,paterno,materno,ci,foto,fecha_nacimiento',
                'grifo:id,razon_social,ciudad',
            ]);

        if (! empty($filtros['nro_vale'])) {
            $query->where('nro_vale', $filtros['nro_vale'])
                ->orWhereRaw('CONCAT(nro_vale, "/", gestion) = ?', [$filtros['nro_vale']]);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha_emision', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha_emision', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['id_conductor'])) {
            $query->where('id_conductor', $filtros['id_conductor']);
        }

        if (! empty($filtros['estado_vale'])) {
            $query->where('estado_vale', $filtros['estado_vale']);
        }

        if ($soloPendientes) {
            // solo pendientes con el estado inferior a ahora el campo tien fecha y hora
            $query->where('estado_vale', 'PENDIENTE')
                ->where('fecha_vencimiento', '>=', now());

        }

        $this->aplicarRestriccionesPorRol($query, $user, $aplicarAlcanceGestion);

        return $query->orderBy('nro_vale', 'desc')
            ->orderBy('gestion', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Con `$aplicarAlcanceGestion = false` (web) el comportamiento es
     * exactamente el de antes: sólo se restringe a quien tenga rol
     * `conductor`, sin importar qué otros roles tenga combinados.
     *
     * Con `$aplicarAlcanceGestion = true` (API) se respeta el mismo criterio
     * de "conductor puro" ya usado en Api\V1\OperacionDiariaController: un
     * conductor que ADEMÁS es jefe-area, administrador o super-admin deja de
     * verse limitado a sus propios vales. Un jefe de área (puro o combinado,
     * pero sin administrador/super-admin) ve los vales de los vehículos
     * asignados a sus áreas a cargo — mismo criterio de
     * ValeController::restringirVehiculosPorAreaDeJefe (web). Administrador
     * y super-admin ven todos los vales del sistema, sin restricción (mismo
     * criterio que ParametrosController::colecciones()).
     */
    private function aplicarRestriccionesPorRol($query, User $user, bool $aplicarAlcanceGestion): void
    {
        $esConductorPuro = $aplicarAlcanceGestion
            ? $user->hasRole('conductor') && ! $user->hasAnyRole(['jefe-area', 'administrador', 'super-admin'])
            : $user->hasRole('conductor');

        if ($esConductorPuro) {
            $query->where('id_conductor', $user->id_persona);

            return;
        }

        if (! $aplicarAlcanceGestion) {
            return;
        }

        if ($user->hasRole('jefe-area') && ! $user->hasAnyRole(['administrador', 'super-admin'])) {
            $areas = $user->persona?->encargadoAreas()->pluck('id_area')->toArray() ?? [];

            $query->whereHas('vehiculo.areasAsignadas', function ($q) use ($areas) {
                $q->whereIn('area.id', $areas);
            });
        }
    }
}
