<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import Multiselect from '@vueform/multiselect'
import '@vueform/multiselect/themes/default.css'
import Maindashboard from '@/Layouts/Maindashboard.vue'


import InputError from '@/Components/InputError.vue';


defineOptions({ layout: Maindashboard })

const props = defineProps({
    carga: Object,
    tiposCombustible: Array,
    grifos: Array,
    vehiculoActual: Object,
    conductorActual: Object,
    valeActual: Object,
    conductores: Array,
    conductor: Object,
})

/* ------------------------------------------------------------------ */
/*  Form                                                               */
/* ------------------------------------------------------------------ */
const form = useForm({
    _method: 'PUT',
    fecha_carga: props.carga.fecha_carga
        ? String(props.carga.fecha_carga).substring(0, 10) : '',
    litros: props.carga.litros,
    precio: props.carga.precio,
    kilometraje: props.carga.kilometraje ?? '',
    horometro: props.carga.horometro ?? '',
    nro_factura: props.carga.nro_factura ?? '',
    tipo_carga: props.carga.tipo_carga,
    estado_carga: props.carga.estado_carga ?? 'REGISTRADO',
    id_vehiculo: props.vehiculoActual.id ?? null,
    id_grifo: props.grifos.find(g => g.id === props.carga.id_grifo) ?? null,
    id_tipo_combustible: props.carga.id_tipo_combustible,
    id_conductor: props.carga.id_conductor ?? null,
    id_vale: props.valeActual ?? null,
    respaldo_count: 0,
    respaldos_eliminar: [],
})


const tipoMedicion = ref(props.vehiculoActual?.meta?.tipo_medicion ?? null)
/* ------------------------------------------------------------------ */
/*  Auto-relleno al cambiar vehículo                                   */
/* ------------------------------------------------------------------ */
const conductorAutoFill = ref(props.conductorActual ?? null)
const loadingVehiculo = ref(false)
let prevVehiculoId = props.vehiculoActual?.id ?? null

const conductoresOptions = ref(props.conductores ?? [])

console.log("conduc", form);


watch(() => form.id_vehiculo, async (val) => {
    const newId = val?.id ?? null
    if (newId === prevVehiculoId) return
    prevVehiculoId = newId
    if (!val) {
        form.id_tipo_combustible = ''
        conductorAutoFill.value = null
        form.id_conductor = null
        return
    }
    loadingVehiculo.value = true
    try {
        const res = await fetch(route('cargas.vehiculo-info', val.id))
        const data = await res.json()
        if (data.tipo_combustible) form.id_tipo_combustible = data.tipo_combustible.id
        if (data.conductor) {
            conductorAutoFill.value = data.conductor
            form.id_conductor = data.conductor
        } else {
            conductorAutoFill.value = null
        }
    } catch {
        // silent
    } finally {
        loadingVehiculo.value = false
    }
})

watch(() => form.id_vale, (val) => { if (val) form.tipo_carga = 'VALE' })

/* ------------------------------------------------------------------ */
/*  Búsquedas asíncronas                                               */
/* ------------------------------------------------------------------ */

const optionsVehiculos = ref([])

async function buscarVehiculos(q) {

    if (!q || q.length < 2) {
        return optionsVehiculos.value
    }
    const res = await fetch(`${route('search.vehiculos')}?q=${encodeURIComponent(q)}`)
    return res.ok ? await res.json() : []
}



async function buscarVales(q) {
    if (!q || q.length < 1) return []
    const params = new URLSearchParams({ q })
    if (form.id_vehiculo?.id) params.set('id_vehiculo', form.id_vehiculo.id)
    // Include current vale in results (for edit)
    if (props.carga.id_vale) params.set('current_vale_id', props.carga.id_vale)
    const res = await fetch(`${route('search.vales-carga')}?${params}`)
    return res.ok ? await res.json() : []
}

/* ------------------------------------------------------------------ */
/*  Respaldos existentes y nuevos                                      */
/* ------------------------------------------------------------------ */
const tiposRespaldo = ['FACTURA', 'NOTA', 'COMPROBANTE', 'OTRO']
const nuevosRespaldos = ref([])

function agregarRespaldo() {
    if (nuevosRespaldos.value.length >= 5) return
    nuevosRespaldos.value.push({ archivo: null, tipo_respaldo: 'FACTURA', preview: null, previewType: null })
}

function quitarNuevoRespaldo(idx) {
    nuevosRespaldos.value.splice(idx, 1)
}

