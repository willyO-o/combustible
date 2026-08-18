<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class NotificacionController extends Controller
{
    /**
     * Marca una notificación del usuario autenticado como leída.
     */
    public function marcarLeida(string $notificacion): RedirectResponse
    {
        auth()->user()->notifications()->findOrFail($notificacion)->markAsRead();

        return back();
    }

    /**
     * Marca todas las notificaciones no leídas del usuario autenticado como leídas.
     */
    public function marcarTodasLeidas(): RedirectResponse
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
