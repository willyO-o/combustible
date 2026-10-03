---
paths:
  - 'app/Http/Controllers/SolicitudMantenimientoController.php,app/Http/Controllers/Api/V1/SolicitudMantenimientoController.php,app/Http/Requests/SolicitudMantenimientoRequest.php,app/Actions/SolicitudMantenimiento/CreateSolicitudMantenimientoAction.php,app/Models/SolicitudMantenimiento.php,resources/js/Pages/SolicitudMantenimiento/Create.vue'
---

# Solicitud Mantenimiento

## Registrar solicitud de mantenimiento: vehículo/conductor por rol, jefe-area prevalece
POST (crear) ahora lo pueden hacer conductor, jefe-area, administrador y super-admin — no sólo conductor como antes; PUT (editar) sigue siendo sólo del conductor autor (`SolicitudMantenimientoRequest::authorize()` distingue por `$this->isMethod('POST')`).

`esConductorFinal($user)` = hasRole('conductor') && !hasAnyRole(['jefe-area','administrador','super-admin']) — a diferencia de Operación Diaria (donde el rol conductor SIEMPRE gana, ver [[operacion]]), aquí **jefe-area (o cualquier rol de gestión) prevalece sobre conductor**: un usuario con ambos roles ve los vehículos de su área y debe elegir el conductor, no opera automáticamente sobre sí mismo. Este criterio se duplica idéntico en 3 lugares (sin capa compartida, patrón ya usado en el proyecto): SolicitudMantenimientoController::esConductorFinal(), SolicitudMantenimientoRequest::esConductorFinal() (privado) y CreateSolicitudMantenimientoAction::execute() (usa `User::esConductorPuro()`; antes usaba `!hasRole('jefe-area') && hasRole('conductor')`, que forzaba a un administrador+conductor a registrar para sí mismo) — si se toca uno, tocar los tres.

Vehículos disponibles (SolicitudMantenimientoController::vehiculosDisponibles()): esConductorFinal → sus asignacionesActivas; jefe-area → vehículos de sus áreas a cargo (whereHas('areasAsignadas', ...), mismo patrón que ValeController::restringirVehiculosPorAreaDeJefe); cualquier otro rol → todos los activos. Cada opción trae meta.conductoresAsignados (Vehiculo::conductoresAsignados(), ACTIVO/PROVISIONAL vigentes) resuelto en una sola consulta agrupada (conductoresAsignadosPorVehiculo(), evita N+1) — sólo se resuelve cuando NO es esConductorFinal. Frontend (SolicitudMantenimiento/Create.vue) replica el patrón de Operacion/Create.vue: Multiselect de vehículo (options=vehiculos), y un segundo Multiselect de conductor sólo si mostrarSelectorConductor=true, poblado localmente desde `vehiculoSeleccionado.meta.conductoresAsignados` (sin endpoint remoto).

Validación (SolicitudMantenimientoRequest::rules()): id_conductor es 'required'+exists-en-asignacion-del-vehiculo sólo cuando POST && !esConductorFinal; si no, 'nullable' (no aplica). id_vehiculo usa Rule::exists('vehiculo','id')->where('estado_vehiculo','ACTIVO') para jefe-area/admin, o la regla original (asignacion propia del conductor) en cualquier otro caso — sin duplicar la restricción de área del jefe a nivel de validación (mismo nivel de rigor que OperacionStoreRequest, que tampoco la re-valida al guardar).

SolicitudMantenimiento::boot() creating() ya NO fuerza siempre `id_conductor = Auth::user()->id_persona` (rompía la selección de un jefe-area) — sólo lo completa si llega vacío. CreateSolicitudMantenimientoAction fuerza id_conductor = id_persona del usuario SÓLO cuando esConductorFinal (evita que un conductor spoofee otro id_conductor).

API: mismo Request/Action que la web (Api/V1/SolicitudMantenimientoController::store() no tenía lógica propia) — el cambio se propaga solo. Documentado en openapi.yaml + CHANGELOG-openapi.md 1.3.0 (info.version, api_version en ParametrosGenerales y ParametrosController::index()).
