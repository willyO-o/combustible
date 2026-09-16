<?php

namespace App\Channels;

use App\Models\Dispositivo;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

/**
 * Canal de notificaciones que envía un push por Firebase Cloud Messaging a
 * todos los dispositivos (app Flutter) registrados del notifiable (ver
 * App\Models\Dispositivo). Se usa igual que 'database'/'mail': agregando
 * self::class al array de via() de la Notification.
 *
 * La Notification debe implementar toFcm($notifiable): array{title: string,
 * body: string, data?: array<string, mixed>} — si no lo implementa, no se
 * envía nada por este canal (mismo criterio que toMail()/toDatabase()).
 */
class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $tokens = $notifiable->dispositivos()->pluck('token')->all();

        if (empty($tokens)) {
            return;
        }

        $payload = $notification->toFcm($notifiable);

        $mensaje = CloudMessage::new()
            ->withNotification(FcmNotification::create($payload['title'], $payload['body']))
            // FCM exige que "data" sea un mapa string => string.
            ->withData(array_map('strval', $payload['data'] ?? []));

        try {
            // Se resuelve recién aquí (no en el constructor): así, si Firebase
            // no está configurado o las credenciales son inválidas, el error
            // ocurre DENTRO del try/catch en vez de tumbar toda la petición
            // al construir el canal (ver .ai/rules sobre este canal).
            $reporte = app(Messaging::class)->sendMulticast($mensaje, $tokens);
        } catch (Throwable $e) {
            // Un push fallido (Firebase caído, credenciales mal configuradas,
            // etc.) no debe tumbar la operación de negocio que lo disparó.
            Log::warning('No se pudo enviar la notificación push por FCM.', [
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Tokens inválidos/no registrados (app desinstalada, token rotado sin
        // volver a registrarse, etc.): se eliminan para no seguir intentando.
        $tokensMuertos = [...$reporte->invalidTokens(), ...$reporte->unknownTokens()];
        if (! empty($tokensMuertos)) {
            Dispositivo::whereIn('token', $tokensMuertos)->delete();
        }
    }
}
