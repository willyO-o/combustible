---
paths:
  - 'app/Http/Controllers/OperacionDiariaReportController.php,resources/js/Pages/Reportes/OperacionDiariaReporte.vue,resources/js/Pages/Reportes/OperacionDiariaDetalle.vue,app/Libraries/Reportes.php'
---

# Reportes de operación diaria

`OperacionDiariaReportController` sirve DOS reportes (ninguno lista actividades de jornada; para eso está el PDF de una operación en `OperacionDiariaController::generarPDF`):

1. **Uso de vehículos** (`operacion-diaria.reporte.uso.{index,pdf}`) — resumen comparativo, ver abajo.
2. **Bitácora por vehículo** (`operacion-diaria.reporte.detalle.{index,pdf}`) — ver sección al final.

Permisos: `operacion-diaria.reporte.uso`, `.uso.pdf`, `operacion-diaria.reporte.detalle`, `.detalle.pdf` — todos en `UserSeeder::permisosOperacionDiaria()` (jefe-area + admin vía el bundle; NUNCA conductor). Sin gating de backend (`can:`), sólo menú + `v-can`.

# Reporte de uso de vehículos en operación diaria

`OperacionDiariaReportController` (rutas `operacion-diaria.reporte.uso.{index,pdf}`, prefix `reportes`, nav bajo REPORTES). Distinto de `operacion-diaria.reporte.pdf` (ese es el PDF de UNA operación, en `OperacionDiariaController::generarPDF`). Página Inertia `Reportes/OperacionDiariaReporte`. Permisos `operacion-diaria.reporte.uso` y `operacion-diaria.reporte.uso.pdf` en `UserSeeder::permisosOperacionDiaria()` (los reciben jefe-area + admin vía el bundle; NUNCA el conductor, que no recibe el bundle). Sin gating de backend (`can:`), sólo menú + `v-can`, como el resto de reportes.

Qué muestra: una fila por vehículo con horas trabajadas (`SUM(horas_trabajadas)`), recorrido (`SUM(kilometraje_fin - kilometraje_inicio)` o el equivalente de horómetro **según `v.tipo_medicion`**, lecturas incompletas = 0), operaciones, días operados (`COUNT(DISTINCT DATE(fecha_inicio))`), promedio h/operación, reparto día/noche y última operación. NO desglosa las actividades de la jornada (eso es un reporte aparte). El tipo de combustible del vehículo entra al `GROUP BY` (es 1:1, no fragmenta) y se usa para el donut "Horas por tipo de combustible".

`obtenerResumen()`: TODO el agregado en la BD. Closure `$base` (join a `vehiculo` + `tipo_combustible`, filtro por `fecha_inicio` entre `Carbon::parse(...)->startOfDay()/endOfDay()`, `id_vehiculo[]`/`id_tipo_combustible`/`id_area`) reutilizado por dos consultas: filas por vehículo (`->get()->map()` sólo castea escalares) y una fila de totales generales (`total_km` y `total_horometro` por separado, no son la misma unidad). El filtro de área usa `whereExists` sobre `vehiculo_area` con `estado_asignacion = 'ACTIVO'` y `fecha_culminacion` nula o futura (igual que `CargasCombustibleReportController`). Default de fechas "Este mes" replicado en el controlador para no disparar la doble petición de `DateRangeFilter.vue` (ver .ai/rules/pages.md).

Filtro de vehículo = **selección múltiple para comparar** (`id_vehiculo[]`, mismo patrón que `CargasCombustibleRendimientoReporte`): `<Multiselect mode="tags">` con opciones `{value,label}`; el controlador normaliza con `idsVehiculo(Request)` → `array<int>` único y `->when(! empty($ids), whereIn(...))` (vacío = todos). El prop `filtros.id_vehiculo` SIEMPRE vuelve como array (`[]` si no hay filtro); el .vue lo re-mapea con `.map(Number)` para que los tags casen con `value` numérico. `etiquetaVehiculos()` arma el texto del PDF (hasta 3 códigos; más → "N vehículos seleccionados"). El gráfico de barras muestra TODOS los vehículos cuando hay selección explícita y sólo el top 15 cuando no.

