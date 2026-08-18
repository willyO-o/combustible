<?php

namespace App\Actions\SolicitudMantenimiento;

use App\Models\SolicitudMantenimiento;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ListSolicitudesMantenimientoAction
{
    public function execute(array $filters, User $user, int $perPage = 10): LengthAwarePaginator
    {
        $query = SolicitudMantenimiento::with(['vehiculo', 'conductor.persona', 'usuarioRegistra', 'planMantenimiento']);

        if (! empty($filters['estado'])) {
            $query->where('estado', $filters['estado']);
        }

        if (! empty($filters['tipo_mantenimiento'])) {
            $query->where('tipo_mantenimiento', $filters['tipo_mantenimiento']);
        }

        if (! empty($filters['id_vehiculo'])) {
            $query->where('id_vehiculo', $filters['id_vehiculo']);
        }

        $this->aplicarRestriccionesPorRol($query, $user);

        return $query->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function aplicarRestriccionesPorRol($query, User $user): void
    {
        if ($user->hasRole('conductor')) {
            $query->where('id_conductor', $user->id_persona);
        }
    }
}
