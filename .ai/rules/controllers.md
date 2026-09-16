---
paths:
  - 'app/Http/Controllers/**/*.php'
  - 'app/Http/Controllers/*.php'
  - app/Http/Controllers/DashboardController.php
  - app/Http/Controllers/OrdenTrabajoController.php
---

# Controllers

## Controllers hold business logic; Actions are the exception
Write model-mutation and conditional logic directly in the controller method. Only extract to an app/Actions/{Domain}/{Verb}{Domain}Action class for complex, multi-step operations.

## No Policy classes — authorize via inline hasRole()
Authorize actions with inline auth()->user()->hasRole('role-name') conditionals in the controller/request, not Policy classes, Gate::define, $this->authorize(), the can middleware, or @can. A global Gate::before bypass in AppServiceProvider grants full access to the super-admin role.

## No repository or query-object layer
Query Eloquent directly in controllers for CRUD. Only domains that already have an app/Actions/{Domain} folder get a dedicated List*Action wrapping the query builder — don't introduce app/Repositories or app/Queries.

## Explicit eager-loading, no model $with defaults
Eager-load relations explicitly with ->with()/->load() at the call site, often scoping to selected columns via 'relation:col1,col2'. Don't set model-level $with defaults.

## Use response() helper for JSON, not Response:: facade
Return JSON with the response()->json([...]) helper. Don't use the Response:: facade.

## JSON responses via response()->json(), no API Resources
Return JSON via response()->json([...]) with raw Eloquent models/arrays, typically wrapped in data/message keys. Don't introduce API Resource classes (app/Http/Resources).

## Shape query results with ->get()->map()
Shape Eloquent query results for Inertia/JSON output with ->get()->map(fn ($x) => [...]) collection pipelines. Reserve foreach for side-effecting operations (file/report building, sync jobs).

