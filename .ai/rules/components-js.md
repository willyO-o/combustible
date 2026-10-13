---
paths:
  - 'resources/js/Components/ConexionAviso.vue,resources/js/app.js'
---

# Components Js

## Aviso global de conexión (ConexionAviso.vue) — sin red vs. servidor caído
resources/js/Components/ConexionAviso.vue (2026-10-13, pedido del usuario, estilo Facebook) se monta en app.js como hermano de <App> (con PageLoader e InstallPwaPrompt), así cubre todas las páginas incluido el login. Estados: 'sin-red' (navigator.onLine=false → "Estás sin conexión a internet"), 'sin-servidor' (hay red pero el sistema no responde — el servidor está en la LAN, internet y servidor son cosas distintas), 'restablecida' (se oculta sola a los 4,5 s). La vuelta se CONFIRMA con fetch HEAD a /up (health de Laravel, bootstrap/app.php) con cache-buster y timeout 6 s, armando la raíz desde la prop compartida storage_url (no APP_URL). Disparadores: online/offline, visibilitychange, "Reintentar", reintento cada 8 s mientras no hay conexión, y router.on('exception') de Inertia sólo si la excepción NO trae response (el poll de notificaciones del header cada 30 s detecta caídas del servidor). No cancela el evento. z-index 100001 (sobre PageLoader). Superficie invertida con tokens propios (--conexion-*), no --custom-white (varía por page-style). En <style scoped> NO usar :global(X) .clase (Vue lo compila sólo como X): usar "[data-theme-mode='dark'] .conexion-aviso". Verificado con harness en Chrome (20 escenarios) + capturas claro/oscuro/celular; sin tests JS en el proyecto.
