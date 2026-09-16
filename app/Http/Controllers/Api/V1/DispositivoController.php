<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dispositivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registro de tokens FCM (app Flutter) para notificaciones push. No es un
 * recurso CRUD completo: sólo registrar/actualizar (al iniciar sesión o
 * cuando Firebase rota el token) y eliminar (al cerrar sesión).
 */
class DispositivoController extends Controller
{
    /**
     * Registra (o actualiza, si ya existía) el token FCM del dispositivo
     * actual para el usuario autenticado.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'plataforma' => ['required', 'string', 'in:android,ios,web'],
        ]);

        // updateOrCreate por token (único global, no por usuario): si el
        // mismo dispositivo pasa a otra cuenta (logout + login de otro
        // usuario), reasigna el registro en vez de duplicarlo.
        $dispositivo = Dispositivo::updateOrCreate(
            ['token' => $datos['token']],
            [
                'id_usuario' => $request->user()->id,
                'plataforma' => $datos['plataforma'],
                'ultima_actividad' => now(),
            ]
        );

        return response()->json([
            'message' => 'Dispositivo registrado exitosamente.',
            'data' => $dispositivo,
        ], 201);
    }

    /**
     * Elimina el token del dispositivo actual (al cerrar sesión), para dejar
     * de enviarle push. Sólo borra tokens del propio usuario autenticado.
     */
    public function destroy(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->dispositivos()->where('token', $datos['token'])->delete();

        return response()->json([
            'message' => 'Dispositivo eliminado exitosamente.',
        ]);
    }
}
