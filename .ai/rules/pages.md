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
