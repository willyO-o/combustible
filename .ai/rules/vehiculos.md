---
paths:
  - 'app/Http/Controllers/VehiculoController.php,resources/js/Pages/Vehiculos/Show.vue,routes/web.php'
---

# Vehiculos

## Botón "Finalizar" en el historial de asignaciones de conductor (Vehiculos/Show.vue)
Nueva acción `VehiculoController::finalizarAsignacionConductor()` (ruta PATCH `vehiculos/{vehiculo}/asignaciones/{asignacion}/finalizar`, nombre `vehiculos.asignaciones.finalizar`): mismo efecto que `ConductorController::finalizarAsignacion()` (marca `estado_asignacion=INACTIVO` + `fecha_culminacion=now()`, mismo `hasAnyRole(['super-admin','administrador','jefe-area'])`) pero es una acción SEPARADA porque esa otra hace `redirect()->route('conductores.index')` hardcodeado — reusarla desde Vehiculos/Show.vue habría sacado al usuario de la ficha del vehículo. Esta nueva redirige a `vehiculos.show($vehiculo)`. Si se necesita la misma acción desde un tercer lugar, replicar el patrón (no generalizar con parámetro de redirect: mantener las tres iguales en código, cada una simple).

`historialAsignaciones` en `VehiculoController::show()` ahora trae `puede_finalizar` (bool) calculado en PHP: `estado_asignacion` ACTIVO, o PROVISIONAL con `fecha_culminacion` null o >= hoy (mismo criterio "vigente" que `ConductorController::asignacionActual()`). El botón en el Vue usa `v-can="'conductores.asignar-vehiculo'"` (permiso ya existente, comparte el mismo rol que el backend) + confirmación con `confirm()` de `@/Utils/alertUtil.js` (patrón calcado de `finalizarAsignacion`/`finalizarAsignacionArea` en Conductores/Index.vue y Vehiculos/Index.vue).
