---
paths:
  - 'app/Notifications/*.php,app/Channels/*.php'
---

# Channels

## Push FCM: canal App\Channels\FcmChannel + tabla dispositivos
kreait/laravel-firebase (^7.2, kreait/firebase-php ^8.5) instalado con aprobación explícita del usuario. config/firebase.php publicado (lee FIREBASE_CREDENTIALS del .env, JSON de cuenta de servicio NO versionado — va en storage/app/firebase/, gitignored).

- Tabla `dispositivos` (id_usuario, token único global, plataforma enum android/ios/web, ultima_actividad) — modelo `Dispositivo`, relación `User::dispositivos()`. Se registra/actualiza vía `POST /api/v1/dispositivos` (updateOrCreate por token, reasigna si el device pasó a otro usuario) y se borra vía `DELETE /api/v1/dispositivos` (Api\V1\DispositivoController, sin Form Request — validate() inline por ser endpoint chico, no CRUD completo).
- `App\Channels\FcmChannel::send()` resuelve `Kreait\Firebase\Contract\Messaging` con `app(Messaging::class)` DENTRO del try/catch, NUNCA en el constructor — si se inyecta por constructor, el container lo resuelve al instanciar el canal (antes de que corra el try/catch), y una credencial ausente/inválida tumba con 500 CUALQUIER notificación (incluida 'database'), incluso en tests con `Notification::fake()` que no llegan a ejecutar send() de esa notificación puntual. Ya se cazó este bug una vez (ver commit): toda `Notification::send()`/`->notify()` sin `Notification::fake()` reventaba si `storage/app/firebase/service-account.json` no existía.
- Cada Notification que quiera push agrega `FcmChannel::class` a `via()` (junto a 'database') + implementa `toFcm($notifiable): array{title, body, data}` reusando `NotificacionFormatter::formatear($this->toArray($notifiable))` para title/body — NUNCA hardcodear el texto de nuevo ahí.
- `NotificacionFormatter::formatear(array $data): array{titulo,descripcion,icono}` (app/Notifications/NotificacionFormatter.php) es la ÚNICA fuente del texto por `data['tipo']`, compartida por HandleInertiaRequests (dropdown web), Api\V1\NotificacionController (listado API) y FcmChannel (push) — agregar el `match` UNA sola vez ahí, no en cada consumidor.
- Tokens inválidos/no registrados se limpian solos tras cada envío (`MulticastSendReport::invalidTokens()`/`unknownTokens()` → `Dispositivo::whereIn('token',...)->delete()`), no hace falta un job de limpieza aparte.
- Tests: para probar el canal real (no con `Notification::fake()`) hay que bindear el mock con `$this->app->instance(Messaging::class, $mock)` ANTES de `->notify()` — ver tests/Feature/FcmChannelTest.php.
