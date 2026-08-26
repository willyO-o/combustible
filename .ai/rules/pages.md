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
