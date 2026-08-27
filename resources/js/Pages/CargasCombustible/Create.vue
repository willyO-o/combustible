<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import SearchSelect from '@/Components/SearchSelect.vue'

import Multiselect from '@vueform/multiselect'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

import axios from 'axios'


const props = defineProps({
    tiposCombustible: Array,
    grifos: Array,
    conductor: Object,   // { id, label } del conductor asignado al vehículo (si hay uno)
    valesConductor: { type: Array, default: () => [] }, // [{ id, nro_vale, fecha_emision, litros }] vales PENDIENTE del conductor (si hay uno)
    vehiculosAsignados: { type: Array, default: () => [] }, // [{ id, label }] vehículos asignados al conductor (si hay uno)

    // ── "Usar vale" desde el listado de vales (modo creación) ──────────
    valePreseleccionado: { type: Object, default: null }, // { id, nro, litros, precio, id_vehiculo, vehiculo, id_conductor, conductor, id_grifo, grifo, id_tipo_combustible, tipo_combustible, tipo_medicion }

    // ── Props exclusivos del modo edición ──────────────────────────────
    carga: { type: Object, default: null },          // registro a editar (null en modo creación)
    vehiculoActual: { type: Object, default: null },  // { id, label, meta } del vehículo de la carga
    conductorActual: { type: Object, default: null }, // { id, label } del conductor de la carga
    valeActual: { type: Object, default: null },      // { id, label } del vale de la carga
    conductores: { type: Array, default: () => [] },  // conductores asignados al vehículo de la carga
})

const isEdit = computed(() => !!props.carga)
// "Usar vale": el vale ya viene preseleccionado desde el listado de vales.
const modoVale = computed(() => !isEdit.value && !!props.valePreseleccionado)

const valesVehiculo = ref(
    isEdit.value ? (props.valeActual ? [props.valeActual] : [])
        : modoVale.value ? [{ id: props.valePreseleccionado.id, label: `Vale #${props.valePreseleccionado.nro} — ${props.valePreseleccionado.litros} Lt` }]
            : (props.valesConductor || [])
)
const vehiculosAsignadosOpt = ref(props.vehiculosAsignados || [])

const conductoresOpt = ref(
    isEdit.value && props.conductorActual ? [props.conductorActual]
        : modoVale.value && props.valePreseleccionado.conductor ? [props.valePreseleccionado.conductor]
            : []
)

/* ------------------------------------------------------------------ */
/*  Form                                                               */
/* ------------------------------------------------------------------ */
const today = new Date().toISOString().substring(0, 10)

const form = useForm({
    ...(isEdit.value ? { _method: 'PUT' } : {}),
    fecha_carga: isEdit.value
        ? props.carga.fecha_carga_formateada
        : today,
    litros: isEdit.value ? props.carga.litros : (modoVale.value ? props.valePreseleccionado.litros : ''),
    precio: isEdit.value ? props.carga.precio : (modoVale.value ? props.valePreseleccionado.precio : ''),
    kilometraje: isEdit.value ? (props.carga.kilometraje ?? '') : '',
    horometro: isEdit.value ? (props.carga.horometro ?? '') : '',
    nro_factura: isEdit.value ? (props.carga.nro_factura ?? '') : '',
    concepto: isEdit.value ? (props.carga.concepto ?? '') : '',
    tipo_carga: isEdit.value ? props.carga.tipo_carga : 'PREPAGO',
    estado_carga: isEdit.value ? (props.carga.estado_carga ?? 'REGISTRADO') : 'REGISTRADO',
    id_vehiculo: isEdit.value ? (props.vehiculoActual?.id ?? null) : (modoVale.value ? props.valePreseleccionado.id_vehiculo : null),
    id_grifo: isEdit.value ? (props.carga.id_grifo ?? null) : (modoVale.value ? props.valePreseleccionado.id_grifo : null),
    id_tipo_combustible: isEdit.value ? props.carga.id_tipo_combustible : (modoVale.value ? props.valePreseleccionado.id_tipo_combustible : ''),
    id_conductor: isEdit.value ? (props.carga.id_conductor ?? null) : (modoVale.value ? props.valePreseleccionado.id_conductor : null),
    id_vale: isEdit.value ? (props.valeActual?.id ?? null) : (modoVale.value ? props.valePreseleccionado.id : null),
    respaldo_count: 0,
    respaldos_eliminar: [],
})

