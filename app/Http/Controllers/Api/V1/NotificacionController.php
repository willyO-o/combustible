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
