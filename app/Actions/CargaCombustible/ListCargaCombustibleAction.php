<?php

namespace App\Actions\CargaCombustible;

use App\Models\CargaCombustible;
use App\Models\User;

class ListCargaCombustibleAction
{
    public function execute(array $filters, User $user, int $perPage = 10)
    {
        $query = CargaCombustible::with(['vehiculo', 'conductor.persona', 'grifo', 'tipoCombustible', 'vale']);

        if (! empty($filters['nro_placa'])) {
            $query->whereHas('vehiculo', function ($q) use ($filters) {
                $q->where('nro_placa', 'like', '%'.$filters['nro_placa'].'%')
                    ->orWhere('codigo', 'like', '%'.$filters['nro_placa'].'%');
            });
        }
        if (! empty($filters['fecha_desde'])) {
            $query->whereDate('fecha_carga', '>=', $filters['fecha_desde']);
        }
        if (! empty($filters['fecha_hasta'])) {
            $query->whereDate('fecha_carga', '<=', $filters['fecha_hasta']);
        }
        if (! empty($filters['tipo_carga'])) {
            $query->where('tipo_carga', $filters['tipo_carga']);
        }
        if (! empty($filters['estado_carga'])) {
            $query->where('estado_carga', $filters['estado_carga']);
        }

        $this->aplicarRestriccionesPorRol($query, $user);

        return $query->orderBy('fecha_carga', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Precedencia (mismo criterio que ListValeAction): administrador/super-admin
     * (sin restricción) > jefe-area (cargas de vehículos de sus áreas a cargo)
     * > conductor (sólo sus propias cargas).
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
