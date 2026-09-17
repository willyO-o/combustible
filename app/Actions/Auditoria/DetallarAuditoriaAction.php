<?php

namespace App\Actions\Auditoria;

use OwenIt\Auditing\Models\Audit;

/**
 * Arma el detalle de UNA entrada de la bitácora: qué campos cambiaron, con su
 * valor antes y después ya traducidos, quién y desde dónde hizo el cambio, y
 * el historial del mismo registro para poner el cambio en contexto.
 */
class DetallarAuditoriaAction
{
    private const LIMITE_HISTORIAL = 10;

    public function __construct(private ResolverEtiquetasAuditoriaAction $resolverEtiquetas) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Audit $audit): array
    {
        $audit->loadMissing('user');

        $ocultos = config('auditoria.ocultos', []);
        $antes = array_diff_key($audit->old_values ?? [], array_flip($ocultos));
        $despues = array_diff_key($audit->new_values ?? [], array_flip($ocultos));

        $etiquetas = $this->resolverEtiquetas->execute(
            $this->resolverEtiquetas->idsPorColumna([$antes, $despues])
        );

        $descriptores = $this->resolverEtiquetas->descriptores([
            $audit->auditable_type => [$audit->auditable_id],
        ]);

        return [
            'id' => $audit->id,
            'evento' => $this->resolverEtiquetas->evento($audit->event),
            'modelo' => $this->resolverEtiquetas->modelo($audit->auditable_type),
            'registro' => [
                'id' => $audit->auditable_id,
                'descriptor' => $descriptores[$audit->auditable_type][$audit->auditable_id] ?? null,
                'existe' => $this->resolverEtiquetas->registroExiste($audit->auditable_type, $audit->auditable_id),
            ],
            'usuario' => $this->usuario($audit),
            'contexto' => [
                'fecha' => $audit->created_at?->format('d/m/Y H:i:s'),
                'fecha_humana' => $audit->created_at?->diffForHumans(),
                'ip' => $audit->ip_address,
                'user_agent' => $audit->user_agent,
                'url' => $audit->url,
                'tags' => $audit->tags,
            ],
            'cambios' => $this->cambios($audit, $antes, $despues, $etiquetas),
            'historial' => $this->historial($audit),
        ];
    }

    /**
     * Una fila por campo tocado, con su valor antes y después. En una creación
     * sólo hay "después"; en una eliminación sólo "antes".
     *
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $despues
     * @param  array<string, array<int|string, string>>  $etiquetas
     * @return array<int, array<string, mixed>>
     */
    private function cambios(Audit $audit, array $antes, array $despues, array $etiquetas): array
    {
        $columnas = array_values(array_unique([...array_keys($antes), ...array_keys($despues)]));
        sort($columnas);

        return array_map(function (string $columna) use ($audit, $antes, $despues, $etiquetas) {
            $valorAntes = $this->resolverEtiquetas->valor($columna, $antes[$columna] ?? null, $etiquetas);
            $valorDespues = $this->resolverEtiquetas->valor($columna, $despues[$columna] ?? null, $etiquetas);

            return [
                'campo' => $columna,
                'label' => $this->resolverEtiquetas->atributo($columna),
                'antes' => $valorAntes['texto'],
                'despues' => $valorDespues['texto'],
                'tipo' => $valorDespues['tipo'] === 'vacio' ? $valorAntes['tipo'] : $valorDespues['tipo'],
                // En un "updated" el paquete sólo guarda lo que cambió, pero en
                // un "created"/"deleted" guarda el registro entero: ahí no hay
                // comparación que resaltar.
                'modificado' => $audit->event === 'updated' && $valorAntes['texto'] !== $valorDespues['texto'],
            ];
        }, $columnas);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function usuario(Audit $audit): ?array
    {
        if (! $audit->user) {
            return null;
        }

        return [
            'id' => $audit->user->id,
            'nombre' => $audit->user->name,
            'email' => $audit->user->email,
            'foto_url' => $audit->user->foto_url,
            'roles' => method_exists($audit->user, 'getRoleNames')
                ? $audit->user->getRoleNames()->values()->all()
                : [],
        ];
    }

    /**
     * Las últimas entradas del MISMO registro auditado, para leer el cambio en
     * contexto (quién lo creó, qué pasó antes y después).
     *
     * @return array<int, array<string, mixed>>
     */
    private function historial(Audit $audit): array
    {
        return Audit::query()
            ->with('user')
            ->where('auditable_type', $audit->auditable_type)
            ->where('auditable_id', $audit->auditable_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMITE_HISTORIAL)
            ->get()
            ->map(fn (Audit $entrada) => [
                'id' => $entrada->id,
                'evento' => $this->resolverEtiquetas->evento($entrada->event),
                'usuario' => $entrada->user?->name,
                'fecha' => $entrada->created_at?->format('d/m/Y H:i'),
                'fecha_humana' => $entrada->created_at?->diffForHumans(),
                'es_actual' => $entrada->id === $audit->id,
            ])
            ->all();
    }
}
