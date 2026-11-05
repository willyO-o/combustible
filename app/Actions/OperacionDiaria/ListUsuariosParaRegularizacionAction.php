<?php

namespace App\Actions\OperacionDiaria;

use App\Models\User;
use Illuminate\Support\Collection;

class ListUsuariosParaRegularizacionAction
{
    /**
     * Usuarios ACTIVO a los que se puede habilitar una regularización: los
     * que pueden registrar operaciones diarias (permiso operacion-diaria.crear,
     * directo o por rol) y el super-admin (que no necesita permisos). Se
     * resuelve en una sola consulta.
     *
     * $soloId limita la búsqueda a ese usuario (para validarlo sin traer la
     * lista entera).
     *
     * @return Collection<int, array{id: int, label: string, email: string}>
     */
    public function execute(?int $soloId = null): Collection
    {
        $tienePermiso = fn ($query) => $query->where('name', 'operacion-diaria.crear');

        return User::query()
            ->with('persona:id,nombres,paterno,materno')
            ->where('estado_usuario', 'ACTIVO')
            ->when($soloId, fn ($query) => $query->whereKey($soloId))
            ->where(fn ($query) => $query
                ->whereHas('permissions', $tienePermiso)
                ->orWhereHas('roles', fn ($roles) => $roles
                    ->where('name', 'super-admin')
                    ->orWhereHas('permissions', $tienePermiso)))
            ->get(['id', 'name', 'email', 'id_persona'])
            ->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'label' => $usuario->nombreVisible(),
                'email' => $usuario->email,
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
