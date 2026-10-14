<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cargo del personal (Técnico, Analista, Supervisor, ...). Catálogo único
 * para todas las personas, independiente de los roles del sistema.
 */
#[Fillable(['cargo', 'estado_cargo'])]

class Cargo extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cargo';

    public function personas()
    {
        return $this->hasMany(Persona::class, 'id_cargo');
    }

    /**
     * Opciones del selector de cargo (Personas y Conductores): los ACTIVOS
     * más, al editar, el que ya tiene la persona aunque se haya desactivado
     * (mismo criterio que la validación de id_cargo), para que siga visible.
     *
     * @return Collection<int, Cargo>
     */
    public static function seleccionables(?int $incluirId = null): Collection
    {
        return static::query()
            ->where(fn ($query) => $query->where('estado_cargo', 'ACTIVO')
                ->when($incluirId, fn ($query) => $query->orWhere('id', $incluirId)))
            ->orderBy('cargo')
            ->get(['id', 'cargo', 'estado_cargo']);
    }
}
