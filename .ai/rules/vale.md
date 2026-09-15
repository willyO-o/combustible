---
paths:
  - 'app/Http/Controllers/Api/V1/ValeController.php,app/Actions/Vale/ListValeAction.php'
---

# Vale

## API GET /vales: jefe-area ve su área, admin/super-admin ven todo (sólo API)
ListValeAction se comparte entre ValeController web y Api\V1\ValeController. Para no tocar la web, `execute()` recibe un 5º parámetro `bool $aplicarAlcanceGestion = false` (default = comportamiento previo, usado por la web sin cambios). Sólo Api\V1\ValeController::index()/valesPendientes() lo pasan en `true`.

Con `true`, `aplicarRestriccionesPorRol()` aplica el mismo criterio de "conductor puro" ya usado en Api\V1\OperacionDiariaController: `hasRole('conductor') && !hasAnyRole(['jefe-area','administrador','super-admin'])` → sólo sus propios vales. Un jefe-area (puro o combinado con conductor, pero sin administrador/super-admin) ve los vales de vehículos de sus áreas a cargo (`whereHas('vehiculo.areasAsignadas', ...)` con `persona->encargadoAreas()`, mismo criterio que `ValeController::restringirVehiculosPorAreaDeJefe` web). Administrador/super-admin (con cualquier combinación) ven todo, sin restricción.

Documentado en openapi.yaml (GET /vales, GET /vales/pendientes) + CHANGELOG-openapi.md 1.8.0, bump api_version en openapi.yaml y ParametrosController. Tests en ValeControllerTest (jefe-area/conductor+jefe-area/conductor puro/administrador en el listado).