Vue: filtros con `router.get` + debounce 500ms (fecha_inicio/fecha_fin viajan tal cual, sin `|| undefined`; `id_vehiculo` viaja sólo si `.length`); tarjetas KPI; gráfico de barras con métrica seleccionable (`useTemaGraficos` + `BotonDescargarGrafico`) + donut por combustible + donut por turno; agregación para los gráficos hecha en el cliente con `reduce` sobre las ~N filas (barata, mismo patrón que `CargasCombustibleReporte.vue`). `watch(() => props.vehiculos)` descarta del filtro los ids que ya no son elegibles tras cambiar combustible/área.

PDF: `Reportes::generarReporteOperacionDiariaUso($resumen, $fechaInicio, $fechaFin, $modo, $nombreArchivo, $filtrosAplicados)` — tabla dibujada a mano al estilo `generarReporteRendimiento` (sin fondo PNG, sin easyTable), patrón `modo 'S' + response(...)`. Recibe el mismo array `['vehiculos'=>Collection, 'totales'=>array]` que devuelve `obtenerResumen()`.

# Bitácora detallada por vehículo (`operacion-diaria.reporte.detalle`)

`detalle()` / `detallePDF()` + `obtenerDetalleVehiculo(Vehiculo, $desde, $hasta)`. Página `Reportes/OperacionDiariaDetalle`. Filtro: **UN solo vehículo** (`<Multiselect>` single, `value-prop="id"`) + rango de fechas. Sin vehículo elegido `datos` viaja `null` y la vista muestra estado vacío (no se consulta nada).

Reproduce el formato "DETALLE DE HORAS TRABAJADAS Y CONSUMO DE COMBUSTIBLE": **una fila por `operacion_diaria`** (con su operador — un vehículo puede tener varias asignaciones, por eso el operador sale de `od.id_conductor`, join directo a `persona` porque `conductor.id == persona.id`), agrupadas visualmente en: TRABAJO DE EQUIPO (fecha, operador, nº parte=`nro_operacion`, lectura inicial/final = km o horómetro según `tipo_medicion`, total horas) · CONSUMO Y COSTO DE COMBUSTIBLE (columnas naranja: `litros`, `precio`, costo = `litros*precio`, lectura de carga) · MANTENIMIENTO (columnas **dinámicas**) · MATERIAL TRASLADADO (columnas **dinámicas**, sólo si `tipo_medicion='kilometraje'`) · OBSERVACIONES (`od.observaciones`).

Enlace combustible↔operación **por fecha calendario** (`DATE(od.fecha_inicio) == DATE(cc.fecha_carga)`), NO por FK: `carga_combustible` no referencia a `operacion_diaria`. `cargasPorDia` = subconsulta `carga_combustible` agrupada por `DATE(fecha_carga)` (SUM litros, SUM litros*precio, precio ponderado, MAX km/horómetro), keyBy'd por día. Si un día tiene 2 turnos, la carga se muestra sólo en la 1ª fila (`$diasConCargaMostrada`) para que la suma visible cuadre con el total.

Columnas dinámicas de **mantenimiento**: `mantenimiento_operacion_diaria` join `tipo_mantenimiento`, sólo los tipos con ≥1 registro en las operaciones del rango, con su total (`SUM(valor)` para `cantidad`; `SUM(realizado='SI')` para `booleano`). Columnas de **material**: `actividad_realizada` (con `id_material` no nulo) join `material`, `SUM(cantidad)` por material. Los valores por fila se traen en 1 consulta cada uno y se reindexan por `id_operacion_diaria` en PHP (lookups, sin sumatorias iterando).

TODO el agregado (costo, totales, totales por columna) en SQL. **Totales de combustible** vía `joinSub` a los días-con-operación distintos → nunca duplica aunque haya turno día+noche. `litros_por_hora` = total litros / total horas. Nº de consultas constante (≈8), no crece con las filas.

PDF `Reportes::generarReporteOperacionDiariaDetalle($vehiculo, $datos, $fechaInicio, $fechaFin, $modo, $nombre)` — **Letter apaisado** (`AddPage('L','Letter')`), `easyTable` con anchos proporcionales (se reparten en el ancho real = suma exacta de columnas), encabezado de 2 filas con `colspan` para los grupos, ambas `printRow(true)`; recuadro RESUMEN dibujado a mano debajo. `$datos` = lo que devuelve `obtenerDetalleVehiculo()` (`['tipo_medicion','columnas'=>['mantenimiento','material'],'filas','totales']`). CUIDADO: no envolver los textos en `utf8Decode()` dos veces (el loop de encabezados ya lo hace) — el doble decode revienta con `iconv(): illegal character`.
