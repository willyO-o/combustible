# Conectar el proyecto con Firebase — Checklist paso a paso

Guía para crear y configurar el proyecto de Firebase (plan **Spark, gratis** — el envío de push
por FCM no tiene costo en ningún plan, no hace falta tarjeta de crédito). Está pensada para
retomarla más adelante: marca cada casillero a medida que avanzas.

Complementa a `docs/flutter-push-notifications.md` (guía para el developer móvil) — aquí solo va
la parte de **crear y configurar el proyecto en Firebase Console**, que hace quien administra el
proyecto (no necesariamente el developer móvil).

---

## ⚠️ Datos que faltan antes de poder terminar esto

No se puede completar el Paso 2 (Android) ni el Paso 3 (iOS) sin estos datos — pedírselos al
developer móvil cuando retomes esto:

- [ ] **`applicationId` de Android** (está en `android/app/build.gradle`, algo como
      `com.miempresa.combustible`)
- [ ] **Bundle ID de iOS** (está en `ios/Runner`, Bundle Identifier en Xcode — sólo si van a
      publicar en iPhone; si por ahora sólo es Android, se puede omitir el Paso 3 y hacerlo después)

---

## Paso 1 — Crear el proyecto en Firebase Console

- [ ] Entrar a [console.firebase.google.com](https://console.firebase.google.com) con una cuenta
      de Google (Gmail).
- [ ] Clic en **"Agregar proyecto" / "Add project"**.
- [ ] Nombre del proyecto (ej. `combustible-siget`).
- [ ] Google Analytics: **no hace falta**, se puede desactivar — no lo usa nada de lo que se
      implementó.
- [ ] Clic en **Crear proyecto**, esperar ~30 segundos.

Con esto el proyecto ya queda en el plan **Spark (gratis)** por defecto. No tocar nada de
facturación/billing.

---

## Paso 2 — Registrar la app Android

- [ ] En la pantalla principal del proyecto, ícono de **Android** ("Agregar app").
- [ ] Nombre del paquete = el `applicationId` de la sección de arriba. Tiene que coincidir
      **exacto**.
- [ ] Apodo de la app y SHA-1: opcionales, se pueden dejar en blanco por ahora.
- [ ] Descargar `google-services.json`.
- [ ] **Entregar `google-services.json` al developer móvil** (va en `android/app/` de su repo
      Flutter — no va en el repo de Laravel).
- [ ] Los pasos que muestra la consola después ("Agregar el SDK de Firebase", etc.) se saltan —
      eso lo hace el developer móvil con `flutterfire configure` (ver
      `docs/flutter-push-notifications.md`, sección 2.2).

---

## Paso 3 — Registrar la app iOS *(sólo si van a publicar en iPhone)*

- [ ] Mismo lugar, ícono de **iOS** ("Agregar app").
- [ ] Bundle ID = el de la sección de arriba.
- [ ] Descargar `GoogleService-Info.plist`.
- [ ] **Entregar `GoogleService-Info.plist` al developer móvil** (va en `ios/Runner/`, y también
      hay que agregarlo al target `Runner` desde Xcode).

> ⚠️ iOS necesita, además de Firebase, una **cuenta Apple Developer Program activa (US$99/año)**
> — sin eso Apple no entrega push a ninguna app, sin importar el proveedor. Esto es un costo de
> Apple, no de Firebase. Ver Paso 6.

---

## Paso 4 — Generar la credencial para el backend (Laravel)

Esta es la parte que de verdad te importa a ti como administrador del backend.

- [ ] Ícono de engranaje ⚙️ (arriba a la izquierda) → **"Configuración del proyecto"**.
- [ ] Pestaña **"Cuentas de servicio"** ("Service accounts").
- [ ] Clic en **"Generar nueva clave privada"** → confirmar → se descarga un `.json`.
- [ ] Copiar ese archivo al servidor/máquina local en:
      ```
      storage/app/firebase/service-account.json
      ```
      Ya está referenciado en `.env` (`FIREBASE_CREDENTIALS`) y en `.gitignore` — no hace falta
      tocar código, sólo copiar el archivo ahí.

> ⚠️ Esta clave da acceso administrativo a **todo** el proyecto de Firebase — tratarla como una
> contraseña: nunca por email/chat sin cifrar, nunca al repositorio de código.

---

## Paso 5 — Verificar que la API esté habilitada

- [ ] Configuración del proyecto → pestaña **"Cloud Messaging"**.
- [ ] Debe decir **"Firebase Cloud Messaging API (V1)": Habilitada**. En proyectos nuevos ya viene
      activada sola; si no, hay un link ahí mismo para activarla (gratis, un clic).

---

## Paso 6 — Sólo si van a soportar iOS: subir la clave APNs

- [ ] Tener la cuenta Apple Developer Program (Paso 3) ya activa.
- [ ] En [developer.apple.com/account](https://developer.apple.com/account) → Certificates,
      Identifiers & Profiles → **Keys** → crear una nueva con **"Apple Push Notifications service
      (APNs)"** marcado → descargar el `.p8` (**sólo se puede descargar una vez**, guardarlo bien).
- [ ] En Firebase Console → Configuración del proyecto → pestaña **Cloud Messaging** → sección
      **"Apple app configuration"** → subir ese `.p8` + el Key ID y Team ID que muestra Apple.

---

## Paso 7 — Probar la conexión desde Laravel

Con el `service-account.json` ya copiado en su lugar (Paso 4):

```bash
php artisan tinker --execute 'app(\Kreait\Firebase\Contract\Messaging::class); echo "OK, credenciales válidas.";'
```

- [ ] El comando no tira error → conexión lista.
- [ ] Avisar al developer móvil que ya puede hacer `flutterfire configure` con los archivos de los
      Pasos 2-3 (ver `docs/flutter-push-notifications.md`, sección 2).

---

## Resultado esperado al terminar

- [ ] Proyecto de Firebase creado, plan Spark (gratis).
- [ ] `google-services.json` y (si aplica) `GoogleService-Info.plist` entregados al developer
      móvil.
- [ ] `storage/app/firebase/service-account.json` en el servidor/local, funcionando.
- [ ] (Si aplica iOS) clave APNs subida a Firebase.
