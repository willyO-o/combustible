<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificacionController extends Controller
{
    /**
     * Lista las notificaciones del usuario autenticado: sólo las que aún no
     * fueron leídas o las emitidas en los últimos 2 días.
     */
    public function index(Request $request): JsonResponse
    {
        $notificaciones = $request->user()->notifications()
            ->where(function ($query) {
                $query->whereNull('read_at')
                    ->orWhere('created_at', '>=', now()->subDays(2));
            })
            ->get()
            ->map(fn (DatabaseNotification $notificacion) => $this->formatear($notificacion));

        return response()->json([
            'data' => $notificaciones,
        ]);
    }

    /**
     * Marca una notificación del usuario autenticado como leída.
     */
    public function marcarLeida(Request $request, string $notificacion): JsonResponse
    {
        $notificacion = $request->user()->notifications()->findOrFail($notificacion);
        $notificacion->markAsRead();

        return response()->json([
            'message' => 'Notificación marcada como leída.',
            'data' => $this->formatear($notificacion),
        ]);
    }

    /**
     * Da forma a una notificación para el consumo de la API.
     *
     * Cada nuevo tipo de notificación (identificado por data['tipo']) debe
     * añadir su propio caso aquí con título, descripción e ícono.
     *
     * @return array<string, mixed>
     */
    private function formatear(DatabaseNotification $notificacion): array
    {
        $data = $notificacion->data;
        $tipo = $data['tipo'] ?? 'general';

        [$titulo, $descripcion, $icono] = match ($tipo) {
            'observacion_operacion' => [
                'Observación en operación diaria',
                $data['observaciones'] ?? '',
                'ri-error-warning-line',
            ],
            'orden_trabajo_asignada' => [
                'Orden de trabajo asignada',
                'Se te asignó la orden N° '.($data['nro_orden'] ?? '').' para su ejecución.',
                'ri-tools-line',
            ],
            'orden_trabajo_culminada' => [
                'Orden de trabajo culminada',
                'El técnico culminó la orden N° '.($data['nro_orden'] ?? '').'.',
                'ri-checkbox-circle-line',
            ],
            'orden_trabajo_verificada' => [
                'Orden de trabajo verificada',
                'Tu orden N° '.($data['nro_orden'] ?? '').' fue verificada y cerrada.',
                'ri-shield-check-line',
            ],
            'vale_emitido' => [
                'Vale de combustible emitido',
                'Se emitió el vale N° '.($data['nro'] ?? '').' por '.($data['litros'] ?? '').' Lt.',
                'ri-file-list-3-line',
            ],
            'vale_por_vencer' => [
                'Vale por vencer',
                'El vale N° '.($data['nro'] ?? '').' vence pronto, aún no se usó.',
                'ri-alarm-warning-line',
            ],
            'carga_combustible_registrada' => [
                'Carga de combustible registrada',
                'Se registró la carga N° '.($data['nro'] ?? '').' ('.($data['litros'] ?? '').' Lt) del vale que emitiste.',
                'ri-gas-station-line',
            ],
            'carga_material_registrada' => [
                'Nuevo flete registrado',
                'Se abrió el flete N° '.($data['nro'] ?? '').'.',
                'ri-truck-line',
            ],
            default => [
                'Notificación',
                $data['mensaje'] ?? '',
                'ri-notification-line',
            ],
        };

        return [
            'id' => $notificacion->id,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'icono' => $icono,
            'url' => $data['url'] ?? null,
            'leida' => $notificacion->read_at !== null,
            'fecha' => $notificacion->created_at->toIso8601String(),
            'fecha_legible' => $notificacion->created_at->diffForHumans(),
        ];
    }
}
