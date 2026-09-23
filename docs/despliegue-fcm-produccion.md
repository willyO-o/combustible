# Despliegue de Push Notifications (FCM) a producción — guía paso a paso

Este documento es para quien despliega `combustible` a producción (vos). Cubre dos cosas:

1. Qué pedirle exactamente a quien administra el proyecto Firebase de la empresa.
2. Cómo instalar/activar las credenciales en el servidor, paso a paso.

> El código ya está implementado y probado (ver sección final "Qué no hace falta tocar"). Esto es
> 100% configuración/operación, no desarrollo.

---

## 1. Qué pedirle a la empresa (dueña del proyecto Firebase)

Vos ya tenés un `service-account.json` de prueba (proyecto `prime-b6a1b`) que funciona — lo
verificamos localmente. Antes de ir a producción, definí con la empresa estos puntos:

- [ ] **¿La clave que ya tenés es la que se va a usar en producción, o generan una nueva?**
      No es obligatorio usar una distinta, pero es buena práctica separar la clave de
      dev/pruebas de la de producción, para poder revocar una sin afectar la otra.
      Si deciden generar una nueva:
      **Configuración del proyecto → Cuentas de servicio → Generar nueva clave privada** (en
      Firebase Console, lo hace quien tenga rol de Owner/Editor en el proyecto).
- [ ] **Pedí que te la envíen por un canal seguro** (no email plano, no chat sin cifrar) — es una
      clave con acceso administrativo total al proyecto Firebase.
- [ ] **Confirmar que "Firebase Cloud Messaging API (V1)" está habilitada** — Configuración del
      proyecto → pestaña **Cloud Messaging**. En proyectos nuevos ya viene activada; solo hace
      falta confirmarlo.
- [ ] **Si van a publicar en iOS**: confirmar que la clave APNs (`.p8`) ya está subida en esa
      misma pestaña (Apple Developer Program activo, ver `docs/firebase-console-setup.md` paso 6).
      Si por ahora es solo Android, se puede omitir.

**Lo que NO necesitás pedir** (eso es para el developer Flutter, no para vos/backend):
`google-services.json` ni `GoogleService-Info.plist`. No van en el repo de Laravel.

---

## 2. Instalación en el servidor de producción

### 2.1 Requisitos del servidor (ya deberían cumplirse)

- PHP 8.3+ con extensiones `ctype`, `filter`, `json`, `mbstring` — estándar en cualquier hosting
  PHP moderno, no requiere nada especial (el SDK de Firebase usa HTTP/REST vía Guzzle, no gRPC).
- El deploy normal (`composer install --no-dev`) ya instala `kreait/laravel-firebase` porque está
  en `composer.lock` — **no es un paso nuevo**, viene con el deploy normal del proyecto.

### 2.2 Colocar la credencial

```bash
# En el servidor, dentro del proyecto:
mkdir -p storage/app/firebase
```

Subí el `service-account.json` real (el de producción, según lo que acuerdes en la sección 1) a:

```
storage/app/firebase/service-account.json
```

- Subilo por SFTP/SCP directo — **nunca por git, nunca por un pipeline que lo loguee**.
- Permisos recomendados: `chmod 600 storage/app/firebase/service-account.json`, dueño el mismo
  usuario que corre PHP-FPM/el proceso web.
- Ya está en `.gitignore` (vía `storage/app/.gitignore`), así que aunque alguien haga
  `git add -A` en el servidor por error, no se sube.

### 2.3 Variables de entorno

En el `.env` de producción, confirmá/agregá:

```dotenv
FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
```

(Ya está como valor por defecto en `.env.example`; solo asegurate de que el `.env` real del
servidor lo tenga.)

### 2.4 Migraciones

```bash
php artisan migrate --force
```

Esto crea la tabla `dispositivos` (tokens FCM por usuario) si el servidor de producción todavía
no la tiene.

### 2.5 Refrescar cachés

Si tu deploy cachea config (`php artisan config:cache`), volvé a correrlo **después** de subir el
`.env` con `FIREBASE_CREDENTIALS` — si la config quedó cacheada de antes, no va a tomar la
variable nueva hasta refrescarla.

```bash
php artisan config:cache
```

### 2.6 Verificar la conexión

```bash
php artisan tinker --execute='app(\Kreait\Firebase\Contract\Messaging::class); echo "OK, credenciales validas.";'
```

Si no tira error, la credencial es válida y Laravel ya puede hablar con FCM.

### 2.7 Confirmar que el scheduler de Laravel corre en el servidor

El aviso diario de "vale por vencer" (`ValePorVencerNotification`, dispara push) depende del
scheduler de Laravel (`routes/console.php`: `Schedule::command('vales:notificar-vencimiento')->daily()`).
Si el servidor no tiene ya el cron de Laravel corriendo (para otros comandos), agregalo:

```cron
* * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
```

Si ya existe (probablemente sí, si el proyecto tiene otros comandos programados), no hay que
tocar nada.

### 2.8 Prueba end-to-end con un dispositivo real

1. Pedile al developer Flutter que compile apuntando a la URL de producción y haga login — esto
   dispara `POST /api/v1/dispositivos` y guarda el token real en la tabla `dispositivos`.
2. Disparar una notificación real de prueba desde el servidor:

   ```bash
   php artisan tinker
   ```
   ```php
   $user = \App\Models\User::find(ID_DEL_USUARIO_QUE_HIZO_LOGIN);
   $vale = \App\Models\Vale::first(); // o el que corresponda
   $user->notify(new \App\Notifications\ValeEmitidoNotification($vale));
   ```
3. Confirmar que el push llega al teléfono, y revisar `storage/logs/laravel.log` — no debería
   aparecer `"No se pudo enviar la notificación push por FCM"`.

---

## 3. Coordinar con el developer Flutter

- Avisarle que ya puede implementar el registro de token contra `POST /api/v1/dispositivos` con
  la URL de producción (guía completa ya escrita en `docs/flutter-push-notifications.md`).
- `CAMBIOS-API-FLUTTER.md` (el changelog que se les manda) **todavía no menciona** los endpoints
  de `dispositivos`/`notificaciones` — conviene agregarlo ahí o avisarle directamente para que no
  se pierda.

---

## 4. Qué NO hace falta tocar en el código

Se revisó el proyecto completo antes de escribir esta guía: `FcmChannel`, el modelo
`Dispositivo`, las 8 clases de `Notification` que ya usan el canal FCM, las rutas de
`dispositivos`/`notificaciones`, y los tests (`FcmChannelTest`, `DispositivoControllerTest`,
`NotificacionControllerTest`, `NotificarValesPorVencerCommandTest` — los 14 pasan). Todo eso ya
está implementado y probado. Esta guía es puramente de configuración/despliegue, no de
desarrollo.
