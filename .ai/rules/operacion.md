---
paths:
  - 'app/Http/Controllers/OperacionDiariaController.php,app/Actions/OperacionDiaria/*.php,app/Http/Requests/OperacionStoreRequest.php,app/Models/Vehiculo.php,resources/js/Pages/Operacion/Create.vue'
---

# Operacion

## Operación Diaria: combo de conductor sólo para roles distintos de conductor
Un vehículo puede tener varios conductores asignados a la vez (1 titular ACTIVO + otros PROVISIONAL por permiso/vacaciones, ver Vehiculo::conductoresAsignados() — ACTIVO/PROVISIONAL con fecha_culminacion nula o futura; distinto de conductorAsignado() singular, que sólo trae el titular ACTIVO).

En Operacion/Create.vue el campo "Conductor" (Multiselect) sólo se muestra cuando el backend manda mostrarSelectorConductor=true (= !hasRole('conductor')): un usuario con rol conductor siempre opera su propio vehículo asignado, sin ambigüedad, así que no elige nada y el controller le resuelve id_conductor = su propio Conductor (OperacionDiariaController::datosConConductorResuelto(), usado en store() y update()). Para cualquier otro rol, id_conductor es obligatorio en la request (OperacionStoreRequest, requiredIf en POST) y debe pertenecer a los conductoresAsignados() del vehículo — lo valida CreateOperacionDiariaAction/UpdateOperacionDiariaAction lanzando ConductorNoAsignadoException si no.

OperacionDiariaController::vehiculosDisponibles() sólo resuelve la lista de conductoresAsignados por vehículo (meta.conductoresAsignados) cuando el usuario NO es conductor, en una sola consulta agrupada (conductoresAsignadosPorVehiculo()) para evitar N+1 con ~200 vehículos.

## Operación Diaria web: roles combinados usan User::esConductorPuro(), no hasRole('conductor')
BUG corregido 2026-10-14 (reportado: jefes de área con rol conductor no podían registrar operaciones). En la web, mostrarSelectorConductor, datosConConductorResuelto(), la carga de meta.conductoresAsignados (vehiculosDisponibles) y el Rule::requiredIf de id_conductor en OperacionStoreRequest usaban hasRole('conductor'): un jefe-area/administrador que también era conductor no veía el combo de operarios y se le forzaba id_conductor = él mismo -> ConductorNoAsignadoException. Ahora TODO ese criterio usa User::esConductorPuro() (rol de gestión prevalece), igual que Api\V1. Además administrador/super-admin ve todos los vehículos activos y todas las actividades sugeridas aunque también sea conductor/jefe-area. La unión conductor+jefe-area de vehículos se mantiene. OperacionStoreRequest es compartida con la API: id_conductor ahora es obligatorio en POST para jefe-area+conductor también vía API. Tests: OperacionDiariaControllerTest *_tambien_es_conductor_*.
