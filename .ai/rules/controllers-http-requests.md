---
paths:
  - 'resources/js/Pages/Conductores/**,app/Http/Controllers/ConductorController.php,app/Http/Requests/ConductorRequest.php'
---

# Controllers Http Requests

## Conductores usa un Form.vue único (alta+edición) con asignación de vehículo opcional
resources/js/Pages/Conductores/Form.vue es el formulario reutilizable para crear Y editar operarios (patrón `esEdicion = computed(() => !!props.conductor)`, `_method:'PUT'` al editar, submit a conductores.store/update). Ya NO existen Create.vue ni Edit.vue. ConductorController::create() y edit() renderizan 'Conductores/Form' y pasan `vehiculos` (todos los ACTIVO, con `conductor_asignado` = nombre del operario que lo tiene, o null, para la etiqueta del <Multiselect>); edit() además pasa `asignacionActual`.

El formulario permite asignar 1 vehículo opcional (switch `asignar_vehiculo`). La lógica de asignación vive en ConductorController::registrarAsignacionVehiculo() — helper COMPARTIDO por store(), update() y asignarVehiculo() (el modal del Index): cierra como REASIGNADO la asignación vigente previa del conductor y libera el vehículo de otro conductor; reasignar el mismo vehículo+estado es no-op. store()/update() sólo procesan la asignación si el usuario es super-admin/administrador/jefe-area (autorizarAsignacionSiCorresponde(), mismo criterio que AsignacionRequest). Las reglas de asignación (id_vehiculo, estado_asignacion, kilometraje/horometro_inicial según tipo_medicion, etc.) están en ConductorRequest y sólo aplican cuando se envía id_vehiculo. Vale como referencia paralela app/Actions/Personas/{Create,Update}PersonaAction.php.

## Conductores: asignación de vehículo SÓLO al registrar (no al editar)
Actualiza la regla previa: la asignación de vehículo en el Form.vue de operarios sólo existe en ALTA. En EDICIÓN el bloque es de sólo lectura (muestra `asignacionActual` + un enlace al listado); no hay controles editables.

- `ConductorController::create()` pasa `vehiculos`; `edit()` NO (sólo `asignacionActual`).
- `ConductorController::store()` procesa `id_vehiculo` vía `registrarAsignacionVehiculo()` (helper compartido sólo con `asignarVehiculo()`, el modal del Index). `update()` ya NO toca asignaciones.
- `ConductorRequest`: las reglas de asignación viven en `reglasAsignacionVehiculo()` y sólo se aplican cuando NO hay `$this->route('conductor')` (es decir, sólo en store). Un PUT con `id_vehiculo` se ignora por completo.
- Precedente del proyecto: Operacion/Create.vue también bloquea el cambio de vehículo al editar.

Diseño (Form.vue): tokens del tema en `resources/css/my-styles.css` bajo prefijo `.op-form` (foto como dropzone circular, panel de asignación que se despliega al elegir vehículo — sin `.form-switch` por el bug de @tailwindcss/forms —, barra de acciones al pie, cabeceras de tarjeta con ícono). Cubierto por tests/Feature/ConductorControllerTest.php.