if (modoVale.value) {
    form.tipo_carga = 'VALE'
}

/* ------------------------------------------------------------------ */
/*  Vale activo → vehículo, conductor, grifo, combustible, litros y   */
/*  precio quedan bloqueados (sólo información), vengan de donde      */
/*  vengan: edición, "usar vale" o selección manual del vale.         */
/* ------------------------------------------------------------------ */
const valeActivo = computed(() => !isEdit.value && !modoVale.value && !!form.id_vale)
const datosLocked = computed(() => isEdit.value || modoVale.value || valeActivo.value)

const vehiculoLabelActual = computed(() => {
    if (form.id_vehiculo && typeof form.id_vehiculo === 'object') return form.id_vehiculo.label
    const encontrado = vehiculosAsignadosOpt.value.find(v => v.id === form.id_vehiculo)
    return encontrado?.label ?? '—'
})

// Datos de sólo lectura a mostrar cuando el vale bloquea el formulario,
// normalizados sin importar de dónde vienen (edición / "usar vale" / elegido manualmente).
const valeInfo = computed(() => {
    if (isEdit.value) {
        return {
            nro: props.carga.vale?.nro ?? props.valeActual?.label ?? null,
            vehiculo: props.vehiculoActual?.label ?? '—',
            conductor: props.conductorActual?.label ?? '—',
            grifo: props.carga.grifo ? props.carga.grifo.razon_social + (props.carga.grifo.ciudad ? ` — ${props.carga.grifo.ciudad}` : '') : '—',
            tipoCombustible: props.carga.tipo_combustible?.tipo_combustible ?? '—',
            litros: props.carga.litros,
            precio: props.carga.precio,
        }
    }
    if (modoVale.value) {
        return {
            nro: props.valePreseleccionado.nro,
            vehiculo: props.valePreseleccionado.vehiculo?.label ?? '—',
            conductor: props.valePreseleccionado.conductor?.label ?? '—',
            grifo: props.valePreseleccionado.grifo?.label ?? '—',
            tipoCombustible: props.valePreseleccionado.tipo_combustible ?? '—',
            litros: props.valePreseleccionado.litros,
            precio: props.valePreseleccionado.precio,
        }
    }
    if (valeActivo.value) {
        const vale = valesVehiculo.value.find(v => v.id == form.id_vale)
        return {
            nro: vale?.meta?.nro ?? null,
            vehiculo: vehiculoLabelActual.value,
            conductor: vale?.meta?.conductor_label ?? '—',
            grifo: vale?.meta?.grifo_label ?? '—',
            tipoCombustible: vale?.meta?.tipo_combustible_label ?? '—',
            litros: vale?.meta?.litros ?? form.litros,
            precio: vale?.meta?.precio ?? form.precio,
        }
    }
    return null
})

/* ------------------------------------------------------------------ */
/*  Auto-relleno al seleccionar vehículo (solo creación, sin vale)     */
/* ------------------------------------------------------------------ */
const conductorAutoFill = ref(
    isEdit.value ? (props.conductorActual ?? null)
        : modoVale.value ? (props.valePreseleccionado.conductor ?? null)
            : null
)   // { id, label } del conductor asignado
const loadingVehiculo = ref(false)

watch(() => form.id_vehiculo, async (val) => {
    if (isEdit.value || modoVale.value) return

    if (!val) {
        form.id_tipo_combustible = ''
        conductorAutoFill.value = null
        form.id_conductor = null
        conductoresOpt.value = []
        return
    }
    loadingVehiculo.value = true
    try {
        const res = await fetch(route('cargas.vehiculo-info', val))
        const data = await res.json()
        if (data.tipo_combustible) {
            form.id_tipo_combustible = data.tipo_combustible.id
        }
        if (data.conductor) {
            conductorAutoFill.value = data.conductor
            form.id_conductor = data.conductor.id
        } else {
            conductorAutoFill.value = null
        }
    } catch (error) {
        // no critical
    } finally {
        loadingVehiculo.value = false
    }
})

/* ------------------------------------------------------------------ */
/*  Vale: seleccionar / quitar                                        */
/* ------------------------------------------------------------------ */
watch(() => form.id_vale, (val) => {
    if (!val) return
    form.tipo_carga = 'VALE'
})

