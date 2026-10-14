---
paths:
  - 'app/Http/Requests/**/*.php,resources/js/Components/*FormModal.vue,resources/js/Components/CargoSelect.vue,bootstrap/app.php'
---

# Components Js Components

## Requests de rutas WEB enviadas por axios: usar el trait RespondeErroresEnJson
bootstrap/app.php (shouldRenderJsonWhen) sólo renderiza JSON en api/*, así que en una ruta WEB un error de validación responde con redirect aunque axios pida JSON: axios sigue el redirect, recibe la página HTML con 200 y el modal se cierra "con éxito" sin guardar nada (bug real corregido 2026-10-14 en MaterialFormModal, el alta rápida de material de ControlCargas/Show.vue, VehiculoExternoFormModal y GrifoFormModal). Toda Form Request de una ruta web que se envíe por axios debe `use RespondeErroresEnJson;` (app/Http/Requests/RespondeErroresEnJson.php): si expectsJson() y NO es api/*, lanza 422 {message, errors}; los formularios Inertia (Accept text/html) siguen recibiendo el redirect con errores; las rutas api/* no se tocan. Hoy lo usan MaterialRequest, VehiculoExternoRequest, GrifoRequest y CargoRequest. NO se cambió shouldRenderJsonWhen globalmente a propósito: CiInput.vue, ConexionAviso y la sesión vencida dependen del redirect web. Test: postJson duplicado → assertUnprocessable + assertJsonValidationErrors (MaterialControllerTest, VehiculoExternoControllerTest, GrifoControllerTest).
