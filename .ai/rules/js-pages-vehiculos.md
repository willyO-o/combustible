---
paths:
  - 'app/Actions/Vehiculo/AsignarVehiculoAreaAction.php,app/Http/Controllers/VehiculoController.php,app/Http/Controllers/AreaController.php,app/Http/Requests/VehiculoRequest.php,resources/js/Pages/Vehiculos/Create.vue'
---

# Js Pages Vehiculos

## Asignar área a un vehículo: AsignarVehiculoAreaAction (también al registrarlo)
Desde 2026-11-05 (pedido del usuario): la regla "cerrar como REASIGNADO la asignación vigente del vehículo y crear la nueva" vive SOLO en AsignarVehiculoAreaAction; la usan VehiculoController::asignarArea() (modal del listado), AreaController::reasignarVehiculo() (ficha del área, sigue redirigiendo a areas.show) y VehiculoController::store(). Reemplaza la "copia exacta" descrita en areas.md. Al registrar un vehículo, Vehiculos/Create.vue muestra una tarjeta opcional "Asignación de Área" (sólo en alta y si User::puedeAsignarAreaAVehiculos() = permiso vehiculos.asignar-area + rol super-admin/administrador/jefe-area): id_area, estado_asignacion, fecha_culminacion, motivo_asignacion (VehiculoRequest::CAMPOS_ASIGNACION_AREA). VehiculoRequest: en POST id_area nullable + prohibitedIf sin permiso + área ACTIVO; el resto Rule::excludeIf(sin id_area); en PUT los 4 campos van 'exclude' (el área se cambia desde el modal). store() separa los campos con safe()->except/only, crea vehículo + asignación en una transacción y borra la foto subida si falla.
