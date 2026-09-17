<?php

namespace App\Actions\Auditoria;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Traduce lo que guarda owen-it/laravel-auditing (nombres de clase, nombres de
 * columna y llaves foráneas en crudo) a texto legible, resolviendo las llaves
 * foráneas en lote: una consulta por modelo relacionado y no una por fila.
 *
 * El mapa de nombres vive en config/auditoria.php.
 */
class ResolverEtiquetasAuditoriaAction
{
    /**
     * Caché por request de los registros ya resueltos. La llave incluye los
     * campos y el formato, no sólo el modelo: un mismo registro puede pedirse
     * con dos descripciones distintas (la del módulo y la de una llave foránea
     * que lo referencia) y no deben pisarse entre sí.
     *
     * @var array<string, array<int|string, string>>
     */
    private array $cache = [];

    /**
     * Resuelve llaves foráneas a su etiqueta humana.
     *
     * @param  array<string, array<int, int|string>>  $idsPorColumna  ['id_vehiculo' => [1, 2], …]
     * @return array<string, array<int|string, string>> ['id_vehiculo' => [1 => 'V-01 — 1234ABC'], …]
     */
    public function execute(array $idsPorColumna): array
    {
        $resueltas = [];

        foreach ($idsPorColumna as $columna => $ids) {
            $relacion = $this->relacion($columna);

            if (! $relacion) {
                continue;
            }

            $resueltas[$columna] = $this->etiquetasDeModelo(
                $relacion['modelo'],
                $relacion['campos'],
                $ids,
                $relacion['formato'] ?? null
            );
        }

        return $resueltas;
    }

    /**
     * Describe registros auditados ("Vale 123 · 2026", "V-01 — 1234ABC") para
     * un lote de auditorías, agrupadas por tipo.
     *
     * @param  array<class-string, array<int, int|string>>  $idsPorTipo
     * @return array<class-string, array<int|string, string>>
     */
    public function descriptores(array $idsPorTipo): array
    {
        $descriptores = [];

        foreach ($idsPorTipo as $tipo => $ids) {
            $campos = config("auditoria.modelos.{$tipo}.descriptor", []);

            if (! $campos || ! class_exists($tipo)) {
                continue;
            }

            $descriptores[$tipo] = $this->etiquetasDeModelo(
                $tipo,
                $campos,
                $ids,
                config("auditoria.modelos.{$tipo}.formato")
            );
        }

        return $descriptores;
    }

    /**
     * Nombre humano de un módulo (clase auditada).
     *
     * @return array{clave: class-string|string, label: string, icono: string}
     */
    public function modelo(string $tipo): array
    {
        return [
            'clave' => $tipo,
            'label' => config("auditoria.modelos.{$tipo}.label", Str::headline(class_basename($tipo))),
            'icono' => config("auditoria.modelos.{$tipo}.icono", 'ri-database-2-line'),
        ];
    }

    /**
     * Nombre humano de un evento de Eloquent.
     *
     * @return array{clave: string, label: string, icono: string, color: string}
     */
    public function evento(string $evento): array
    {
        return [
            'clave' => $evento,
            'label' => config("auditoria.eventos.{$evento}.label", Str::headline($evento)),
            'icono' => config("auditoria.eventos.{$evento}.icono", 'ri-history-line'),
            'color' => config("auditoria.eventos.{$evento}.color", 'secondary'),
        ];
    }

    /**
     * Nombre humano de una columna.
     */
    public function atributo(string $columna): string
    {
        return config("auditoria.atributos.{$columna}", Str::headline($columna));
    }