function onArchivoChange(e, idx) {
    const file = e.target.files[0]
    if (!file) return
    nuevosRespaldos.value[idx].archivo = file
    const isPdf = file.type === 'application/pdf'
    nuevosRespaldos.value[idx].previewType = isPdf ? 'PDF' : 'IMAGEN'
    if (!isPdf) {
        const reader = new FileReader()
        reader.onload = (ev) => (nuevosRespaldos.value[idx].preview = ev.target.result)
        reader.readAsDataURL(file)
    } else {
        nuevosRespaldos.value[idx].preview = null
    }
}

function marcarEliminar(respaldoId) {
    if (confirm('¿Eliminar este respaldo?')) {
        form.respaldos_eliminar.push(respaldoId)
    }
}

const respaldosVisibles = computed(() =>
    props.carga.respaldos_digitales?.filter(r => !form.respaldos_eliminar.includes(r.id)) ?? []
)

const respaldoUrl = (ruta) => `/storage/${ruta}`

/* ------------------------------------------------------------------ */
/*  Monto total                                                        */
/* ------------------------------------------------------------------ */
const totalMonto = computed(() => {
    const l = Number(form.litros), p = Number(form.precio)
    return l > 0 && p > 0 ? (l * p).toFixed(2) : null
})


/* ------------------------------------------------------------------ */
/*  Envío                                                              */
/* ------------------------------------------------------------------ */
function submit() {

    console.log(form);

    form
        .transform((data) => {
            const out = {
                ...data,
                // id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
                id_grifo: data.id_grifo?.id ?? data.id_grifo,
                id_conductor: data.id_conductor?.id ?? data.id_conductor,
                id_vale: data.id_vale?.id ?? data.id_vale ?? null,
                respaldo_count: nuevosRespaldos.value.filter(r => r.archivo).length,
            }
            nuevosRespaldos.value.forEach((r, i) => {
                if (r.archivo) {
                    out[`respaldo_archivo_${i}`] = r.archivo
                    out[`respaldo_tipo_${i}`] = r.tipo_respaldo
                }
            })
            return out
        })
        .post(route('cargas.update', props.carga.id), { forceFormData: true })
}

const multi = ref(null)

onMounted(() => {

    optionsVehiculos.value = props.vehiculoActual ? [props.vehiculoActual] : []



})
</script>

