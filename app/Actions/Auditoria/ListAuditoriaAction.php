<?php

namespace App\Actions\Auditoria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Models\Audit;

/**
 * Listado paginado de la bitácora de auditoría, ya traducido a texto legible.
 */
class ListAuditoriaAction
{
    public function __construct(private ResolverEtiquetasAuditoriaAction $resolverEtiquetas) {}

    /**
     * @param  array{auditable_type?: ?string, event?: ?string, user_id?: ?int, fecha_desde?: ?string, fecha_hasta?: ?string, q?: ?string}  $filtros
     */
    public function execute(array $filtros, int $perPage = 15): LengthAwarePaginator
    {
        $query = Audit::query()->with('user');

        if (! empty($filtros['auditable_type'])) {
            $query->where('auditable_type', $filtros['auditable_type']);
        }

        if (! empty($filtros['event'])) {
            $query->where('event', $filtros['event']);
        }

        if (! empty($filtros['user_id'])) {
            $query->where('user_id', $filtros['user_id']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['q'])) {
            $this->aplicarBusqueda($query, trim($filtros['q']));
        }

        $auditorias = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $descriptores = $this->descriptoresDe($auditorias->getCollection());

        return $auditorias->through(fn (Audit $audit) => $this->mapear($audit, $descriptores));
    }

    /**
     * Texto libre: el número/identificador del registro auditado, el nombre o
     * correo de quien hizo el cambio, o cualquier valor que haya quedado
     * guardado en el "antes"/"después" (así se puede buscar por una placa o un
     * nro. de vale sin saber en qué módulo se registró).
     */
    private function aplicarBusqueda(Builder $query, string $texto): void
    {
        $query->where(function (Builder $query) use ($texto) {
            $query->where('old_values', 'like', "%{$texto}%")
                ->orWhere('new_values', 'like', "%{$texto}%")
                ->orWhere('tags', 'like', "%{$texto}%")
                ->orWhereHas('user', function (Builder $query) use ($texto) {
                    $query->where('name', 'like', "%{$texto}%")
                        ->orWhere('email', 'like', "%{$texto}%");
                });

            if (is_numeric($texto)) {
                $query->orWhere('auditable_id', $texto);
            }
        });
    }

    /**
     * Describe de una sola vez los registros auditados de la página actual:
     * una consulta por módulo presente, no una por fila.
     *
     * @param  Collection<int, Audit>  $auditorias
     * @return array<class-string, array<int|string, string>>
     */
    private function descriptoresDe(Collection $auditorias): array
    {
        $idsPorTipo = $auditorias
            ->groupBy('auditable_type')
            ->map(fn (Collection $grupo) => $grupo->pluck('auditable_id')->unique()->values()->all())
            ->all();

        return $this->resolverEtiquetas->descriptores($idsPorTipo);
    }

    /**
     * @param  array<class-string, array<int|string, string>>  $descriptores
     * @return array<string, mixed>
     */
    private function mapear(Audit $audit, array $descriptores): array
    {
        $ocultos = config('auditoria.ocultos', []);
        $campos = array_values(array_diff(
            array_keys($audit->event === 'deleted' ? ($audit->old_values ?? []) : ($audit->new_values ?? [])),
            $ocultos
        ));

        return [
            'id' => $audit->id,
            'evento' => $this->resolverEtiquetas->evento($audit->event),
            'modelo' => $this->resolverEtiquetas->modelo($audit->auditable_type),
            'registro' => [
                'id' => $audit->auditable_id,
                'descriptor' => $descriptores[$audit->auditable_type][$audit->auditable_id] ?? null,
            ],
            'usuario' => $audit->user ? [
                'id' => $audit->user->id,
                'nombre' => $audit->user->name,
                'email' => $audit->user->email,
                'foto_url' => $audit->user->foto_url,
            ] : null,
            'campos' => array_map(fn (string $campo) => $this->resolverEtiquetas->atributo($campo), $campos),
            'total_campos' => count($campos),
            'ip' => $audit->ip_address,
            'fecha' => $audit->created_at?->format('Y-m-d H:i:s'),
            'fecha_humana' => $audit->created_at?->diffForHumans(),
        ];
    }
}
