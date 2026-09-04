---
paths:
  - 'app/Http/Controllers/CargasCombustibleReportController.php,app/Libraries/Reportes.php,resources/js/Pages/Reportes/CargasCombustibleReporte.vue'
---

# Pages Reportes

## Reporte de Cargas de Combustible admite filtro por Tipo de Vehículo
El filtro `id_tipo_vehiculo` (campo propio de vehiculo, 1 vehículo = 1 tipo, igual criterio que id_tipo_combustible) se agregó a: `vehiculosParaFiltro()` y `obtenerResumen()` en CargasCombustibleReportController (acota el select de vehículo y la tabla resumen), a `generarPDF()`/`Reportes::generarReporteCargasCombustible()` (filtra vía `whereHas('vehiculo', ...)` porque esa query no tiene join propio a vehiculo), y al combo `<select>` (NO Multiselect: es un catálogo fijo pequeño, mismo criterio que tipo_combustible/área) en CargasCombustibleReporte.vue. El PDF muestra el filtro aplicado como línea extra en el encabezado (alto dinámico `$h1`, mismo patrón que generarReporteRendimiento). Si se agrega este filtro a otros reportes de combustible (rendimiento, detalle), replicar el mismo patrón en vez de improvisar uno nuevo. Nota: `CargasCombustibleReportController::generarPDF()` no tiene test HTTP (usa `$this->Output('I', ...)` sin retornar Response, como vales.imprimir) — la cobertura de este filtro vive en el test del índice (`obtenerResumen`/`vehiculosParaFiltro`), no en un test de PDF.