// Al elegir "Prepago" explícitamente (sólo en el flujo libre, sin vale
// preseleccionado) se entiende que no se usará ningún vale: se bloquea su
// selección y se limpian los campos que hubiera prellenado uno.
watch(() => form.tipo_carga, (val) => {
    if (isEdit.value || modoVale.value) return
    if (val === 'PREPAGO') {
        form.id_vale = null
        form.litros = ''
        form.precio = ''
        form.id_grifo = null
    }
})

const valeSeleccionado = (e) => {
    const selectedValeId = e.target.value

    form.litros = ''
    form.precio = ''
    form.id_grifo = ''
    if (valesVehiculo.value.length === 0) return

    const selectedVale = valesVehiculo.value.find(vale => vale.id == selectedValeId)
    if (!selectedVale) return

    form.litros = selectedVale.meta.litros
    form.precio = selectedVale.meta.precio
    form.id_grifo = selectedVale.meta.id_grifo
    if (selectedVale.meta.id_conductor) form.id_conductor = selectedVale.meta.id_conductor
    if (selectedVale.meta.id_tipo_combustible) form.id_tipo_combustible = selectedVale.meta.id_tipo_combustible
}

function quitarVale() {
    form.id_vale = null
    form.tipo_carga = 'PREPAGO'
    form.litros = ''
    form.precio = ''
    form.id_grifo = null
}

/* ------------------------------------------------------------------ */
/*  Respaldos digitales                                                */
/* ------------------------------------------------------------------ */
const tiposRespaldo = ['FACTURA', 'NOTA', 'COMPROBANTE', 'OTRO']
const respaldos = ref([])   // [{ archivo, tipo_respaldo, preview, previewType }] — nuevos a subir

function agregarRespaldo() {
    if (respaldos.value.length >= 5) return
    respaldos.value.push({ archivo: null, tipo_respaldo: 'FACTURA', preview: null, previewType: null })
}

function quitarRespaldo(idx) {
    respaldos.value.splice(idx, 1)
}

function onArchivoChange(e, idx) {
    const file = e.target.files[0]
    if (!file) return
    respaldos.value[idx].archivo = file
    const isPdf = file.type === 'application/pdf'
    respaldos.value[idx].previewType = isPdf ? 'PDF' : 'IMAGEN'
    if (!isPdf) {
        const reader = new FileReader()
        reader.onload = (ev) => (respaldos.value[idx].preview = ev.target.result)
        reader.readAsDataURL(file)
    } else {
        respaldos.value[idx].preview = null
    }
}

// Respaldos ya guardados (solo en edición), con opción de eliminar
const respaldosVisibles = computed(() =>
    (props.carga?.respaldos_digitales ?? []).filter(r => !form.respaldos_eliminar.includes(r.id))
)

function marcarEliminar(respaldoId) {
    if (confirm('¿Eliminar este respaldo?')) {
        form.respaldos_eliminar.push(respaldoId)
    }
}

const respaldoUrl = (ruta) => `/storage/${ruta}`

/* ------------------------------------------------------------------ */
/*  Resumen de monto total                                             */
/* ------------------------------------------------------------------ */
const totalMonto = computed(() => {
    const l = Number(valeInfo.value?.litros ?? form.litros)
    const p = Number(valeInfo.value?.precio ?? form.precio)
    return !isNaN(l) && !isNaN(p) && l > 0 && p > 0 ? (l * p).toFixed(2) : null
})

/* ------------------------------------------------------------------ */
/*  Envío                                                              */
/* ------------------------------------------------------------------ */
function submit() {
    const transformed = form.transform((data) => ({
        ...data,
        id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
        id_grifo: data.id_grifo?.id ?? data.id_grifo,
        id_conductor: data.id_conductor?.id ?? data.id_conductor,
        id_vale: data.id_vale?.id ?? data.id_vale ?? null,
        respaldo_count: respaldos.value.filter(r => r.archivo).length,
        respaldos: respaldos.value,
    }))

    if (isEdit.value) {
        transformed.post(route('cargas.update', props.carga.id), { forceFormData: true })
    } else {
        transformed.post(route('cargas.store'), { forceFormData: true })
    }
}

const tipoMedicion = ref(
    isEdit.value ? (props.vehiculoActual?.meta?.tipo_medicion ?? '')
        : modoVale.value ? (props.valePreseleccionado.tipo_medicion ?? '')
            : ''
) // 'kilometraje' o 'horometro'

