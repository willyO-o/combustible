---
paths:
  - 'app/Http/Controllers/OrdenTrabajoController.php,app/Http/Requests/OrdenTrabajoRequest.php,app/Models/SolicitudMantenimiento.php,resources/js/Pages/OrdenTrabajo/Create.vue'
---

# Orden Trabajo

## Emitir orden de trabajo sin solicitud de origen genera una solicitud APROBADA automáticamente
El reporte de mantenimiento exige que toda orden tenga una solicitud vinculada. Si Create.vue no elige "Solicitud de Origen", junto al combo de Vehículo aparece un combo de Conductor (opcional, no obligatorio: hay vehículos sin conductor asignado) poblado desde `vehiculos[].conductoresAsignados` (OrdenTrabajoController::vehiculosConConductores()/conductoresAsignadosPorVehiculo(), mismo patrón de consulta agrupada que SolicitudMantenimientoController, ver [[solicitud-mantenimiento]]).

En store() (OrdenTrabajoController), si no llega `id_solicitud_mantenimiento`: se crea una SolicitudMantenimiento con estado='APROBADA' explícito (no PENDIENTE) usando los datos del propio formulario (id_vehiculo, id_conductor, tipo_mantenimiento, kilometraje/horometro; `descripcion_problema` toma `nota_emisor` o, si viene vacío, un texto por defecto, porque esa columna es NOT NULL), y la OrdenTrabajo se engancha a ella. Todo el método corre dentro de `DB::transaction()` por tocar 2 tablas.

Para permitir el estado explícito, `SolicitudMantenimiento::boot()` ya NO fuerza `estado = 'PENDIENTE'` siempre: sólo lo completa si llega vacío (mismo criterio ya aplicado a `id_conductor`, ver [[solicitud-mantenimiento]]).

`OrdenTrabajoRequest::rules()`: `id_conductor` sigue 'nullable', pero si se manda SIN solicitud de origen debe estar realmente asignado al `id_vehiculo` (Asignacion::where(...)->exists(), una sola consulta). Si SÍ hay solicitud de origen, este chequeo se omite (el conductor viene heredado de la solicitud y pudo reasignarse desde que se generó — no es motivo de rechazo).
