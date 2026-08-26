---
paths:
  - 'app/Http/Controllers/OperacionDiariaController.php,app/Actions/OperacionDiaria/*.php,app/Http/Requests/OperacionStoreRequest.php,app/Models/Vehiculo.php,resources/js/Pages/Operacion/Create.vue'
---

# Operacion

## Operación Diaria: combo de conductor sólo para roles distintos de conductor
Un vehículo puede tener varios conductores asignados a la vez (1 titular ACTIVO + otros PROVISIONAL por permiso/vacaciones, ver Vehiculo::conductoresAsignados() — ACTIVO/PROVISIONAL con fecha_culminacion nula o futura; distinto de conductorAsignado() singular, que sólo trae el titular ACTIVO).

En Operacion/Create.vue el campo "Conductor" (Multiselect) sólo se muestra cuando el backend manda mostrarSelectorConductor=true (= !hasRole('conductor')): un usuario con rol conductor siempre opera su propio vehículo asignado, sin ambigüedad, así que no elige nada y el controller le resuelve id_conductor = su propio Conductor (OperacionDiariaController::datosConConductorResuelto(), usado en store() y update()). Para cualquier otro rol, id_conductor es obligatorio en la request (OperacionStoreRequest, requiredIf en POST) y debe pertenecer a los conductoresAsignados() del vehículo — lo valida CreateOperacionDiariaAction/UpdateOperacionDiariaAction lanzando ConductorNoAsignadoException si no.

OperacionDiariaController::vehiculosDisponibles() sólo resuelve la lista de conductoresAsignados por vehículo (meta.conductoresAsignados) cuando el usuario NO es conductor, en una sola consulta agrupada (conductoresAsignadosPorVehiculo()) para evitar N+1 con ~200 vehículos.