const cambioVehiculo = async (vehiculoId) => {
    if (isEdit.value || modoVale.value) return

    form.kilometraje = ''
    form.horometro = ''
    form.id_vale = null
    form.id_grifo = null
    form.precio = ''
    form.litros = ''
    form.id_conductor = null

    let vehiculoSelected = null

    if (!vehiculoId || (typeof vehiculoId === 'object' && vehiculoId === null) || JSON.stringify(vehiculoId) === '{}') {
        tipoMedicion.value = ''
        valesVehiculo.value = []
        return
    }

    if (typeof vehiculoId === 'object' && vehiculoId !== null) {
        vehiculoSelected = vehiculoId
        vehiculoId = vehiculoId.id
    } else {
        vehiculoSelected = vehiculosAsignadosOpt.value.find(v => v.id === vehiculoId)
    }

    if (vehiculoSelected) {
        tipoMedicion.value = vehiculoSelected.meta.tipo_medicion
    }

    const { data } = await axios.get(route('search.vales-carga'), {
        params: {
            id_vehiculo: vehiculoId,
            q: '',
        }
    })
    if (data) {
        valesVehiculo.value = data.map(v => ({
            id: v.id,
            label: v.label,
            meta: v.meta,
        }))
    }

    const conductoresRes = await axios.get(route('search.conductores'), {
        params: {
            id_vehiculo: vehiculoId,
        }
    })

    conductoresOpt.value = conductoresRes.data.map(c => ({
        id: c.id,
        label: c.label,
        meta: c.meta,
    }))
}

onMounted(() => {
    // En edición y en "usar vale" no hay auto-relleno: los campos ya vienen
    // bloqueados con los datos guardados / los datos del vale.
    if (isEdit.value || modoVale.value) return

    // Si hay un conductor asignado desde el servidor, auto-seleccionarlo
    if (props.conductor) {
        form.id_conductor = props.conductor.id
        form.id_tipo_combustible = props.conductor.tipo_combustible_id

        if (vehiculosAsignadosOpt.value.length > 0) {
            form.id_vehiculo = vehiculosAsignadosOpt.value[0].id
        }
    }
})
</script>

