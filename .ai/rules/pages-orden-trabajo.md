---
paths:
  - resources/js/Pages/OrdenTrabajo/Create.vue
---

# Pages Orden Trabajo

## OrdenTrabajo/Create.vue: Solicitud/Vehículo/Conductor son Multiselect con búsqueda
Los 3 combos "Solicitud de Origen", "Vehículo" y "Conductor" usan `<Multiselect>` (@vueform/multiselect, filtrado local, ver .ai/rules/pages.md) en vez de `<select>` nativo. Técnico/Categoría/Taller siguen siendo `<select>` nativos (no pedido, catálogos chicos).

`solicitudesPendientes` y `vehiculos` no traen `label` desde el backend: se sintetiza client-side con `solicitudesOpt`/`vehiculosOpt` (computed que hace spread + agrega `label`), sin tocar el controller. `conductoresDelVehiculo` sí trae `label` ya armado desde el backend (OrdenTrabajoController::vehiculosConConductores()).

La lógica de "al elegir/quitar la solicitud de origen, copiar o liberar vehículo/conductor/categoría/km" que antes vivía en un handler `@change` (onSolicitudChange) ahora es un `watch(() => form.id_solicitud_mantenimiento, ...)`, porque Multiselect no expone un evento @change equivalente al de un `<select>` nativo — mismo patrón que los watch ya usados para id_vehiculo en esta página y en Operacion/Create.vue.
