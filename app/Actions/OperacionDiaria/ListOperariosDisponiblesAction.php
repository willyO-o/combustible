<?php

namespace App\Actions\OperacionDiaria;

use App\Models\Conductor;
use Illuminate\Support\Collection;

class ListOperariosDisponiblesAction
{
    /**
     * Operarios (conductores) que se pueden elegir al registrar una operación
     * diaria: TODOS los conductores ACTIVO, sin filtrar por la asignación al
     * vehículo ni por el rol del usuario (a pedido del usuario, 2026-10-16: la
     * asignación conductor-vehículo todavía no es confiable).
     *
     * $incluirIdConductor agrega un conductor aunque ya no esté activo (el de
     * una operación existente que se está editando), para que el combo no lo
     * descarte en silencio.
     *
     * @return Collection<int, array{id: int, label: string, meta: array{ci: string|null, nombre_completo: string}}>
     */
    public function execute(?int $incluirIdConductor = null): Collection
    {
        return Conductor::query()
            ->with('persona:id,nombres,paterno,materno,ci')
            ->where(function ($query) use ($incluirIdConductor) {
                $query->where('estado_conductor', 'ACTIVO');

                if ($incluirIdConductor) {
                    $query->orWhere('id', $incluirIdConductor);
                }
            })
            ->whereHas('persona')
            ->get()
            ->map(fn (Conductor $conductor) => [
                'id' => $conductor->id,
                'label' => "{$conductor->persona->nombre_completo} (CI: {$conductor->persona->ci})"
                    .($conductor->estado_conductor !== 'ACTIVO' ? ' — inactivo' : ''),
                'meta' => [
                    'ci' => $conductor->persona->ci,
                    'nombre_completo' => $conductor->persona->nombre_completo,
                ],
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }
}