<template>

    <Head :title="isEdit ? 'Editar Carga de Combustible' : 'Nueva Carga de Combustible'" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item">
                            <Link :href="route('cargas.index')">Cargas</Link>
                        </li>
                        <li class="breadcrumb-item active">{{ isEdit ? 'Editar' : 'Nueva' }}</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    {{ isEdit ? 'Editar Carga de Combustible' : 'Registrar Carga de Combustible' }}
                    <template v-if="isEdit">
                        <span class="text-primary">— #{{ carga.nro }} · {{ carga.vehiculo?.nro_placa }}</span>
                        <span class="text-muted fs-14 ms-2">{{ carga.fecha_carga }}</span>
                    </template>
                    <template v-else-if="modoVale">
                        <span class="text-primary">— Vale #{{ valePreseleccionado.nro }}</span>
                    </template>
                </h1>
            </div>
            <Link :href="route('cargas.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <div v-if="isEdit" class="alert alert-info-transparent d-flex align-items-center gap-2 mb-4">
            <i class="ri-lock-line fs-16"></i>
            <small>
                Los datos originales de la carga (vehículo, conductor, vale, tipo de combustible, grifo, fecha,
                litros y precio) quedan bloqueados. Solo puedes actualizar el <strong>kilometraje/horómetro</strong>
                y los <strong>respaldos digitales</strong>.
            </small>
        </div>

        <div v-else-if="modoVale" class="alert alert-info-transparent d-flex align-items-center gap-2 mb-4">
            <i class="ri-lock-line fs-16"></i>
            <small>
                Estás usando el <strong>Vale #{{ valePreseleccionado.nro }}</strong>. El vehículo, conductor, grifo,
                combustible, litros y precio vienen del vale y quedan bloqueados. Solo debes completar el
                <strong>kilometraje/horómetro</strong>, el <strong>número de factura</strong> y los
                <strong>respaldos digitales</strong>.
            </small>
        </div>

        <form @submit.prevent="submit">
            <div class="row g-4">

                <!-- ====== VEHÍCULO Y COMBUSTIBLE ====== -->
                <div class="col-xl-6">
                    <div class="card custom-card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div class="card-title"><i class="ri-car-line me-2"></i>Vehículo y Combustible</div>
                            <span v-if="datosLocked" class="badge bg-secondary-transparent text-secondary fs-10">
                                <i class="ri-lock-line me-1"></i>Datos del vale
                            </span>
                        </div>
                        <div class="card-body">
                            <div v-if="props.conductor" class="mb-3">
                                <span class="badge bg-primary-transparent text-primary">
                                    <i class="ri-user-line me-1"></i>
                                    Conductor: {{ props.conductor.nombre_completo ?? '—' }}
                                </span>
                            </div>

                            <!-- ── Sólo información: vale activo (edición, "usar vale" o elegido manualmente) ── -->
                            <div v-if="datosLocked && valeInfo" class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label text-muted mb-0 small">Vehículo</label>
                                    <p class="fw-medium mb-0">{{ valeInfo.vehiculo }}</p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label text-muted mb-0 small">Conductor</label>
                                    <p class="fw-medium mb-0">{{ valeInfo.conductor }}</p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label text-muted mb-0 small">Tipo de Combustible</label>
                                    <p class="fw-medium mb-0">{{ valeInfo.tipoCombustible }}</p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label text-muted mb-0 small">Grifo</label>
                                    <p class="fw-medium mb-0">{{ valeInfo.grifo }}</p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label text-muted mb-0 small">Litros</label>
                                    <p class="fw-medium mb-0">{{ Number(valeInfo.litros ?? 0).toFixed(2) }} Lt</p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label text-muted mb-0 small">Precio x Litro</label>
                                    <p class="fw-medium mb-0">Bs {{ Number(valeInfo.precio ?? 0).toFixed(2) }}</p>
                                </div>
                                <div v-if="totalMonto" class="col-12">
                                    <div class="alert alert-success-transparent py-2 mb-0 d-flex align-items-center gap-2">
                                        <i class="ri-money-dollar-box-line fs-18"></i>
                                        <div>
                                            <span class="fw-medium">Total:</span>
                                            <strong class="ms-1">Bs {{ totalMonto }}</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Editable: sin vale (Prepago) ── -->
                            <div v-else class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Vehículo <span class="text-danger">*</span>
                                    </label>

                                    <Multiselect v-if="props.conductor" v-model="form.id_vehiculo"
                                        :options="vehiculosAsignadosOpt" value-prop="id" label="label"
                                        @change="cambioVehiculo" placeholder="Seleccionar vehículo" />

                                    <SearchSelect v-else @change-data="cambioVehiculo" v-model="form.id_vehiculo"
                                        :search-url="route('search.vehiculos')" value-prop="id" :object="true"
                                        label-prop="label" :searchable="true" :min-chars="2" :delay="300"
                                        :resolve-on-load="false"
                                        placeholder="Buscar por placa o marca (mín. 2 caracteres)..."
                                        no-options-text="Escriba para buscar" no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_vehiculo }" />

                                    <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">{{
                                        form.errors.id_vehiculo }}</div>
                                    <div v-if="loadingVehiculo" class="text-muted small mt-1">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Cargando datos del
                                        vehículo...
                                    </div>
                                </div>

                                <!-- Tipo Combustible (auto-llenado) -->
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">
                                        Tipo de Combustible <span class="text-danger">*</span>
                                        <span v-if="form.id_vehiculo"
                                            class="badge bg-success-transparent text-success ms-1 fs-10">Auto</span>
                                    </label>
                                    <select v-model="form.id_tipo_combustible" class="form-select"
                                        :class="{ 'is-invalid': form.errors.id_tipo_combustible }">
                                        <option value="">— Seleccionar —</option>
                                        <option v-for="tc in tiposCombustible" :key="tc.id" :value="tc.id">
                                            {{ tc.tipo_combustible }}
                                        </option>
                                    </select>
                                    <div v-if="form.errors.id_tipo_combustible" class="invalid-feedback">{{
                                        form.errors.id_tipo_combustible }}</div>
                                </div>

                                <!-- Grifo -->
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Surtidor <span class="text-danger">*</span></label>
                                    <Multiselect v-model="form.id_grifo" :options="grifos" value-prop="id" label="label"
                                        :searchable="true" :filter-results="true" placeholder="Buscar surtidor..."
                                        no-options-text="Sin resultados" no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_grifo }" />
                                    <div v-if="form.errors.id_grifo" class="text-danger small mt-1">{{
                                        form.errors.id_grifo }}</div>
                                </div>

                                <!-- Conductor (auto-llenado desde asignación activa) -->
                                <div class="col-12" v-if="!props.conductor">
                                    <label class="form-label fw-medium">
                                        Conductor <span class="text-danger">*</span>
                                        <span v-if="conductorAutoFill"
                                            class="badge bg-success-transparent text-success ms-2 fs-10">
                                            Asignado activo
                                        </span>
                                        <span v-else-if="form.id_vehiculo && !conductorAutoFill"
                                            class="badge bg-warning-transparent text-warning ms-2 fs-10">
                                            Sin asignación activa
                                        </span>
                                    </label>

                                    <Multiselect v-model="form.id_conductor" :options="conductoresOpt" value-prop="id" label="label"
                                        :searchable="true" :filter-results="true" placeholder="Buscar conductor..."
                                        no-options-text="Sin conductores activos" no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_conductor }" />
                                    <div v-if="form.errors.id_conductor" class="text-danger small mt-1">{{
                                        form.errors.id_conductor }}</div>
                                </div>

                                <!-- Litros -->
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Litros <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input v-model="form.litros" type="text" v-decimal="2"
                                            class="form-control" :class="{ 'is-invalid': form.errors.litros }"
                                            placeholder="0.00" />
                                        <span class="input-group-text">Lt</span>
                                    </div>
                                    <div v-if="form.errors.litros" class="text-danger small mt-1">{{ form.errors.litros }}</div>
                                </div>

                                <!-- Precio -->
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Precio x Litro (Bs) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">Bs</span>
                                        <input v-model="form.precio" type="text" v-decimal="2"
                                            class="form-control" :class="{ 'is-invalid': form.errors.precio }"
                                            placeholder="0.00" />
                                    </div>
                                    <div v-if="form.errors.precio" class="text-danger small mt-1">{{ form.errors.precio }}</div>
                                </div>

                                <div v-if="totalMonto" class="col-12">
                                    <div class="alert alert-success py-2 mb-0 d-flex align-items-center gap-2">
                                        <i class="ri-money-dollar-box-line fs-18"></i>
                                        <div>
                                            <span class="fw-medium">Total:</span>
                                            <strong class="ms-1">Bs {{ totalMonto }}</strong>
                                            <small class="text-muted ms-2">({{ form.litros }} Lt × Bs {{ form.precio }})</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====== VALE Y TIPO DE CARGA ====== -->
                <div class="col-xl-6">
                    <div class="card custom-card h-100">
                        <div class="card-header">
                            <div class="card-title"><i class="ri-coupon-3-line me-2"></i>Vale y Tipo de Carga</div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <!-- Tipo de carga: segmented control -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">Tipo de Carga</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" id="tcPrepago" name="tipo_carga" value="PREPAGO"
                                            v-model="form.tipo_carga" :disabled="datosLocked" autocomplete="off" />
                                        <label class="btn btn-outline-secondary" for="tcPrepago">
                                            <i class="ri-cash-line me-1"></i>Prepago
                                        </label>
                                        <input type="radio" class="btn-check" id="tcVale" name="tipo_carga" value="VALE"
                                            v-model="form.tipo_carga" :disabled="datosLocked" autocomplete="off" />
                                        <label class="btn btn-outline-secondary" for="tcVale">
                                            <i class="ri-coupon-3-line me-1"></i>Con Vale
                                        </label>
                                    </div>
                                    <div v-if="form.errors.tipo_carga" class="text-danger small mt-1">{{
                                        form.errors.tipo_carga }}</div>
                                </div>

                                <!-- Vale ya asignado (bloqueado por edición/"usar vale", o elegido manualmente) -->
                                <div v-if="form.id_vale" class="col-12">
                                    <label class="form-label fw-medium">Vale</label>
                                    <div class="d-flex align-items-center justify-content-between border rounded-2 p-2">
                                        <span class="badge bg-primary fs-12 fw-semibold">
                                            Vale #{{ valeInfo?.nro ?? '—' }}
                                        </span>
                                        <button v-if="valeActivo" type="button"
                                            class="btn btn-sm btn-outline-danger btn-wave"
                                            @click="quitarVale">
                                            <i class="ri-close-line me-1"></i>Quitar
                                        </button>
                                        <span v-else class="badge bg-secondary-transparent text-secondary fs-10">
                                            <i class="ri-lock-line me-1"></i>Bloqueado
                                        </span>
                                    </div>
                                </div>

                                <!-- Edición sin vale (Prepago): sólo indicador, sin selector -->
                                <div v-else-if="isEdit" class="col-12">
                                    <label class="form-label fw-medium">Vale</label>
                                    <p class="text-muted mb-0">
                                        <i class="ri-close-circle-line me-1"></i>Sin vale (Prepago)
                                    </p>
                                </div>

                                <!-- Prepago elegido explícitamente: selector bloqueado, no se usará vale -->
                                <div v-else-if="form.tipo_carga === 'PREPAGO'" class="col-12">
                                    <label class="form-label fw-medium">Nro de Vale</label>
                                    <select class="form-select" disabled>
                                        <option>— No aplica (Prepago) —</option>
                                    </select>
                                    <small class="text-muted">
                                        <i class="ri-information-line me-1"></i>
                                        Cambia el tipo de carga a "Con Vale" para seleccionar uno.
                                    </small>
                                </div>

                                <!-- Selector de vale (tipo de carga = VALE, sin vale elegido aún) -->
                                <div v-else class="col-12">
                                    <label class="form-label fw-medium">
                                        Nro de Vale <span class="text-muted small">(Si aplica)</span>
                                    </label>

                                    <select v-model="form.id_vale" class="form-select"
                                        :class="{ 'is-invalid': form.errors.id_vale }" @change="valeSeleccionado">
                                        <option :value="null">— Seleccione —</option>
                                        <option v-for="vale in valesVehiculo" :key="vale.id" :value="vale.id">
                                            {{ vale.label }}
                                        </option>
                                    </select>

                                    <small class="text-muted">
                                        <i class="ri-information-line me-1"></i>
                                        Solo vales PENDIENTE. Si hay vehículo seleccionado, filtra por ese vehículo.
                                    </small>
                                    <div v-if="form.errors.id_vale" class="text-danger small mt-1">{{
                                        form.errors.id_vale }}</div>
                                </div>

                                <!-- Estado (sólo administración) -->
                                <div v-if="!props.conductor" class="col-12">
                                    <label class="form-label fw-medium">Estado</label>
                                    <select v-model="form.estado_carga" class="form-select" :disabled="isEdit"
                                        :class="{ 'is-invalid': form.errors.estado_carga }">
                                        <option value="REGISTRADO">REGISTRADO</option>
                                        <option value="VERIFICADO">VERIFICADO</option>
                                        <option value="ANULADO">ANULADO</option>
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====== DATOS DE LA CARGA ====== -->
                <div class="col-xl-6">
                    <div class="card custom-card h-100">
                        <div class="card-header">
                            <div class="card-title"><i class="ri-calendar-line me-2"></i>Fecha y Lecturas</div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
             <div v-if="tipoMedicion === 'kilometraje'" class="col-sm-6">
                                    <label class="form-label fw-medium">
                                        Kilometraje
                                        <span v-if="isEdit" class="badge bg-success-transparent text-success ms-1 fs-10">Editable</span>
                                    </label>
                                    <div class="input-group">
                                        <input v-model="form.kilometraje" type="text" class="form-control"  v-decimal="1"
                                            :class="{ 'is-invalid': form.errors.kilometraje }" placeholder="0"
                                            step="0.1" />
                                        <span class="input-group-text">km</span>
                                        <div v-if="form.errors.kilometraje" class="invalid-feedback">{{
                                            form.errors.kilometraje }}</div>
                                    </div>
                                </div>

                                <div v-if="tipoMedicion === 'horometro'" class="col-sm-6">
                                    <label class="form-label fw-medium">
                                        Horómetro
                                        <span v-if="isEdit" class="badge bg-success-transparent text-success ms-1 fs-10">Editable</span>
                                    </label>
                                    <div class="input-group">
                                        <input v-model="form.horometro" type="text" class="form-control"  v-decimal="1"
                                            :class="{ 'is-invalid': form.errors.horometro }" placeholder="0"
                                            step="0.1" />
                                        <span class="input-group-text">h</span>
                                        <div v-if="form.errors.horometro" class="invalid-feedback">{{
                                            form.errors.horometro
                                        }}</div>
                                    </div>
                                </div>

                                <div v-if="!tipoMedicion" class="col-12 text-muted small">
                                    <i class="ri-information-line me-1"></i>
                                    Seleccione un vehículo para registrar kilometraje u horómetro.
                                </div>


                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Nro. Factura</label>
                                    <input v-model="form.nro_factura" type="text" class="form-control" :disabled="isEdit"
                                        placeholder="Nro de factura..." maxlength="50" />
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-medium">A utilizarse en:</label>
                                    <input v-model="form.concepto" type="text" class="form-control" :disabled="isEdit"
                                        :class="{ 'is-invalid': form.errors.concepto }"
                                        placeholder="Ej: Operaciones de planta..." maxlength="255" />
                                    <div v-if="form.errors.concepto" class="invalid-feedback">{{ form.errors.concepto }}</div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====== RESPALDOS DIGITALES ====== -->
                <div class="col-xl-6">
                    <div class="card custom-card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div class="card-title"><i class="ri-attachment-2 me-2"></i>Respaldos Digitales</div>
                            <button type="button" class="btn btn-sm btn-primary-light btn-wave" @click="agregarRespaldo"
                                :disabled="respaldos.length >= 5">
                                <i class="ri-add-line me-1"></i> Agregar
                            </button>
                        </div>
                        <div class="card-body">

                            <!-- Existentes (solo edición) -->
                            <div v-if="isEdit && respaldosVisibles.length > 0" class="mb-3">
                                <p class="text-muted small mb-2">Respaldos guardados:</p>
                                <div v-for="resp in respaldosVisibles" :key="resp.id"
                                    class="d-flex align-items-center gap-2 border rounded-2 p-2 mb-2">
                                    <span class="badge"
                                        :class="resp.tipo_archivo === 'PDF' ? 'bg-danger-transparent text-danger' : 'bg-info-transparent text-info'">
                                        <i :class="resp.tipo_archivo === 'PDF' ? 'ri-file-pdf-line' : 'ri-image-line'"
                                            class="me-1"></i>
                                        {{ resp.tipo_archivo }}
                                    </span>
                                    <span class="badge bg-secondary-transparent text-secondary">{{ resp.tipo_respaldo
                                        }}</span>
                                    <a :href="respaldoUrl(resp.ruta_respaldo)" target="_blank"
                                        class="btn btn-sm btn-outline-primary py-0">
                                        <i class="ri-eye-line me-1"></i>Ver
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger py-0 ms-auto"
                                        @click="marcarEliminar(resp.id)">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </div>

                            <div v-if="respaldos.length === 0 && (!isEdit || respaldosVisibles.length === 0)"
                                class="text-center text-muted py-3">
                                <i class="ri-file-upload-line fs-3 d-block mb-2"></i>
                                <small>Haz clic en "Agregar" para adjuntar facturas, notas u otros documentos</small>
                            </div>

                            <div v-for="(r, idx) in respaldos" :key="idx" class="border rounded-3 p-3 mb-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-medium small text-muted">Respaldo {{ idx + 1 }}</span>
                                    <button type="button" class="btn btn-sm btn-icon btn-danger-light"
                                        @click="quitarRespaldo(idx)">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                                <div class="row g-2">
                                    <div class="col-sm-4">
                                        <label class="form-label form-label-sm fw-medium">Tipo</label>
                                        <select v-model="r.tipo_respaldo" class="form-select form-select-sm">
                                            <option v-for="tipo in tiposRespaldo" :key="tipo" :value="tipo">{{ tipo }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-sm-8">
                                        <label class="form-label form-label-sm fw-medium">Archivo</label>
                                        <input type="file" class="form-control form-control-sm"
                                            accept="image/jpeg,image/png,image/webp,application/pdf"
                                            @change="onArchivoChange($event, idx)" />
                                    </div>
                                    <!-- Preview -->
                                    <div v-if="r.preview" class="col-12 mt-1">
                                        <img :src="r.preview" alt="Preview" class="rounded"
                                            style="max-height:100px;object-fit:contain;" />
                                    </div>
                                    <div v-else-if="r.previewType === 'PDF'" class="col-12 mt-1">
                                        <span class="badge bg-danger-transparent text-danger fs-12">
                                            <i class="ri-file-pdf-line me-1"></i>PDF seleccionado: {{ r.archivo?.name }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <Link :href="route('cargas.index')" class="btn btn-outline-secondary btn-wave">Cancelar</Link>
                <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="ri-save-line me-1"></i>
                    {{ form.processing ? (isEdit ? 'Actualizando...' : 'Guardando...') : (isEdit ? 'Actualizar Carga' : 'Guardar Carga') }}
                </button>
            </div>
        </form>
</template>

<style>
.is-invalid-multiselect .multiselect-wrapper {
    border-color: #dc3545 !important;
}
</style>
