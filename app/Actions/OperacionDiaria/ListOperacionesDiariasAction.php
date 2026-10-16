<?php

namespace App\Actions\OperacionDiaria;

use App\Models\OperacionDiaria;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ListOperacionesDiariasAction
{
    public function execute(array $filtros, User $user, int $perPage = 10): LengthAwarePaginator
    {
        $query = OperacionDiaria::with(['conductor.persona', 'vehiculo', 'area', 'verificador']);

        if (! empty($filtros['nro_placa'])) {
            $query->whereHas('vehiculo', function ($q) use ($filtros) {
                $q->where('nro_placa', 'like', '%'.$filtros['nro_placa'].'%')
                    ->orWhere('codigo', 'like', '%'.$filtros['nro_placa'].'%');
            });
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha_inicio', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha_inicio', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['id_conductor'])) {
            $query->where('id_conductor', $filtros['id_conductor']);
        }

        if (! empty($filtros['estado_operacion'])) {
            $query->where('estado', $filtros['estado_operacion']);
        }

        $this->aplicarRestriccionesPorRol($query, $user);

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Precedencia (mismo criterio que ListValeAction): administrador/super-admin
     * (sin restricción) > jefe-area (operaciones de sus áreas a cargo, más las
     * suyas propias si también es conductor) > conductor (sólo sus propias
     * operaciones).
     */
    private function aplicarRestriccionesPorRol($query, User $user): void
    {
        if ($user->hasAnyRole(['administrador', 'super-admin'])) {
            return;
        }

        if ($user->hasRole('jefe-area')) {
            $idsArea = $user->persona?->encargadoAreas()->pluck('id_area')->toArray() ?? [];

            // Un jefe de área que además es conductor también registra las
            // operaciones de su propio vehículo aunque esté en otra área
            // (ver OperacionDiariaController::vehiculosDisponibles()): debe
            // seguir viéndolas en su listado.
            $query->where(function ($query) use ($idsArea, $user) {
                $query->whereIn('id_area', $idsArea)
                    // Cualquier usuario puede registrar cualquier vehículo:
                    // lo que registró él mismo siempre le aparece.
                    ->orWhere('id_usuario_registro', $user->id);

                if ($user->hasRole('conductor')) {
                    $query->orWhere('id_conductor', $user->id_persona);
                }
            });

            return;
        }

        if ($user->hasRole('conductor')) {
            $query->where(function ($query) use ($user) {
                $query->where('id_conductor', $user->id_persona)
                    ->orWhere('id_usuario_registro', $user->id);
            });
        }
    }
}
