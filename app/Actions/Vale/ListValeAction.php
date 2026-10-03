<?php

namespace App\Actions\Vale;

use App\Models\User;
use App\Models\Vale;
use Illuminate\Pagination\LengthAwarePaginator;

class ListValeAction
{
    /**
     * El alcance depende del rol (web y API): administrador/super-admin ven
     * todo y prevalecen sobre cualquier otro rol combinado; un jefe de área
     * ve los vales de vehículos de sus áreas a cargo; un conductor puro ve
     * sólo los suyos.
     */
    public function execute(array $filtros, User $user, int $perPage = 10, $soloPendientes = false): LengthAwarePaginator
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
            // Agrupado en un closure: un orWhere() suelto se combina con AND
            // de menor precedencia que el resto de filtros (fechas, estado,
            // restricción por rol), así que sin agrupar un nro_vale coincidente
            // podía devolver vales que no cumplían el resto de condiciones.
            $query->where(function ($query) use ($filtros) {
                $query->where('nro_vale', $filtros['nro_vale'])
                    ->orWhereRaw('CONCAT(nro_vale, "/", gestion) = ?', [$filtros['nro_vale']]);
            });
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

        $this->aplicarRestriccionesPorRol($query, $user);

        return $query->orderBy('nro_vale', 'desc')
            ->orderBy('gestion', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Precedencia: administrador/super-admin (sin restricción) > jefe-area
     * (vales de vehículos de sus áreas a cargo, mismo criterio de
     * ValeController::restringirVehiculosPorAreaDeJefe) > conductor (sólo
     * sus propios vales).
     */
    private function aplicarRestriccionesPorRol($query, User $user): void
    {
        if ($user->hasAnyRole(['administrador', 'super-admin'])) {
            return;
        }

        if ($user->hasRole('jefe-area')) {
            $areas = $user->persona?->encargadoAreas()->pluck('id_area')->toArray() ?? [];

            $query->whereHas('vehiculo.areasAsignadas', function ($q) use ($areas) {
                $q->whereIn('area.id', $areas);
            });

            return;
        }

        if ($user->hasRole('conductor')) {
            $query->where('id_conductor', $user->id_persona);
        }
    }
}
