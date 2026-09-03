---
paths:
  - 'resources/js/Pages/Reportes/OperacionDiariaReporte.vue,resources/js/Pages/Reportes/OperacionDiariaDetalle.vue'
---

# Reportes

## La tabla "Detalle por Vehículo" del reporte de uso enlaza a la bitácora por vehículo
En `OperacionDiariaReporte.vue` (reporte de uso), cada fila de la tabla "Detalle por Vehículo" tiene una columna "Bitácora" con un `<Link>` (`btn btn-sm btn-icon btn-primary-light`, icono `ri-booklet-line`, `v-can="'operacion-diaria.reporte.detalle'"`) a `route('operacion-diaria.reporte.detalle.index', { id_vehiculo: v.id_vehiculo, fecha_inicio, fecha_fin })` — arrastra el rango de fechas actual (con `|| undefined`, si está vacío el controller de `detalle()` reaplica "Este mes"). No hubo cambio de backend: `detalle()` ya acepta ese shape de query y está cubierto por OperacionDiariaDetalleReportTest. Si se agregan/quitan columnas a esa tabla, ajustar el `colspan` del empty state (13) y el nº de `<td>` de la fila TOTALES.
