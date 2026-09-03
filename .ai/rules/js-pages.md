---
paths:
  - 'app/Models/IntervaloMantenimientoTipo.php,app/Http/Controllers/DashboardController.php,app/Http/Controllers/VehiculoController.php,resources/js/Pages/Vehiculos/Show.vue,resources/js/Pages/Dashboard.vue'
---

# Js Pages

## Alertas de mantenimiento: cálculo por múltiplos + holgura 5% en IntervaloMantenimientoTipo
Las "alertas de mantenimiento" de un vehículo se calculan en `IntervaloMantenimientoTipo::alertasMantenimiento(?array $idVehiculos)` (Collection por vehículo+intervalo) y `::resumenAlertasVehiculos(?array $idVehiculos)` (agrupado por vehículo, sólo VENCIDO/PROXIMO, para el dashboard).

Fuente de la lectura actual: `MAX(carga_combustible.kilometraje|horometro)` (no la fila más reciente; las lecturas son monótonas), excluyendo `estado_carga='ANULADO'`, según `intervalo_mantenimiento_tipo.tipo_medicion`. Último mantenimiento hecho: `MAX(detalle_mantenimiento.kilometraje|horometro)` join `orden_trabajo` por `id_vehiculo` + `id_tipo_mantenimiento` (sin filtrar estado de la orden). Todo eso va en UNA consulta `DB::table()` con subconsultas correlacionadas en `selectRaw` (portable SQLite/MySQL); la aritmética por fila (holgura = frecuencia*0.05, ciclos = floor((ultimo+holgura)/frecuencia), proximo_objetivo = (ciclos+1)*frecuencia, restante = objetivo - lectura, estado) se hace en PHP en `evaluarAlerta()`. Estados: SIN_DATOS (sin lectura), VENCIDO (restante < -holgura), PROXIMO (|restante| <= holgura), AL_DIA.

Dashboard: widget "Próximos Mantenimientos" tras el permiso `dashboard.mantenimiento-alertas.ver` (en UserSeeder: `todosLosPermisos()` para administrador, `permisosParaConductor()`, `permisosParaJefeArea()` — NO tecnico-mantenimiento, para no romper `UserSeederTest` que usa assertEqualsCanonicalizing). Alcance en `DashboardController::idsVehiculosParaAlertas()` (helper aparte de `idsVehiculosEnAlcance()` porque el conductor SÍ se acota aquí a sus `persona->vehiculosAsignados()`): admin/super-admin → null (todos), jefe-area → vehículos de `encargadoAreas()`, conductor → sus asignados, resto → collect() vacío. `VehiculoController::show` publica `alertasMantenimiento` sin permiso extra (ya está tras `vehiculos.ver`).
