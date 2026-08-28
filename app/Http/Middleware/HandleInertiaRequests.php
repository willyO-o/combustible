<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->load('persona'),
                'permissions' => fn () => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')->values()
                    : [],
                'roles' => fn () => $request->user()
                    ? $request->user()->getRoleNames()->values()
                    : [],
                'is_super_admin' => fn () => $request->user()?->hasRole('super-admin') ?? false,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notificaciones' => [
                'no_leidas' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
                'items' => fn () => $request->user()
                    ? $request->user()->notifications()->limit(10)->get()
                        ->map(fn ($notificacion) => $this->formatearNotificacion($notificacion))
                    : [],
            ],
        ];
    }

    /**
     * Da forma a una notificación de base de datos para el dropdown del header.
     *
     * Cada nuevo tipo de notificación (identificado por data['tipo']) debe
     * añadir su propio caso aquí con título, descripción e ícono.
     *
     * @return array<string, mixed>
     */
    private function formatearNotificacion(DatabaseNotification $notificacion): array
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
            default => [
                'Notificación',
                $data['mensaje'] ?? '',
                'ri-notification-line',
            ],
        };

        return [
            'id' => $notificacion->id,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'icono' => $icono,
            'url' => $data['url'] ?? null,
            'leida' => $notificacion->read_at !== null,
            'fecha' => $notificacion->created_at->diffForHumans(),
        ];
    }
}
