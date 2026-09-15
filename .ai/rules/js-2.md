---
paths:
  - 'vite.config.js,public/.htaccess,resources/js/app.js'
---

# Js 2

## PWA: scope del Service Worker y cabecera Service-Worker-Allowed
laravel-vite-plugin fija el `base` de Vite en "/build/" (ahí compila los assets). vite-plugin-pwa, si no se le pasa `scope` explícito, hereda ESE MISMO base como scope de registro del Service Worker → el SW quedaba con scope "/build/" y nunca controlaba el resto del sitio (rompía offline + instalabilidad: Chrome exige que el scope efectivo cubra `start_url`, "/"). Fix en vite.config.js: `VitePWA({ scope: '/', ... })`, SIN tocar `base` (si se pone `base:'/'` también, cambia dónde el cliente espera el archivo — pasaría a pedir `/sw.js` en vez de `/build/sw.js`, donde realmente se escribe, y además las rutas relativas del precache manifest — `assets/xxx.js`, relativas a la carpeta del propio sw.js — dejarían de resolver).

Con scope "/" pero el script sirviéndose físicamente desde "/build/sw.js", el navegador exige que esa respuesta traiga la cabecera `Service-Worker-Allowed: /` — si falta, `register()` rechaza con SecurityError (se captura en `onRegisterError` de resources/js/app.js, no rompe la app, pero la PWA deja de ser instalable). Se agregó en public/.htaccess (`<Files "sw.js"> Header set Service-Worker-Allowed "/" </Files>`, requiere mod_headers) — sólo cubre Apache. Si producción corre Nginx (o cualquier otro servidor) hace falta el equivalente en su config (`add_header Service-Worker-Allowed "/";` en el location que sirve /build/sw.js), fuera de este repo — no asumir que ya está resuelto sin verificarlo ahí.

También se quitó `workbox.navigateFallback` (default "index.html" de generateSW, pensado para SPAs de shell único) — no aplica: cada ruta la renderiza Laravel/Inertia en servidor con props distintas, no hay un index.html único que sirva de fallback offline.