## auth() helper in domain code, Auth:: only in Breeze auth flows
Use the auth() helper for current-user checks in domain code. The Auth:: facade appears only in the Breeze-generated authentication controllers (app/Http/Controllers/Auth/**) — don't extend Auth:: usage beyond that scaffolding.

## Dashboard: un permiso por widget + alcance por área para jefe-area
Cada widget del dashboard (4 tarjetas + gráfico) está detrás de un permiso `dashboard.tarjeta-*.ver` / `dashboard.grafico-combustible.ver`. El controlador SOLO calcula/envía la métrica si `$user->can(...)` (además del `v-can` en Dashboard.vue). Los reciben admin/super-admin/jefe-area (UserSeeder::permisosDashboardWidgets()).

Alcance: `DashboardController::idsVehiculosEnAlcance()` devuelve null (admin/super-admin ven todo) o una Collection de ids de vehículo cuando es jefe-area (vía `Persona::encargadoAreas()` + `Vehiculo::areasAsignadas`), y TODA métrica se filtra por esos vehículos (cargas, vales, vehículos, conductores por asignación, y `CargaCombustible::reporteCargaCombustibleMes($anio, $idVehiculos)`).

conductor: sin widgets todavía (gráficas propias = iteración futura); Dashboard.vue muestra un empty state si el usuario no tiene ninguno de los 5 permisos.

`CargaCombustible::reporteCargaCombustibleMes()` se reescribió para agrupar por mes en PHP (no `MONTH()`/`groupByRaw`) para funcionar en SQLite (tests) y MySQL.

## Dashboard: gráfico de órdenes de trabajo por estado (técnico de mantenimiento)
Widget adicional al de los 5 de `permisosDashboardWidgets()`: permiso `dashboard.grafico-ordenes.ver`, gráfico donut de órdenes de trabajo agrupadas por `estado_orden` (PENDIENTE/EN_EJECUCION/CULMINADO/VERIFICADO/CANCELADO).

Lo reciben: tecnico-mantenimiento (en `permisosParaTecnicoMantenimiento()`) y administrador (en `todosLosPermisos()`, línea suelta — NO está en `permisosDashboardWidgets()` para no dárselo a jefe-area). super-admin por bypass.

`DashboardController::metricaOrdenesPorEstado()`: el COUNT/GROUP BY va en la consulta (`->groupBy('estado_orden')->selectRaw('estado_orden, COUNT(*) as total')->pluck('total','estado_orden')`); en PHP sólo se ordena por flujo del estado, se traducen etiquetas y se descartan estados con 0. Devuelve `{labels:[], series:[]}`. Un técnico "puro" (sin rol de gestión) sólo ve sus órdenes (`id_usuario_ejecuta`), igual criterio que `OrdenTrabajoController::esSoloTecnico()`.

Frontend: `Dashboard.vue` prop `ordenesPorEstado`, `<Apexchart type="donut">` con `v-can` + `v-if` (mostrarOrdenes). Convención confirmada por el usuario: los cálculos de agregación se hacen en la BD siempre que se pueda.

## Dashboard: gráfico de horas trabajadas por día/semana (rol conductor)
Permiso `dashboard.grafico-horas.ver`: lo reciben conductor (`permisosParaConductor()`) y administrador (línea suelta en `todosLosPermisos()`, junto a `dashboard.grafico-ordenes.ver`); super-admin por bypass. NO jefe-area ni técnico.

`DashboardController::metricaHorasTrabajadas()`: SUM(horas_trabajadas) de `operacion_diaria` agrupado por `DATE(fecha_inicio)` en la BD (`groupByRaw('DATE(fecha_inicio)')` + `selectRaw` — portable SQLite/MySQL, cubre 42 días). El reparto de esos totales diarios en semanas (lunes-domingo) se hace en PHP porque la función de semana no es portable entre motores. Devuelve `{ dia: {labels,series}, semana: {labels,series} }` (últimos 7 días / 6 semanas). Un conductor "puro" (sin rol de gestión) sólo ve sus operaciones (`id_conductor = $user->id_persona`, mismo criterio que ListOperacionesDiariasAction); admin ve todas.

Frontend `Dashboard.vue`: gráfico de barras (`type: 'bar'`) con toggle "Por día / Por semana" (radios, mismo patrón que "Gastos de Combustible por Mes"), prop `horasTrabajadas`, `v-can` + `v-if` (mostrarHoras). Siempre visible para el conductor (la serie trae 7 puntos aunque sean 0).

## Notificación al técnico cuando se le asigna una orden de trabajo
Al emitir (`store`) o reasignar (`update`, sólo si `id_usuario_ejecuta` cambió) una orden de trabajo se dispara `OrdenTrabajoAsignada::dispatch($orden)`. El listener `NotificarOrdenTrabajoAsignada` (auto-descubierto, síncrono) manda `OrdenTrabajoAsignadaNotification` (canal `database`) al `usuarioEjecuta`.

Mismo patrón que `ObservacionOperacionEvent` / `NotificarObservacionOperacion` / `ObservacionOperacionNotification`. La notificación lleva `data['tipo'] => 'orden_trabajo_asignada'` + `nro_orden`, `id_orden_trabajo`, `id_vehiculo`, `url` (ruta a `mantenimiento.ordenes.show`).

Todo tipo de notificación nuevo debe añadir su `match` en AMBOS formateadores: `HandleInertiaRequests::formatearNotificacion()` (dropdown web) y `Api/V1/NotificacionController::formatear()` (API). Ícono usado: `ri-tools-line`.

## Notificaciones de orden culminada/verificada, vale emitido/por vencer y carga registrada
Mismo patrón Event+Listener+Notification(canal database) que OrdenTrabajoAsignada, ampliado con 3 features nuevas (6 tipos):
- `OrdenTrabajoCulminada` → `NotificarOrdenTrabajoCulminada` → notifica a `usuarioEmite`. Se dispara en 3 sitios: OrdenTrabajoController::cambiarEstado() (si nuevoEstado=CULMINADO) y ::culminarEjecucion() (web), y Api/V1/OrdenTrabajoController::culminar().
- `OrdenTrabajoVerificada` → notifica a `usuarioEjecuta`. Sólo se dispara en OrdenTrabajoController::cambiarEstado() (VERIFICADO sigue siendo sólo web).
- `ValeEmitido` → notifica a `vale->conductor->user` (puede no existir cuenta). Se dispara en ValeController::store() web y Api/V1/ValeController::store().
- Vale "por vencer": sin Event, va directo por Console\Commands\NotificarValesPorVencerCommand (`vales:notificar-vencimiento`, `Schedule::command()->daily()` en routes/console.php), que notifica PENDIENTE con fecha_vencimiento en [now, now+2 días] y marca `vale.notificado_vencimiento_at` (columna nueva) para no repetir el aviso.
- `CargaCombustibleRegistrada` → notifica a `carga->vale->user` (sólo si la carga tiene vale; PREPAGO sin vale no notifica). Se dispara DENTRO de CreateCargaCombustibleAction::execute() (Action existente, compartida por web+API) — no en los controladores, por la regla de Actions.
- `CargaMaterialRegistrada` → notifica a `User::role(['jefe-area','administrador'])->where('estado_usuario','ACTIVO')`. Se dispara en CargaMaterialController::store() web y Api/V1/CargaMaterialController::store() (sin Action compartida, hay que tocar los 2 controladores).
Los 6 `tipo` nuevos se agregaron en AMBOS formateadores (HandleInertiaRequests::formatearNotificacion() y Api/V1/NotificacionController::formatear(), ver regla existente sobre esto) y en el enum de `Notificacion.tipo` de openapi.yaml (bump 1.8.0→1.9.0 + CHANGELOG-openapi.md, también en ParametrosController::index()['api_version']).
