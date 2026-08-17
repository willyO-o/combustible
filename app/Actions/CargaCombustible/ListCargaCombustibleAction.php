<?php

namespace App\Actions\CargaCombustible;

use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\User;
use App\Models\CargaCombustible;



class ListCargaCombustibleAction
{
    public function execute(array $filters, User $user, int $perPage = 10)
    {
        $query = CargaCombustible::with(['vehiculo', 'conductor.persona', 'grifo', 'tipoCombustible', 'vale']);

        if (!empty($filters['nro_placa'])) {
            $query->whereHas('vehiculo', function ($q) use ($filters) {
                $q->where('nro_placa', 'like', '%' . $filters['nro_placa'] . '%')
                ->orWhere('codigo', 'like', '%' . $filters['nro_placa'] . '%');
            });
        }
        if (!empty($filters['fecha_desde'])) {
            $query->whereDate('fecha_carga', '>=', $filters['fecha_desde']);
        }
        if (!empty($filters['fecha_hasta'])) {
            $query->whereDate('fecha_carga', '<=', $filters['fecha_hasta']);
        }
        if (!empty($filters['tipo_carga'])) {
            $query->where('tipo_carga', $filters['tipo_carga']);
        }
        if (!empty($filters['estado_carga'])) {
            $query->where('estado_carga', $filters['estado_carga']);
        }


        $this->aplicarRestriccionesPorRol($query, $user);

        return $query->orderBy('fecha_carga', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function aplicarRestriccionesPorRol($query, User $user): void
    {
        if ($user->hasRole('conductor')) {
            $query->where('id_conductor', $user->id_persona);
            return;
        }

        // if ($user->hasRole('jefe-area')) {
        //     $query->whereIn('id_area', $user->persona->encargadoAreas()->pluck('id_area'));
        // }
    }
}
