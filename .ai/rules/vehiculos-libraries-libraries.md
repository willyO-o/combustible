---
paths:
  - 'app/Http/Controllers/VehiculoController.php,app/Http/Controllers/ConductorController.php,app/Http/Controllers/PersonaController.php,resources/js/Pages/Vehiculos/Index.vue,resources/js/Pages/Conductores/Index.vue,resources/js/Pages/Personas/Index.vue,app/Libraries/Reportes.php,app/Libraries/ReportesExcel.php'
---

# Exportación de listados (vehículos, conductores, personas)

## Exportar listados (PDF/Excel) con los filtros del index
Tres listados exportables con el mismo patrón: vehículos, conductores y personas. Rutas `{modulo}.reporte.pdf` / `{modulo}.reporte.excel` (GET {modulo}/reporte/{pdf,excel}, declaradas ANTES de su Route::resource). En cada controlador, consultaListado() es la única consulta filtrada: la usan index() (paginada) y listadoParaReporte() (completa, filas ya resueltas sin modelos + conteos de las tarjetas); si agregas un filtro al listado, agrégalo ahí y en FILTROS_LISTADO/filtrosAplicados() para que el export siga coincidiendo. Vue: los botones exportan con props.filters (filtros ya aplicados por el servidor), no con el formulario en debounce; PDF con window.open, Excel con window.location.href; deshabilitados si total = 0. Permisos `{modulo}.reporte.pdf/.excel` en su permisos{Modulo}() + config/acl.php (vehículos y conductores: admin + jefe-area vía catálogos; personas: sólo admin); sólo gating v-can.

PDF: los tres son wrappers finos de Reportes::generarListadoTabular() (carta horizontal, tarjetas, tabla easyTable con anchos que suman 263 mm, celdas null/'' → EN BLANCO, ver regla siguiente). Es el único que activa $pieDePaginaRepetido (Footer() en cada hoja + encabezado de tabla repetido); el resto de reportes sigue con pintarPieDePagina() al final. Excel: un método por listado en ReportesExcel con los helpers comunes; nombres/apellidos, marca/modelo, capacidad/unidad y n°/categoría de licencia van en columnas separadas para poder ordenar/filtrar. Conductores: licencia = documento LICENCIA_DE_CONDUCIR con fecha_vencimiento más reciente. Personas: el tipo se calcula con las relaciones ya cargadas (mismo criterio que Persona::getTipoActualAttribute(), sin su consulta por fila).
