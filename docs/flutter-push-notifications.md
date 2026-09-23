# Notificaciones push (FCM) — Guía de integración para la app Flutter

Este documento es para el developer de la app móvil. Cubre todo lo necesario para registrar
dispositivos y recibir/mostrar las notificaciones push que ya emite el backend.

El contrato formal de los endpoints (`POST/DELETE /dispositivos`, `GET /notificaciones`) está en
`public/docs/openapi.yaml` (Swagger, tags **Dispositivos** y **Notificaciones**) — este documento
lo complementa con el código Flutter y los pasos de configuración de Firebase.

> 🔒 **Datos sensibles**: este documento NO incluye credenciales de ningún tipo (acceso al
> proyecto de Firebase, cuenta de servicio, keystore de firma Android, clave APNs de iOS). Todo
> eso se coordina por canal interno (invitación al proyecto de Firebase, transferencia segura de
> archivos), nunca por este documento ni por chat.

---

## 1. Qué necesitas que te den (coordinar internamente)

| Qué | Quién lo genera | Para qué |
|---|---|---|
| Acceso como colaborador al proyecto de Firebase (Console) | Backend/admin del proyecto Firebase | Poder ejecutar `flutterfire configure` y descargar `google-services.json` / `GoogleService-Info.plist` tú mismo |
| Apple Developer Team ID + clave APNs (`.p8`) subida a Firebase | Quien administre la cuenta Apple Developer | Push en iOS (Firebase lo pide para reenviar a APNs) |
| Confirmación del `applicationId` (Android) / Bundle ID (iOS) ya usados en el proyecto Firebase | Backend/admin | Para que coincidan con los que agregues a Firebase Console |

Lo que **no** necesitas: el JSON de la cuenta de servicio (`service-account.json`) — eso es
exclusivo del backend (Laravel), nunca debe estar en el repo ni en el dispositivo del developer
móvil.

---

## 2. Configuración inicial del proyecto Flutter

### 2.1. Dependencias (`pubspec.yaml`)

```yaml
dependencies:
  firebase_core: ^3.x
  firebase_messaging: ^15.x
  flutter_local_notifications: ^18.x   # para mostrar el push con la app abierta (foreground)
```

### 2.2. Vincular el proyecto con FlutterFire CLI

Con acceso ya otorgado al proyecto de Firebase (ver sección 1):

```bash
dart pub global activate flutterfire_cli
flutterfire configure
```

Esto genera `lib/firebase_options.dart` y coloca automáticamente:
- `android/app/google-services.json`
- `ios/Runner/GoogleService-Info.plist` (ábrelo también desde Xcode → agrégalo al target `Runner`)

### 2.3. Inicializar en `main.dart`

```dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'firebase_options.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);

  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

  runApp(const MyApp());
}

// Debe ser una función top-level (no un método de clase) con esta anotación.
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  // No hace falta mostrar nada aquí: si el mensaje trae bloque "notification"
  // (que todos los que manda este backend traen), el sistema operativo ya lo
  // muestra solo cuando la app está en background/terminada.
}
```

### 2.4. Android — canal de notificaciones (obligatorio, Android 8+)

En `android/app/src/main/AndroidManifest.xml`, dentro de `<application>`:

```xml
<meta-data
  android:name="com.google.firebase.messaging.default_notification_channel_id"
  android:value="default_channel" />
```

Y crear ese canal al iniciar la app (una vez, junto con la inicialización de
`flutter_local_notifications`):

```dart
const canal = AndroidNotificationChannel(
  'default_channel',
  'General',
  importance: Importance.high,
);

await flutterLocalNotificationsPlugin
    .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
    ?.createNotificationChannel(canal);
```

### 2.5. iOS — permisos

En `ios/Runner/Info.plist` no hace falta nada especial más allá de lo que ya pide
`firebase_messaging`. El permiso se pide en runtime (ver sección 3).

---

## 3. Registrar el dispositivo (después del login)