<template>

    <Head title="Editar Carga de Combustible" />
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
                        <li class="breadcrumb-item active">Editar</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    Editar Carga
                    <span class="text-primary">— {{ carga.vehiculo?.nro_placa }}</span>
                    <span class="text-muted fs-14 ms-2">{{ carga.fecha_carga }}</span>
                </h1>
            </div>
            <Link :href="route('cargas.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <form @submit.prevent="submit">
            <div class="row g-4">

                <!-- ====== VEHÍCULO ====== -->
                <div class="col-xl-6">
                    <div class="card custom-card h-100">
                        <div class="card-header">
                            <div class="card-title"><i class="ri-car-line me-2"></i>Vehículo</div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div v-if="props.conductor" class="col-12">
                                    <h5>
                                        Conductor asignado:
                                        {{ props.conductor.nombre_completo ?? '—' }}
                                    </h5>

                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium">Vehículo <span
                                            class="text-danger">*</span></label>
                                    <Multiselect ref="multi" v-model="form.id_vehiculo"
                                        :options="[{ id: props.vehiculoActual.id, label: props.vehiculoActual.label }]"
                                        value-prop="id" label="label" :searchable="true" :min-chars="2" :delay="300"
                                        :resolve-on-load="false"
                                        placeholder="Buscar por placa o marca..." no-options-text="Escriba para buscar"
                                        no-results-text="Sin resultados" :disabled="true"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_vehiculo }" />
                                    <InputError :message="form.errors.id_vehiculo" />
                                    <div v-if="loadingVehiculo" class="text-muted small mt-1">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Cargando...
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Tipo de Combustible <span class="text-danger">*</span>
                                        <span v-if="form.id_vehiculo"
                                            class="badge bg-success-transparent text-success ms-2 fs-10">Auto-llenado</span>
                                    </label>
                                    <select v-model="form.id_tipo_combustible" class="form-select" :disabled="true"
                                        :class="{ 'is-invalid': form.errors.id_tipo_combustible }">
                                        <option value="">— Seleccionar —</option>
                                        <option v-for="tc in tiposCombustible" :key="tc.id" :value="tc.id">{{
                                            tc.tipo_combustible }}</option>
                                    </select>
                                    <div v-if="form.errors.id_tipo_combustible" class="invalid-feedback">{{
                                        form.errors.id_tipo_combustible }}</div>
                                </div>
                                <div class="col-12" v-if="!props.conductor">
                                    <label class="form-label fw-medium">
                                        Conductor <span class="text-danger">*</span>
                                        <span v-if="conductorAutoFill"
                                            class="badge bg-success-transparent text-success ms-2 fs-10">Asignado
                                            activo</span>
                                    </label>
                                    <Multiselect v-model="form.id_conductor" :options="conductoresOptions"
                                        value-prop="id" label="label" :searchable="true" :min-chars="2" :delay="300"
                                        :resolve-on-load="false" placeholder="Buscar conductor..."
                                        no-options-text="Escriba para buscar" no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_conductor }" />
                                    <div v-if="form.errors.id_conductor" class="text-danger small mt-1">{{
                                        form.errors.id_conductor }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====== DATOS DE CARGA ====== -->
                <div class="col-xl-6">
                    <div class="card custom-card h-100">
                        <div class="card-header">
                            <div class="card-title"><i class="ri-drop-line me-2"></i>Datos de la Carga</div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Fecha <span class="text-danger">*</span></label>
                                    <input v-model="form.fecha_carga" type="date" class="form-control"
                                        :class="{ 'is-invalid': form.errors.fecha_carga }" />
                                    <div v-if="form.errors.fecha_carga" class="invalid-feedback">{{
                                        form.errors.fecha_carga }}</div>
                                </div>
                                <div v-if="!props.conductor" class="col-sm-6">
                                    <label class="form-label fw-medium">Estado</label>
                                    <select v-model="form.estado_carga" class="form-select">
                                        <option value="REGISTRADO">REGISTRADO</option>
                                        <option value="VERIFICADO">VERIFICADO</option>
                                        <option value="ANULADO">ANULADO</option>
                                    </select>
                                    <InputError :message="form.errors.estado_carga" />
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Litros <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input v-model="form.litros" type="number" step="0.01" min="0.01"
                                            class="form-control" :class="{ 'is-invalid': form.errors.litros }" />
                                        <span class="input-group-text">Lt</span>
                                        <div v-if="form.errors.litros" class="invalid-feedback">{{ form.errors.litros }}
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Precio (Bs) <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">Bs</span>
                                        <input v-model="form.precio" type="number" step="0.01" min="0.01"
                                            class="form-control" :class="{ 'is-invalid': form.errors.precio }" />
                                        <div v-if="form.errors.precio" class="invalid-feedback">{{ form.errors.precio }}
                                        </div>
                                    </div>
                                </div>

                                <div v-if="tipoMedicion === 'kilometraje'" class="col-sm-6">
                                    <label class="form-label fw-medium">Kilometraje</label>
                                    <div class="input-group">
                                        <input v-model="form.kilometraje" type="text" class="form-control" v-decimal="1"
                                            :class="{ 'is-invalid': form.errors.kilometraje }" placeholder="0"
                                            step="0.1" />
                                        <span class="input-group-text">km</span>
                                        <div v-if="form.errors.kilometraje" class="invalid-feedback">{{
                                            form.errors.kilometraje }}</div>
                                    </div>

                                </div>

                                <div v-if="tipoMedicion === 'horometro'" class="col-sm-6">
                                    <label class="form-label fw-medium">Horometro</label>
                                    <div class="input-group">
                                        <input v-model="form.horometro" type="text" class="form-control" v-decimal="1"
                                            :class="{ 'is-invalid': form.errors.horometro }" placeholder="0"
                                            step="0.1" />
                                        <span class="input-group-text">h</span>
                                        <div v-if="form.errors.horometro" class="invalid-feedback">{{
                                            form.errors.horometro
                                            }}</div>
                                    </div>

                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Nro. Factura</label>
                                    <input v-model="form.nro_factura" type="text" class="form-control" maxlength="50" />
                                </div>
                                <div v-if="totalMonto" class="col-12">
                                    <div class="alert alert-success py-2 mb-0">
                                        <i class="ri-money-dollar-box-line me-1"></i>
                                        Total: <strong>Bs {{ totalMonto }}</strong>
                                        <small class="text-muted ms-2">({{ form.litros }} Lt × Bs {{ form.precio
                                            }})</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====== GRIFO & VALE ====== -->
                <div class="col-xl-6">
                    <div class="card custom-card h-100">
                        <div class="card-header">
                            <div class="card-title"><i class="ri-gas-station-line me-2"></i>Grifo & Vale</div>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-medium">Grifo <span class="text-danger">*</span></label>
                                    <Multiselect v-model="form.id_grifo" :options="grifos" value-prop="id" label="label"
                                        :searchable="true" :filter-results="true" placeholder="Buscar grifo..."
                                        no-options-text="Sin grifos" no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_grifo }" />
                                    <div v-if="form.errors.id_grifo" class="text-danger small mt-1">{{
                                        form.errors.id_grifo }}</div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium">Tipo de Carga <span
                                            class="text-danger">*</span></label>
                                    <select v-model="form.tipo_carga" class="form-select" :disabled="!!form.id_vale">
                                        <option value="VALE">VALE</option>
                                        <option value="PREPAGO">PREPAGO</option>
                                    </select>
                                    <small v-if="form.id_vale" class="text-info">
                                        <i class="ri-information-line me-1"></i>Bloqueado: hay un vale asignado
                                    </small>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium">Vale <span
                                            class="text-muted small">(opcional)</span></label>
                                    <Multiselect v-model="form.id_vale" :options="buscarVales" value-prop="id"
                                        label="label" :searchable="true" :min-chars="1" :delay="300"
                                        :resolve-on-load="false" placeholder="Buscar vale por número..."
                                        no-options-text="Escriba el número" no-results-text="Sin vales PENDIENTE"
                                        :can-clear="true" :class="{ 'is-invalid-multiselect': form.errors.id_vale }" />
                                    <div v-if="form.errors.id_vale" class="text-danger small mt-1">{{
                                        form.errors.id_vale }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ====== RESPALDOS ====== -->
                <div class="col-xl-6">
                    <div class="card custom-card h-100">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div class="card-title"><i class="ri-attachment-2 me-2"></i>Respaldos Digitales</div>
                            <button type="button" class="btn btn-sm btn-primary-light btn-wave" @click="agregarRespaldo"
                                :disabled="nuevosRespaldos.length >= 5">
                                <i class="ri-add-line me-1"></i> Agregar
                            </button>
                        </div>
                        <div class="card-body">

                            <!-- Existentes -->
                            <div v-if="respaldosVisibles.length > 0" class="mb-3">
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

                            <!-- Nuevos a subir -->
                            <div v-if="nuevosRespaldos.length === 0 && respaldosVisibles.length === 0"
                                class="text-center text-muted py-3">
                                <i class="ri-file-upload-line fs-3 d-block mb-2"></i>
                                <small>Haz clic en "Agregar" para adjuntar documentos</small>
                            </div>

                            <div v-for="(r, idx) in nuevosRespaldos" :key="'new-' + idx"
                                class="border border-dashed rounded-3 p-3 mb-2">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-medium small text-primary">Nuevo {{ idx + 1 }}</span>
                                    <button type="button" class="btn btn-sm btn-icon btn-danger-light"
                                        @click="quitarNuevoRespaldo(idx)">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                                <div class="row g-2">
                                    <div class="col-sm-4">
                                        <select v-model="r.tipo_respaldo" class="form-select form-select-sm">
                                            <option v-for="t in tiposRespaldo" :key="t" :value="t">{{ t }}</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-8">
                                        <input type="file" class="form-control form-control-sm"
                                            accept="image/jpeg,image/png,image/webp,application/pdf"
                                            @change="onArchivoChange($event, idx)" />
                                    </div>
                                    <div v-if="r.preview" class="col-12">
                                        <img :src="r.preview" class="rounded"
                                            style="max-height:80px;object-fit:contain;" />
                                    </div>
                                    <div v-else-if="r.previewType === 'PDF'" class="col-12">
                                        <span class="badge bg-danger-transparent text-danger fs-12">
                                            <i class="ri-file-pdf-line me-1"></i>{{ r.archivo?.name }}
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
                    {{ form.processing ? 'Actualizando...' : 'Actualizar Carga' }}
                </button>
            </div>
        </form>
</template>

<style>
.is-invalid-multiselect .multiselect-wrapper {
    border-color: #dc3545 !important;
}

.multiselect {
    --ms-font-size: 0.875rem;
    --ms-border-color: #dee2e6;
    --ms-border-color-active: #86b7fe;
    --ms-ring-color: rgba(13, 110, 253, .25);
    --ms-ring-width: 0.25rem;
    --ms-radius: 0.375rem;
    --ms-py: 0.375rem;
    --ms-px: 0.75rem;
    --ms-option-bg-selected: #0d6efd;
    --ms-option-bg-selected-pointed: #0a58ca;
    --ms-option-color-selected: #fff;
    --ms-option-color-selected-pointed: #fff;
}
</style>
