---
paths:
  - 'app/Http/Controllers/OperacionDiariaController.php,app/Actions/OperacionDiaria/*.php,app/Http/Requests/OperacionStoreRequest.php,app/Http/Controllers/Api/V1/ParametrosController.php,resources/js/Pages/Operacion/Create.vue'
---

# V1 Js Pages Operacion

## Operación diaria: roles de gestión eligen entre TODOS los operarios activos
A pedido del usuario (2026-10-16, la asignación conductor-vehículo aún no es confiable): para cualquier usuario que NO sea conductor puro, id_conductor puede ser cualquier conductor estado_conductor=ACTIVO, esté o no asignado al vehículo. Fuente única: ListOperariosDisponiblesAction (prop `operarios` en create()/edit() web; `operaciones_diarias.operarios` en GET /parametros/colecciones, API 1.16.0). Validación: POST -> Rule::exists conductor ACTIVO + no borrado; Create/UpdateOperacionDiariaAction lanzan ConductorNoAsignadoException si el operario (nuevo) no está activo. Editar conservando el mismo operario sigue siendo válido aunque esté inactivo (edit() lo agrega a `operarios` con sufijo "— inactivo"). El conductor puro NO cambia: sin combo, `operarios` vacío y el controller le fuerza su propio id (web y API). meta.conductoresAsignados del vehículo ya no limita el combo: sólo ordena/marca sugeridos y preselecciona si hay uno solo. Reemplaza lo dicho en operacion.md/js-pages-operacion.md sobre "debe pertenecer a conductoresAsignados()" e incluirConductorDeLaOperacion() (eliminado).