Justo después de que `POST /auth/login` devuelva el `access_token`:

```dart
Future<void> registrarDispositivoPush(String accessToken) async {
  final messaging = FirebaseMessaging.instance;

  // Pide permiso explícito (obligatorio en iOS y Android 13+). Si el usuario
  // lo rechaza, getToken() igual puede devolver un token en Android, pero no
  // se mostrará nada — está bien registrar igual, no truena nada.
  await messaging.requestPermission(alert: true, badge: true, sound: true);

  final token = await messaging.getToken();
  if (token == null) return;

  await dio.post(
    '/dispositivos',
    data: {
      'token': token,
      'plataforma': Platform.isIOS ? 'ios' : 'android',
    },
    options: Options(headers: {'Authorization': 'Bearer $accessToken'}),
  );
}
```

**Importante**: llamar a esto en cada login exitoso, no solo la primera vez — el endpoint hace
`updateOrCreate` por token, así que repetirlo no duplica nada.

### 3.1. Cuando Firebase rota el token

```dart
FirebaseMessaging.instance.onTokenRefresh.listen((nuevoToken) async {
  final accessToken = await obtenerAccessTokenGuardado();
  if (accessToken == null) return; // usuario deslogueado, no reenviar

  await dio.post('/dispositivos',
    data: {'token': nuevoToken, 'plataforma': Platform.isIOS ? 'ios' : 'android'},
    options: Options(headers: {'Authorization': 'Bearer $accessToken'}),
  );
});
```

### 3.2. Al cerrar sesión

Antes (o después) de `POST /auth/logout`:

```dart
final token = await FirebaseMessaging.instance.getToken();
if (token != null) {
  await dio.delete('/dispositivos', data: {'token': token});
}
```

Si no se hace esto, el dispositivo sigue registrado a nombre del usuario anterior — no rompe nada
(el backend reasigna el token automáticamente si otro usuario hace login ahí), pero mientras tanto
seguiría recibiendo push de la cuenta anterior.

---

## 4. Mostrar el push con la app abierta (foreground)

FCM **no** muestra nada solo si la app está en primer plano — hay que dibujarlo a mano:

```dart
FirebaseMessaging.onMessage.listen((RemoteMessage message) {
  flutterLocalNotificationsPlugin.show(
    message.hashCode,
    message.notification?.title,
    message.notification?.body,
    const NotificationDetails(
      android: AndroidNotificationDetails('default_channel', 'General'),
    ),
    payload: jsonEncode(message.data),
  );
});
```

Con la app en background o terminada, el sistema operativo ya lo muestra solo (no hace falta este
paso, sólo el handler de background de la sección 2.3 para que la app "despierte" si hace falta).

---

## 5. Navegar al tocar el push (deep link)

Cada notificación trae, además de `title`/`body`, un bloque `data` con `tipo` + los IDs
necesarios para navegar a la pantalla correcta — es el mismo `data` que ya devuelve
`GET /notificaciones` en el campo interno (`tipo`, más los campos de cada fila de la tabla).

### Tabla completa de tipos (`data['tipo']`)

