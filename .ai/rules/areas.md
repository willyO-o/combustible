---
paths:
  - 'app/Http/Controllers/AreaController.php,resources/js/Pages/Areas/**,routes/web.php'
---

# Areas

## Areas: ficha de detalle (areas.show) con vehículos asignados
`AreaController::show(Area $area)` → página Inertia `Areas/Show`. Ruta: se agregó `show` al `Route::resource('areas', ...)->only([...])` (URL `areas/{area}`, nombre `areas.show`). Sin permiso nuevo: usa `areas.ver` (mismo que el índice) y `v-can` en el botón "ojo" (`ri-eye-line`) del índice — NO hay gating `can:` en backend, igual que el resto de áreas (sólo menú + v-can).

Props que envía show(): `area` (datos + created_at formateado), `resumen` (contadores), `encargados` (todos, vigentes primero, con flag `vigente`), `vehiculos` (SÓLO asignaciones vigentes vía `Area::vehiculosActivos()`, con conductor actual vía `conductorAsignado.persona`), `historialVehiculos` (TODAS las filas de `vehiculo_area` del área, incluidas REASIGNADO/CULMINADO).

Detalle clave: el histórico de vehículos se arma con `DB::table('vehiculo_area')->join('vehiculo')` (una fila por asignación), NO con la relación belongsToMany `Area::vehiculos()` — esa deduplica por vehículo y perdería asignaciones repetidas del mismo vehículo. Las fechas de los pivots de `Area::vehiculosActivos()`/`->encargados()` llegan como string crudo (pivot genérico, sin `->using()`), así que se formatean con un closure `$fmt = fn ($f) => $f ? Carbon::parse($f)->format('d/m/Y') : null` — NO con `optional($pivot->fecha)->format(...)` (devuelve null silenciosamente sobre un string; ese bug ya existe en `AreaController::index()` para las fechas de encargados, sin corregir).

La Vue reproduce el patrón de `Vehiculos/Show.vue` (cards + tablas `table-responsive`, badges de estado, breadcrumb + botón Volver). Cada fila de "Vehículos Asignados" enlaza a `vehiculos.show`. Cubierto por AreaControllerTest (6 tests `test_show_*`).

## Areas/Show: reasignar / finalizar asignación de vehículo desde la ficha
La tabla "Vehículos Asignados" de `Areas/Show.vue` tiene columna "Culminación" y un botón "Cambiar asignación" (`ri-exchange-line`, `v-can="'vehiculos.asignar-area'"`) que abre un modal (useBootstrapModal) con: la asignación actual + botón "Finalizar", y un formulario para reasignar (área, tipo ACTIVO/PROVISIONAL, fecha culminación si PROVISIONAL, motivo). Se movieron los detalles al modal para no ensanchar la tabla.

Backend — 2 acciones NUEVAS en AreaController (no se reutilizan las de VehiculoController porque redirigen a `vehiculos.index` y sacarían al usuario de la ficha, mismo criterio que `finalizarAsignacionConductor`):
- `reasignarVehiculo(VehiculoAreaRequest, Area, Vehiculo)` — copia exacta del cuerpo de `VehiculoController::asignarArea()` (cierra como REASIGNADO la asignación vigente del vehículo sin importar el área, crea la nueva), redirige a `areas.show($area)`. Ruta `POST areas/{area}/vehiculos/{vehiculo}/asignacion` = `areas.vehiculos.reasignar`.
- `finalizarAsignacionVehiculo(Request, Area, VehiculoArea $asignacion)` — copia de `VehiculoController::finalizarAsignacionArea()` (CULMINADO + fecha_culminacion=now, `hasAnyRole(['super-admin','administrador','jefe-area'])`, 404 si `$asignacion->id_area !== $area->id`), redirige a `areas.show`. Ruta `PATCH areas/{area}/asignaciones-vehiculo/{asignacion}/finalizar` = `areas.vehiculos.finalizar`.

`show()` ahora también envía `areasDisponibles` (Area ACTIVO, id+nombre) para el select del modal, y cada item de `vehiculos` trae `id_asignacion` (pivot id) para la ruta de finalizar. Cubierto por AreaControllerTest (`test_reasignar_*`, `test_finalizar_asignacion_de_vehiculo_*`).
