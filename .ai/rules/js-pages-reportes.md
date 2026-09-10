---
paths:
  - 'app/Libraries/ReportesExcel.php,app/Http/Controllers/CargasCombustibleReportController.php,app/Http/Controllers/ControlCargasReportController.php,app/Http/Controllers/OperacionDiariaReportController.php,resources/js/Pages/Reportes/**'
---

# Js Pages Reportes

## Reportes en Excel: ReportesExcel.php es espejo del PDF, no lo reemplaza
Los 7 reportes de la sección REPORTES tienen ahora salida PDF **y** Excel. Los PDF (App\Libraries\Reportes, FPDF) NO se tocaron: el .xlsx lo genera App\Libraries\ReportesExcel (phpoffice/phpspreadsheet ^5.9), con un método público por reporte que refleja al de Reportes.php.

Contrato de ReportesExcel: NO consulta la base de datos. Recibe exactamente los mismos arrays/colecciones ya agregados que el controlador le pasa al PDF (obtenerResumen(), obtenerDetalleVehiculo(), obtenerResumenRendimiento(), …) y devuelve el binario del .xlsx, igual que el modo 'S' de FPDF; el controlador lo entrega con response($contenido, 200, [Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, Content-Disposition: attachment]). Si agregas un reporte, sigue ese patrón: el agregado va en el controlador, nunca dentro de la librería de Excel.

Excepción conocida: CargasCombustibleReportController::generarExcel() usa obtenerResumen() del propio controlador, porque el PDF equivalente (Reportes::generarReporteCargasCombustible) arma su consulta por dentro — se prefirió reutilizar el agregado existente antes que duplicar esa query en Excel. Son los mismos datos que ve la tabla del PDF.

Rutas: `.excel` al lado de cada `.pdf` (cargas-combustible.reporte.{excel,rendimiento.excel,rendimiento.detalle.excel}, control-cargas.reporte.{excel,detalle.excel}, operacion-diaria.reporte.{uso.excel,detalle.excel}). Permisos `.excel` propios (separados de los `.pdf`, mismo criterio que el resto) en UserSeeder; sin gating de backend, sólo `v-can` en el botón verde `btn-success` + `ri-file-excel-2-line` junto al de PDF. Tras desplegar hay que correr `php artisan db:seed --class=UserSeeder` o los botones no aparecen.

Vue: el botón de Excel usa `window.location.href = route(...)` (no `window.open`), porque el .xlsx viaja como attachment y una pestaña nueva quedaría en blanco.

Detalles de la librería: los números se escriben como números con formato de celda (nunca texto preformateado) para que se puedan sumar/ordenar; los valores nulos quedan como celda vacía, NO como 0 (un 0 falsearía sumas y promedios); el logo repite la validación de Reportes::logoEmpresa() (is_file + getimagesize, si no, logo-plus-metals.png). Cobertura: cada endpoint tiene test de generación + de validación, y ControlCargasReportControllerTest::test_el_excel_es_un_libro_legible_con_los_datos_del_reporte reabre el .xlsx con IOFactory y verifica los valores — si tocas columnas de ese reporte, ese test se cae.
