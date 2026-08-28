---
paths:
  - 'resources/js/Pages/**/*.vue'
---

# Pages

## Use Inertia's useForm for Create/Edit pages
Build create/edit page forms with Inertia's useForm (form.errors, form.processing, form.post/put) rather than plain ref() + manual router.post calls.

## Searchable Multiselect for vehiculo/repuesto/persona/conductor selects
Any select field that picks a vehiculo, repuesto, persona, or conductor record must use @vueform/multiselect's `<Multiselect>` with LOCAL filtering (`:searchable="true"`, `:filter-results="true"`, static `:options` from the already-loaded prop array), not a plain `<select>` and not SearchSelect.vue (that wraps Multiselect for remote/async search via axios — don't use it when the full list is already loaded as a prop).

Reference implementation: resources/js/Pages/SolicitudMantenimiento/Create.vue (Vehículo field). Pattern:
```
<Multiselect v-model="form.id_x" :options="xOpt" value-prop="id" label="label"
    :searchable="true" :filter-results="true" placeholder="Buscar..."
    no-options-text="Sin resultados" no-results-text="Sin resultados"
    :class="{ 'is-invalid-multiselect': form.errors.id_x }" />
<div v-if="form.errors.id_x" class="text-danger small mt-1">{{ form.errors.id_x }}</div>
```
Multiselect needs a `label` field on each option; if the prop array doesn't already have one (e.g. Vehiculo only has codigo/nro_placa/marca), synthesize it client-side with a `computed()` (e.g. `` `${v.codigo} – ${v.nro_placa} – ${v.marca}` ``) instead of changing the controller. Multiselect doesn't support Bootstrap's is-invalid/invalid-feedback, so errors use `.is-invalid-multiselect` + a manual `text-danger small mt-1` div, not InputError.

Small fixed enums (e.g. tipo_mantenimiento: PREVENTIVO/CORRECTIVO) stay as plain `<select>` — this rule is only for selects backed by a growable list of vehiculo/repuesto/persona/conductor records.

## Filtros de rango de fechas usan DateRangeFilter.vue, no dos &lt;input type=date&gt;
Para cualquier filtro "fecha desde/hasta" en una página (listados con ListValeAction-style filtros), usa resources/js/Components/DateRangeFilter.vue en vez de dos <input type="date"> sueltos. Es un wrapper de daterange-picker-vue3 (instalado vía npm) con presets en español (Hoy, Ayer, Últimos 3/7 días, Esta semana desde el lunes, Este mes, Últimos 6 meses, Este año, Año pasado, Rango personalizado) y dos v-model en formato 'YYYY-MM-DD' (sólo fecha, sin horas — coincide con lo que esperan los whereDate(...) del backend):

<DateRangeFilter v-model:fecha-desde="filters.fecha_desde" v-model:fecha-hasta="filters.fecha_hasta" label="..." />

Reference implementation: resources/js/Pages/Vales/Index.vue. El CSS de la librería (daterange-picker-vue3/dist/daterange-picker-vue3.css) se importa una sola vez, globalmente, en resources/js/app.js junto a los demás CSS de librerías (multiselect, toastify, etc.) — no lo reimportes por componente. Overrides de estilo van en resources/css/my-styles.css (ver [[Estilos de componentes nuevos van en resources/css/my-styles.css, no en <style> del .vue]]), no en un <style> del propio DateRangeFilter.vue. No pasar `append-to-body` — el teleport a document.body rompe el cálculo de posición del picker con el layout del template (sidebar fijo), queda flotando lejos del input; se deja el posicionamiento CSS normal (absolute) por defecto de la librería.

Prop `default-range="Este mes"` (u otra clave exacta de las de arriba) preselecciona ese rango cuando el padre no trae fechaDesde/fechaHasta todavía (primera carga sin filtros en la URL): al montar, el componente emite ese rango como v-model (encadena con el filtro/petición del padre) y lo marca "activo" en la lista de presets usando los mismos objetos Date del preset — no reconstruidos desde el string 'YYYY-MM-DD', porque `new Date('YYYY-MM-DD')` parsea como UTC y no calza en milisegundos con los rangos (construidos en hora local) aunque sea el mismo día calendario, y el picker resalta "activo" por igualdad exacta de instante.

