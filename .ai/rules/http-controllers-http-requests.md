---
paths:
  - 'app/Http/Controllers/OrdenTrabajoController.php,app/Http/Requests/OrdenTrabajoRequest.php'
---

# Http Controllers Http Requests

## Orden de Trabajo: una solicitud sólo puede generar UNA orden
El listado `solicitudesPendientes` de create() ya filtraba `estado=PENDIENTE` + `whereDoesntHave('ordenTrabajo')` (store() deja la solicitud en APROBADA al emitir la orden), pero eso sólo ocultaba la opción del combo — el propio `store()`/`update()` no revalidaba nada, así que un doble-submit o un POST directo con un `id_solicitud_mantenimiento` ya usado creaba una segunda orden para la misma solicitud (sin unique constraint en `orden_trabajo.id_solicitud_mantenimiento`).

Cerrado en `OrdenTrabajoRequest::rules()`: `id_solicitud_mantenimiento` ahora valida con una closure que exige `estado=PENDIENTE` y ausencia de `OrdenTrabajo::where('id_solicitud_mantenimiento', $value)` — EXCEPTO cuando `$this->route('orden')` (edición) ya es la orden dueña de esa misma solicitud (si no, editar una orden ya emitida desde una solicitud rompería, porque esa solicitud está APROBADA y con esta misma orden). `OrdenTrabajoController::create()` también filtra `solicitudPreseleccionada` (`?solicitud=` query param) con el mismo `estado=PENDIENTE` + `whereDoesntHave('ordenTrabajo')`, para no preseleccionar una solicitud que otro usuario ya procesó mientras el link estaba abierto.

`OrdenTrabajo/Edit.vue` no muestra selector de solicitud (no recibe `solicitudesPendientes`): reenvía `id_solicitud_mantenimiento` tal cual venía en la orden, por eso la excepción de "es la propia orden" en la validación es indispensable.
