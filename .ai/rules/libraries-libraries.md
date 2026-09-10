---
paths:
  - 'app/Libraries/Reportes.php,app/Libraries/ReportesExcel.php'
---

# Libraries Libraries

## Tablas de los reportes: relleno gris #c9c9c9 + texto negro (nunca azul)
Por convención de la empresa, TODA la "chrome" de las tablas de los 7 reportes de la sección REPORTES usa relleno gris #c9c9c9 (RGB 201,201,201) con texto NEGRO (30,30,30 en PDF / 1E1E1E en Excel), nunca azul. Aplica a: barra de título de sección, fila de encabezados de columna, fila de grupos (colspans de la bitácora) y fila de TOTALES. En PDF esto ya era así para generarReporteFlete/generarReporteOperacionDiaria/generarSolicitudMantenimiento; ahora también para generarReporteCargasCombustible, generarReporteRendimiento, generarReporteControlCargas, generarReporteControlCargasDetalle, generarReporteDetalleRendimiento, generarReporteOperacionDiariaUso y generarReporteOperacionDiariaDetalle (que además dejó de usar sus tintes celeste/amarillo). Cada método define `$grisEncabezado = [201, 201, 201];` en su bloque de colores; los bordes de esas easyTable y del recuadro RESUMEN pasaron a negro.

NO se tocaron (a propósito): las tarjetas KPI de resumen (siguen multicolor verde/rojo/gris en PDF; en Excel el rótulo pasó a gris pero es un caso aparte), el recuadro del encabezado del documento (borde azul), el título del reporte (texto azul) y la franja de acento superior `$verde`. El usuario pidió sólo "el relleno de las tablas".

Excel: App\Libraries\ReportesExcel usa `const GRIS_RELLENO = 'C9C9C9'` en pintarTituloSeccion / pintarTarjetas / pintarTabla (grupos, encabezado, TOTALES). Se eliminaron las consts GRIS_ENCABEZADO y BLANCO (quedaron sin uso). AZUL sólo se usa ya para el título del reporte.

Verificado visualmente renderizando los PDF a PNG con PyMuPDF y leyendo los .xlsx con IOFactory. Los 129 tests de reportes + PDFs siguen verdes.
