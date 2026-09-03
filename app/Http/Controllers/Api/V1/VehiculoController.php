<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntervaloMantenimientoTipo;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Detalle por vehículo del mantenimiento preventivo sugerido: para cada
 * intervalo configurado en su tipo de vehículo compara la última lectura del
 * vehículo (kilometraje/horómetro de las cargas de combustible) contra la
 * última vez que se hizo ese mantenimiento y sugiere el siguiente múltiplo de
 * la frecuencia, con una tolerancia del 5% (±).
 *
 * Todo el cálculo se resuelve en el servidor (ver
 * IntervaloMantenimientoTipo::alertasMantenimiento()); la respuesta ya trae el
 * estado, su etiqueta y una descripción lista para mostrar, para que la app no
 * tenga que hacer ninguna cuenta.
 */
class VehiculoController extends Controller
{
    /**
     * Prioridad de orden de los estados (lo más urgente primero).
     */
    private const ORDEN_ESTADO = [
        'VENCIDO' => 0,
        'PROXIMO' => 1,
        'AL_DIA' => 2,
        'SIN_DATOS' => 3,
    ];

    private const ETIQUETA_ESTADO = [
        'VENCIDO' => 'Vencido',
        'PROXIMO' => 'Próximo',
        'AL_DIA' => 'Al día',
        'SIN_DATOS' => 'Sin datos',
    ];

    public function mantenimientoSugerido(Request $request, Vehiculo $vehiculo): JsonResponse
    {
        $this->assertPuedeVer($request->user(), $vehiculo);

        $alertas = IntervaloMantenimientoTipo::alertasMantenimiento([$vehiculo->id])
            ->map(fn (array $alerta) => $this->formatearAlerta($alerta))
            ->sortBy([
                fn ($a) => self::ORDEN_ESTADO[$a['estado']] ?? 9,
                fn ($a) => $a['tipo_mantenimiento'],
            ])
            ->values();

        return response()->json([
            'data' => [
                'vehiculo' => [
                    'id' => $vehiculo->id,
                    'uuid' => $vehiculo->uuid,
                    'codigo' => $vehiculo->codigo,
                    'nro_placa' => $vehiculo->nro_placa,
                    'marca' => $vehiculo->marca,
                    'modelo' => $vehiculo->modelo,
                    'tipo_medicion' => $vehiculo->tipo_medicion,
                ],
                'resumen' => $this->resumen($alertas),
                'alertas' => $alertas,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $alerta  Fila cruda de IntervaloMantenimientoTipo::alertasMantenimiento().
     * @return array<string, mixed>
     */
    private function formatearAlerta(array $alerta): array
    {
        $unidad = $alerta['tipo_medicion'] === 'horometro' ? 'h' : 'km';
        $fmt = fn ($valor): string => $this->numero($valor);

        $descripcion = match ($alerta['estado']) {
            'SIN_DATOS' => 'Sin lecturas de combustible registradas para calcular este mantenimiento.',
            'VENCIDO' => 'Vencido por '.$fmt(abs($alerta['restante'])).' '.$unidad.'. Programar cuanto antes.',
            'PROXIMO' => $alerta['restante'] >= 0
                ? 'Próximo: faltan '.$fmt($alerta['restante']).' '.$unidad.' para el objetivo sugerido.'
                : 'Debería realizarse ahora (pasado por '.$fmt(abs($alerta['restante'])).' '.$unidad.', dentro de la tolerancia).',
            default => 'Al día: faltan '.$fmt($alerta['restante']).' '.$unidad.' para el próximo mantenimiento.',
        };

        return [
            'id_tipo_mantenimiento' => $alerta['id_tipo_mantenimiento'],
            'tipo_mantenimiento' => $alerta['tipo_mantenimiento'],
            'tipo_medicion' => $alerta['tipo_medicion'],
            'unidad' => $unidad,
            'frecuencia' => $alerta['frecuencia'],
            'tolerancia' => $alerta['holgura'],
            'lectura_actual' => $alerta['lectura_actual'],
            'ultimo_mantenimiento' => $alerta['ultimo_mantenimiento'],
            'proximo_objetivo' => $alerta['proximo_objetivo'],
            'restante' => $alerta['restante'],
            'estado' => $alerta['estado'],
            'estado_label' => self::ETIQUETA_ESTADO[$alerta['estado']] ?? $alerta['estado'],
            'descripcion' => $descripcion,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $alertas
     * @return array<string, int|bool>
     */
    private function resumen(Collection $alertas): array
    {
        $vencidos = $alertas->where('estado', 'VENCIDO')->count();
        $proximos = $alertas->where('estado', 'PROXIMO')->count();

        return [
            'total' => $alertas->count(),
            'vencidos' => $vencidos,
            'proximos' => $proximos,
            'al_dia' => $alertas->where('estado', 'AL_DIA')->count(),
            'sin_datos' => $alertas->where('estado', 'SIN_DATOS')->count(),
            'requiere_atencion' => $vencidos + $proximos > 0,
        ];
    }

    /**
     * Formato de número para las descripciones: separador de miles "." y
     * decimales "," (es-BO), sin decimales si el valor es entero.
     */
    private function numero(mixed $valor): string
    {
        $valor = (float) $valor;
        $decimales = fmod($valor, 1.0) === 0.0 ? 0 : 2;

        return number_format($valor, $decimales, ',', '.');
    }

    /**
     * super-admin/administrador/jefe-area/técnico ven el mantenimiento de
     * cualquier vehículo; un conductor sólo el de los vehículos que tiene
     * asignados actualmente (mismo criterio que el resto de la API).
     */
    private function assertPuedeVer(User $user, Vehiculo $vehiculo): void
    {
        if ($user->hasAnyRole(['super-admin', 'administrador', 'jefe-area', 'tecnico-mantenimiento'])) {
            return;
        }

        if ($user->hasRole('conductor') && $vehiculo->conductorAsignado?->id === $user->id_persona) {
            return;
        }

        abort(403, 'No tiene acceso al mantenimiento de este vehículo.');
    }
}
