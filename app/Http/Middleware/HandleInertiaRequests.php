<?php

namespace App\Http\Middleware;

use App\Notifications\NotificacionFormatter;
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
     * El título/descripción/ícono según el tipo (data['tipo']) vive en
     * NotificacionFormatter, compartido con Api\V1\NotificacionController y
     * App\Channels\FcmChannel — no dupliques el match aquí.
     *
     * @return array<string, mixed>
     */
    private function formatearNotificacion(DatabaseNotification $notificacion): array
    {
        $data = $notificacion->data;

        return [
            'id' => $notificacion->id,
            ...NotificacionFormatter::formatear($data),
            'url' => $data['url'] ?? null,
            'leida' => $notificacion->read_at !== null,
            'fecha' => $notificacion->created_at->diffForHumans(),
        ];
    }
}