Convención del proyecto: todo DateRangeFilter de un listado/reporte lleva `default-range="Este mes"` (así quedó en Vales/Index.vue, CargasCombustible/Index.vue, Operacion/Index.vue, y las 3 páginas de Reportes/CargasCombustible*.vue) — agrégalo también en cualquier filtro de rango de fechas nuevo, salvo que el usuario pida explícitamente otro comportamiento.

## El default-range debe aplicarse también en el backend (índice), no sólo en DateRangeFilter.vue
Si el controlador no manda ya `fecha_desde`/`fecha_hasta` con un valor por defecto (como sí hacían las 3 páginas de Reportes/CargasCombustible*, ver CargasCombustibleReportController::index()), la primera carga de la página dispara DOS peticiones: la inicial (props sin filtro) y una extra inmediatamente después, porque el onMounted() de DateRangeFilter.vue ve fechaDesde/fechaHasta vacíos, autoselecciona `default-range` y emite el v-model — lo que dispara el watch(filters, ...) del padre y una petición extra apenas se monta. Esto pasó en Vales/Index.vue, CargasCombustible/Index.vue y Operacion/Index.vue (ValeController, CargaCombustibleController, OperacionDiariaController): no tenían ningún default de fecha en el controlador.

La solución NO es tocar DateRangeFilter.vue (su lógica de default-range en el cliente es correcta y sirve como respaldo) sino replicar el mismo default en el controlador, para que la primera respuesta ya venga filtrada por "Este mes" y las props que recibe DateRangeFilter no estén vacías (con lo que su onMounted no tiene nada que autoseleccionar/emitir):
```php
$filters = $request->only([...campos sin fecha...]);
$filters['fecha_desde'] = $request->input('fecha_desde', now()->startOfMonth()->format('Y-m-d'));
$filters['fecha_hasta'] = $request->input('fecha_hasta', now()->format('Y-m-d'));
```
Importante: en el Vue de la página, el watcher que dispara `router.get(...)` NO debe omitir fecha_desde/fecha_hasta con `|| undefined` cuando están vacíos (a diferencia de los demás filtros) — deben viajar como `val.fecha_desde`/`val.fecha_hasta` tal cual, incluso `''`. Si se omiten, "Limpiar filtros" ya no podría quitar el rango de fechas: el request llegaría sin esas claves y el controlador reaplicaría el default. Laravel además normaliza ese '' a `null` vía ConvertEmptyStringsToNull, pero la clave sigue presente en el request, así que `$request->input('fecha_desde', $default)` devuelve `null` (no el default) — sigue distinguiendo "el usuario limpió el filtro" de "todavía no se mandó nada".

## Scroll infinito en un listado paginado: <InfiniteScroll> de Inertia v2, no useInfiniteScroll de VueUse
Para "cargar más" un listado paginado con LengthAwarePaginator sin romper la paginación numérica existente (usada en otra vista del mismo listado, ej. la tabla desktop), usa el componente nativo `<InfiniteScroll>` de `@inertiajs/vue3` (v2) en vez de reimplementarlo con `useInfiniteScroll`/`useIntersectionObserver` de VueUse — Inertia ya trae el merge de props, el intersection observer y el tracking de página resuelto y probado; VueUse sólo el intersection observer, dejando el resto (merge de arrays, sincronía con el paginador de Laravel, header de versión) por reinventar.

Referencia: resources/js/Pages/Vales/Index.vue (sólo en la vista de tarjetas mobile; la tabla desktop conserva su paginador numerado de siempre, sin tocar).

Backend — envolver el paginador con `Inertia::scroll()` en vez de pasarlo tal cual:
```php
'vales' => Inertia::scroll($vales), // $vales = LengthAwarePaginator (::paginate())
```
Esto NO cambia la forma de la prop (sigue trayendo data/total/from/to/last_page/links igual que antes — sólo agrega metadata de merge a nivel de protocolo), así que cualquier código que ya lea `vales.total`, `vales.links`, etc. (el paginador numerado de la tabla desktop) sigue funcionando sin cambios.

Frontend — envolver sólo el listado que debe scrollear infinito (no toda la página):
```vue
<InfiniteScroll data="vales" only-next as="div" class="...">
    <div v-for="vale in vales.data" :key="vale.id">...</div>
</InfiniteScroll>
```
`only-next` porque el listado siempre arranca en la página 1 (no hay caso de "cargar anteriores"). El merge (agregar en vez de reemplazar) sólo aplica a recargas parciales (`only`/`X-Inertia-Partial-Data`, que es justamente lo que dispara `<InfiniteScroll>` al hacer scroll); una visita completa normal (cambiar un filtro con `router.get(...)` sin `only`) siempre reemplaza la prop entera, así que los filtros existentes (que no usan `only`) siguen reseteando el listado sin necesidad de `reset: [...]` extra.