| `tipo` | Campos extra en `data` | Pantalla sugerida |
|---|---|---|
| `observacion_operacion` | `id_operacion`, `id_vehiculo`, `observaciones` | Detalle de operación diaria `id_operacion` |
| `orden_trabajo_asignada` | `id_orden_trabajo`, `nro_orden`, `id_vehiculo`, `tipo_mantenimiento` | Detalle de orden `id_orden_trabajo` |
| `orden_trabajo_culminada` | `id_orden_trabajo`, `nro_orden`, `id_vehiculo` | Detalle de orden `id_orden_trabajo` |
| `orden_trabajo_verificada` | `id_orden_trabajo`, `nro_orden`, `id_vehiculo` | Detalle de orden `id_orden_trabajo` |
| `vale_emitido` | `id_vale`, `nro`, `litros`, `fecha_vencimiento` | Listado de vales (o detalle si se agrega luego) |
| `vale_por_vencer` | `id_vale`, `nro`, `dias_restantes`, `fecha_vencimiento` | Listado de vales |
| `carga_combustible_registrada` | `id_carga_combustible`, `nro`, `id_vale`, `litros` | Listado de cargas de combustible |
| `carga_combustible_area` | `id_carga_combustible`, `nro`, `id_vale` (puede ser vacío), `id_vehiculo`, `placa`, `litros` | Listado de cargas de combustible (jefes de área: revisar) |
| `solicitud_mantenimiento_registrada` | `id_solicitud`, `nro`, `id_vehiculo`, `placa`, `tipo_mantenimiento` | Detalle de la solicitud `id_solicitud` (jefes de área) |
| `carga_material_registrada` | `id_carga_material`, `nro` | Detalle del flete `id_carga_material` |

Todos además traen `url` (ruta absoluta del panel **web**, ej. `http://.../mantenimiento/ordenes/7`)
— es informativa/de referencia, no pensada para abrir un WebView; la app debe navegar nativo con
los IDs de la tabla de arriba.

```dart
void _manejarTap(Map<String, dynamic> data) {
  switch (data['tipo']) {
    case 'orden_trabajo_asignada':
    case 'orden_trabajo_culminada':
    case 'orden_trabajo_verificada':
      Navigator.pushNamed(context, '/ordenes/${data['id_orden_trabajo']}');
      break;
    case 'observacion_operacion':
      Navigator.pushNamed(context, '/operaciones/${data['id_operacion']}');
      break;
    case 'vale_emitido':
    case 'vale_por_vencer':
      Navigator.pushNamed(context, '/vales');
      break;
    case 'carga_combustible_area':
    case 'carga_combustible_registrada':
      Navigator.pushNamed(context, '/cargas-combustible');
      break;
    case 'solicitud_mantenimiento_registrada':
      Navigator.pushNamed(context, '/solicitudes/${data['id_solicitud']}');
      break;
    case 'carga_material_registrada':
      Navigator.pushNamed(context, '/control-cargas/${data['id_carga_material']}');
      break;
  }
}

// App en background, usuario tocó el push
FirebaseMessaging.onMessageOpenedApp.listen((m) => _manejarTap(m.data));

// App cerrada, usuario tocó el push (revisar al iniciar la app)
final inicial = await FirebaseMessaging.instance.getInitialMessage();
if (inicial != null) _manejarTap(inicial.data);
```

> Si se agrega un tipo nuevo en el backend, aparecerá primero documentado en
> `public/docs/openapi.yaml` (enum `Notificacion.tipo`) y en el changelog — revisar ahí antes de
> cada release para mantener este switch al día.

---

## 6. Checklist de prueba end-to-end

1. Login en la app → confirmar con el equipo backend que el dispositivo quedó registrado
   (`dispositivos` en BD).
2. Disparar cualquier acción que notifique (ej. que un jefe de área emita un vale) → debe llegar
   el push al celular en unos segundos, con la app en foreground, background y cerrada.
3. Tocar el push → debe navegar a la pantalla correcta según la tabla de la sección 5.
4. Cerrar sesión → confirmar que el token se eliminó (`DELETE /dispositivos` respondió 200).
5. Desinstalar y reinstalar la app (o borrar datos) → el token viejo debe dejar de recibir push
   sin que nadie tenga que borrarlo a mano (el backend lo limpia solo en el primer envío fallido).

## 7. Si algo no llega

No hay forma de depurar esto desde la app: un push que no llega casi siempre es un problema de
configuración de Firebase (SHA-1 no registrado en Android, certificado/clave APNs vencida en iOS,
token no registrado). Reportar al equipo backend con: usuario, hora aproximada, y si la app estaba
en foreground/background/cerrada — ellos pueden ver en `storage/logs/laravel.log` el motivo exacto
si el envío falló del lado de Firebase.