    /**
     * Da formato de presentación a un valor auditado.
     *
     * @param  array<string, array<int|string, string>>  $etiquetas  Resultado de execute(), para las llaves foráneas.
     * @return array{texto: ?string, tipo: string}
     */
    public function valor(string $columna, mixed $valor, array $etiquetas = []): array
    {
        if ($valor === null || $valor === '') {
            return ['texto' => null, 'tipo' => 'vacio'];
        }

        if ($this->relacion($columna)) {
            $etiqueta = $etiquetas[$columna][$valor] ?? null;

            return [
                'texto' => $etiqueta ? "{$etiqueta} (#{$valor})" : "#{$valor} (registro no encontrado)",
                'tipo' => 'relacion',
            ];
        }

        if (is_bool($valor) || in_array($columna, config('auditoria.booleanos', []), true)) {
            return ['texto' => filter_var($valor, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No', 'tipo' => 'booleano'];
        }

        if (is_string($valor) && $this->esJson($valor)) {
            return [
                'texto' => json_encode(json_decode($valor, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'tipo' => 'json',
            ];
        }

        if ($this->esColumnaDeFecha($columna) && ($fecha = $this->fecha($valor))) {
            return ['texto' => $fecha, 'tipo' => 'fecha'];
        }

        if (is_numeric($valor)) {
            return ['texto' => (string) $valor, 'tipo' => 'numero'];
        }

        return ['texto' => (string) $valor, 'tipo' => 'texto'];
    }

    /**
     * Llaves foráneas presentes en un conjunto de valores auditados, listas
     * para pasarse a execute() en una sola tanda.
     *
     * @param  array<int, array<string, mixed>>  $conjuntos  Varios arrays de valores (old_values / new_values).
     * @return array<string, array<int, int|string>>
     */
    public function idsPorColumna(array $conjuntos): array
    {
        $ids = [];

        foreach ($conjuntos as $valores) {
            foreach ($valores as $columna => $valor) {
                if ($valor === null || $valor === '' || ! $this->relacion($columna)) {
                    continue;
                }

                $ids[$columna][] = $valor;
            }
        }

        return array_map(fn (array $valores) => array_values(array_unique($valores)), $ids);
    }

    /**
     * ¿El registro auditado sigue existiendo? (un borrado lógico cuenta como
     * existente: se puede restaurar y sus datos siguen ahí).
     */
    public function registroExiste(string $tipo, int|string|null $id): bool
    {
        if (! class_exists($tipo) || $id === null) {
            return false;
        }

        /** @var Model $instancia */
        $instancia = new $tipo;
        $query = $instancia->newQuery();

        if (in_array(SoftDeletes::class, class_uses_recursive($tipo), true)) {
            $query->withTrashed();
        }

        return $query->whereKey($id)->exists();
    }

    /**
     * @return array{modelo: class-string, campos: array<int, string>}|null
     */
    private function relacion(string $columna): ?array
    {
        $relacion = config("auditoria.relaciones.{$columna}");

        return $relacion && class_exists($relacion['modelo']) ? $relacion : null;
    }

    /**
     * Una consulta por modelo: trae los registros pedidos (incluidos los
     * borrados lógicamente, que son justo los que interesa poder nombrar en
     * una auditoría) y arma la etiqueta concatenando los campos indicados.
     *
     * @param  class-string  $modelo
     * @param  array<int, string>  $campos
     * @param  array<int, int|string>  $ids
     * @param  string|null  $formato  Plantilla opcional con marcadores {campo} ("N° {nro_vale}/{gestion}").
     * @return array<int|string, string>
     */
    private function etiquetasDeModelo(string $modelo, array $campos, array $ids, ?string $formato = null): array
    {
        $clave = $modelo.'|'.implode(',', $campos).'|'.$formato;
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id !== null && $id !== '')));
        $pendientes = array_values(array_diff($ids, array_keys($this->cache[$clave] ?? [])));

        if ($pendientes) {
            /** @var Model $instancia */
            $instancia = new $modelo;
            $query = $instancia->newQuery();

            if (in_array(SoftDeletes::class, class_uses_recursive($modelo), true)) {
                $query->withTrashed();
            }

            $relaciones = array_values(array_filter(array_map(
                fn (string $campo) => Str::contains($campo, '.') ? Str::beforeLast($campo, '.') : null,
                $campos
            )));

            if ($relaciones) {
                $query->with(array_unique($relaciones));
            }

            $registros = $query->whereIn($instancia->getKeyName(), $pendientes)->get();

            foreach ($registros as $registro) {
                $this->cache[$clave][$registro->getKey()] = $this->componerEtiqueta($registro, $campos, $formato);
            }
        }

        return Arr::only($this->cache[$clave] ?? [], $ids);
    }

    /**
     * @param  array<int, string>  $campos
     */
    private function componerEtiqueta(Model $registro, array $campos, ?string $formato = null): string
    {
        $partes = [];

        foreach ($campos as $campo) {
            $valor = data_get($registro, $campo);

            if ($valor === null || $valor === '') {
                continue;
            }

            // Un descriptor puede apuntar a otra llave foránea (una asignación
            // se nombra por su vehículo y su operario, no por sus ids).
            if ($this->relacion($campo)) {
                $relacionadas = $this->execute([$campo => [$valor]]);
                $valor = $relacionadas[$campo][$valor] ?? "#{$valor}";
            }

            $partes[$campo] = trim((string) $valor);
        }

        // La plantilla sólo se usa si trae valor para todos sus marcadores; si
        // falta alguno quedaría un "N° 12/" a medias, y es preferible caer al
        // listado simple de lo que sí se conoce.
        if ($formato && ! array_diff($this->marcadores($formato), array_keys($partes))) {
            return trim(str_replace(
                array_map(fn (string $campo) => '{'.$campo.'}', array_keys($partes)),
                array_values($partes),
                $formato
            ));
        }

        return $partes ? implode(' · ', $partes) : "#{$registro->getKey()}";
    }

    /**
     * @return array<int, string>
     */
    private function marcadores(string $formato): array
    {
        preg_match_all('/\{([\w.]+)\}/', $formato, $coincidencias);

        return $coincidencias[1];
    }

    private function esColumnaDeFecha(string $columna): bool
    {
        return Str::startsWith($columna, 'fecha')
            || Str::endsWith($columna, '_at')
            || in_array($columna, ['ultima_actividad', 'ultimo_uso'], true);
    }

    private function fecha(mixed $valor): ?string
    {
        try {
            $fecha = Carbon::parse((string) $valor);
        } catch (\Throwable) {
            return null;
        }

        return $fecha->format($fecha->format('H:i:s') === '00:00:00' ? 'd/m/Y' : 'd/m/Y H:i');
    }

    private function esJson(string $valor): bool
    {
        return Str::startsWith(trim($valor), ['{', '['])
            && json_validate($valor);
    }
}
