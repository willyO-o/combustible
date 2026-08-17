<?php

namespace App\Actions\Vale;

use App\Models\Vale;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ListValeAction
{
    public function execute(array $filtros, User $user, int $perPage = 10, $soloPendientes=false): LengthAwarePaginator
    {

        $query = Vale::select([
            'id', 'nro_vale', 'gestion', 'fecha_emision', 'fecha_vencimiento', 'litros', 'precio', 'id_vehiculo', 'id_conductor', 'id_grifo', 'estado_vale', 'id_tipo_combustible', 'id_user'
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

        if($soloPendientes){
            //solo pendientes con el estado inferior a ahora el campo tien fecha y hora
            $query->where("estado_vale", "PENDIENTE")
            ->where("fecha_vencimiento", ">=", now());

        }


        $this->aplicarRestriccionesPorRol($query, $user);

        return $query->orderBy('nro_vale', 'desc')
            ->orderBy('gestion', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }


    private function aplicarRestriccionesPorRol($query, User $user)
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
