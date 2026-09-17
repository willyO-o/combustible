<?php

namespace App\Http\Controllers;

use App\Actions\Auditoria\DetallarAuditoriaAction;
use App\Actions\Auditoria\ListAuditoriaAction;
use App\Actions\Auditoria\ResolverEtiquetasAuditoriaAction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use OwenIt\Auditing\Models\Audit;

/**
 * Bitácora de auditoría: quién creó, modificó, eliminó o restauró cada
 * registro del sistema, con el valor anterior y el nuevo.
 *
 * Los cambios los graba owen-it/laravel-auditing desde los propios modelos
 * (trait Auditable); acá sólo se consultan y se traducen a lenguaje humano.
 */
class AuditoriaController extends Controller
{
    public function index(
        Request $request,
        ListAuditoriaAction $listAuditoriaAction,
        ResolverEtiquetasAuditoriaAction $resolverEtiquetas
    ): Response {
        abort_unless($request->user()->can('auditoria.ver'), 403);

        $filters = $request->only(['q', 'auditable_type', 'event', 'user_id']);
        // Mismo criterio que el resto de listados con DateRangeFilter: la
        // primera carga ya llega filtrada por "Este mes" desde el servidor
        // (ver .ai/rules/pages.md), sin una segunda petición al montar.
        $filters['fecha_desde'] = $request->input('fecha_desde', now()->startOfMonth()->format('Y-m-d'));
        $filters['fecha_hasta'] = $request->input('fecha_hasta', now()->format('Y-m-d'));

        $auditorias = $listAuditoriaAction->execute($filters);

        return Inertia::render('Auditoria/Index', [
            // Inertia::scroll() sólo agrega metadata de merge para el
            // <InfiniteScroll> del listado en tarjetas (mobile); la tabla de
            // escritorio conserva su paginador numerado.
            'auditorias' => Inertia::scroll($auditorias),
            'filters' => $filters,
            'opciones' => $this->opcionesDeFiltro($resolverEtiquetas),
        ]);
    }

    /**
     * Detalle de una entrada para el modal del listado (antes/después,
     * contexto e historial del registro auditado).
     */
    public function detalle(Request $request, Audit $audit, DetallarAuditoriaAction $detallarAuditoriaAction): JsonResponse
    {
        abort_unless($request->user()->can('auditoria.ver'), 403);

        return response()->json($detallarAuditoriaAction->execute($audit));
    }

    /**
     * Sólo se ofrecen módulos, eventos y usuarios que realmente aparecen en la
     * bitácora: un filtro que no puede devolver nada no ayuda a nadie.
     *
     * @return array{modulos: array<int, array<string, mixed>>, eventos: array<int, array<string, mixed>>, usuarios: array<int, array<string, mixed>>}
     */
    private function opcionesDeFiltro(ResolverEtiquetasAuditoriaAction $resolverEtiquetas): array
    {
        $modulos = Audit::query()
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->map(fn (string $tipo) => $resolverEtiquetas->modelo($tipo))
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $eventos = Audit::query()
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->map(fn (string $evento) => $resolverEtiquetas->evento($evento))
            ->values();

        $idsUsuarios = Audit::query()->whereNotNull('user_id')->distinct()->pluck('user_id');

        $usuarios = User::whereIn('id', $idsUsuarios)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'label' => $usuario->name,
                'email' => $usuario->email,
            ])
            ->values();

        return [
            'modulos' => $modulos->all(),
            'eventos' => $eventos->all(),
            'usuarios' => $usuarios->all(),
        ];
    }
}