## Valores de gráficos: redondear siempre a máximo 2 decimales
Todo dato que se pasa a un gráfico (Apexchart) debe redondearse a máximo 2 decimales, tanto en el backend que arma la serie como en el mapeo del .vue antes de asignarlo a `series`.

Backend: `round((float) $valor, 2)` al construir cada punto (ej. `CargaCombustible::reporteCargaCombustibleMes()` redondea `total_litros` y `total_precio`).
Frontend: helper `a2Decimales(v) = Math.round((Number(v)||0)*100)/100` aplicado en `.map(...)` de la serie (ej. `Dashboard.vue` cambiarDatos()).

Aplica a cualquier gráfico nuevo del proyecto, no solo el dashboard.

## ApexCharts: resolver las variables del tema, no pasar var(--x) como color
ApexCharts (componente global `Apexchart`, vue3-apexcharts) NO entiende `colors: ['var(--primary-color)']` — lo ignora y cae a su paleta por defecto (se vio en Dashboard.vue: donut "deforme" con 2 colores).

Usar los helpers de `resources/js/Utils/chartUtil.js`:
- `colorTema('--primary-rgb', fallback)` — resuelve una variable CSS a un color usable (las `*-rgb` del tema vienen "r, g, b" -> `rgb(...)`).
- `paletaGraficos()` — paleta categórica del tema (primary/success/warning/info/danger/secondary).

Para que siga al tema al alternar claro/oscuro, usar el composable `resources/js/Composables/useTemaGraficos.js`:
```js
const { paleta } = useTemaGraficos()   // ref<string[]>, se re-resuelve con un MutationObserver sobre <html>
const opciones = computed(() => ({ colors: paleta.value, ... }))
```
(Dashboard.vue tiene su propia paleta ordenada por estado + observer; el resto de gráficos usan el composable.)

Donut circular: envolver `<Apexchart>` en un div con `max-width` y centrar.

## Gráficos ApexCharts: botón de descarga PNG con <BotonDescargarGrafico>
Todo `<Apexchart>` (dashboard y reportes) debe permitir descargar la imagen. NO se usa la toolbar nativa de ApexCharts: su export hereda los colores del modo oscuro y sale ilegible (texto blanco sobre el fondo blanco del PNG), y además `vue3-apexcharts` hace `JSON.parse(JSON.stringify(options))` en cada cambio de la prop `options`, lo que borra cualquier función dentro de `toolbar.tools.customIcons`.

Patrón:
```vue
<Apexchart ref="miGrafico" ... :options="..." />   <!-- options.chart.toolbar: { show: false } -->
<BotonDescargarGrafico :grafico="miGrafico" nombre="horas-trabajadas"
    titulo="Horas Trabajadas por Día" subtitulo="Dashboard" />   <!-- en el card-header -->
```

`BotonDescargarGrafico` (resources/js/Components/BotonDescargarGrafico.vue) llama a `descargarGraficoPng(ref, nombre, titulo, subtitulo)` de `resources/js/Utils/chartUtil.js`, que: (1) aplica temporalmente `LOOK_EXPORT` (fondo `#fff`, `foreColor`/leyenda `#373d3f`, tooltip light) + `title`/`subtitle` incrustados vía `chart.updateOptions(..., true, false, false)`, (2) `await chart.dataURI()` -> descarga el PNG, (3) revierte al look anterior (snapshot de las claves tocadas, título/subtítulo a texto vacío) en `finally`. Funciona igual en modo claro y oscuro. Siempre pasar `titulo` + `subtitulo` (módulo) para que la imagen se entienda sola.

Para `<Apexchart>` dentro de un `v-for`: guardar las refs en un objeto (`const graficosRef = ref({})`, `:ref="(el) => (graficosRef[clave] = el)"`).

Aplicado en: Dashboard.vue (3), Reportes/CargasCombustibleRendimientoReporte.vue (1 por tipo de medición), Reportes/CargasCombustibleRendimientoDetalle.vue (1), Reportes/CargasCombustibleReporte.vue (2: barras costo/litros/cargas/precio por vehículo con toggle + donut gasto por tipo de combustible, ambos derivados en el cliente de `datosResumen`, sin tocar el controlador).
