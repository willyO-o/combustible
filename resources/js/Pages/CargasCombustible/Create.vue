<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Multiselect from '@vueform/multiselect'
import '@vueform/multiselect/themes/default.css'
import Maindashboard from '@/Layouts/Maindashboard.vue'

const props = defineProps({
    tiposCombustible: Array,
    grifos: Array,
    conductor: Object,   // { id, label } del conductor asignado al vehículo (si hay uno)
})



/* ------------------------------------------------------------------ */
/*  Form                                                               */
/* ------------------------------------------------------------------ */
const today = new Date().toISOString().substring(0, 10)

const form = useForm({
    fecha_carga: today,
    litros: '',
    precio: '',
    kilometraje: '',
    nro_factura: '',
    tipo_carga: 'VALE',
    estado_carga: 'REGISTRADO',
    id_vehiculo: null,
    id_grifo: null,
    id_tipo_combustible: '',
    id_conductor: null,
    id_vale: null,
    respaldo_count: 0,
})

/* ------------------------------------------------------------------ */
/*  Auto-relleno al seleccionar vehículo                               */
/* ------------------------------------------------------------------ */
const conductorAutoFill = ref(null)   // { id, label } del conductor asignado
const loadingVehiculo = ref(false)

watch(() => form.id_vehiculo, async (val) => {
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
        if (data.tipo_combustible) {
            form.id_tipo_combustible = data.tipo_combustible.id
        }
        if (data.conductor) {
            conductorAutoFill.value = data.conductor
            form.id_conductor = data.conductor
        } else {
            conductorAutoFill.value = null
        }
    } catch {
        // no critical
    } finally {
        loadingVehiculo.value = false
    }
})

/* ------------------------------------------------------------------ */
/*  Cuando se selecciona un vale → tipo_carga = VALE                  */
/* ------------------------------------------------------------------ */
watch(() => form.id_vale, (val) => {
    if (val) form.tipo_carga = 'VALE'
})

/* ------------------------------------------------------------------ */
/*  Búsqueda asíncrona de vehículos (mismo endpoint de Vales)         */
/* ------------------------------------------------------------------ */
async function buscarVehiculos(q) {
    if (!q || q.length < 2) return []
    const res = await fetch(`${route('search.vehiculos')}?q=${encodeURIComponent(q)}`)
    return res.ok ? await res.json() : []
}

/* ------------------------------------------------------------------ */
/*  Búsqueda asíncrona de conductores                                  */
/* ------------------------------------------------------------------ */
async function buscarConductores(q) {
    if (!q || q.length < 2) return []
    const res = await fetch(`${route('search.conductores')}?q=${encodeURIComponent(q)}`)
    return res.ok ? await res.json() : []
}

/* ------------------------------------------------------------------ */
/*  Búsqueda asíncrona de vales (filtra por vehículo si hay uno)      */
/* ------------------------------------------------------------------ */
async function buscarVales(q) {
    if (!q || q.length < 1) return []
    const params = new URLSearchParams({ q })
    if (form.id_vehiculo?.id) params.set('id_vehiculo', form.id_vehiculo.id)
    const res = await fetch(`${route('search.vales-carga')}?${params}`)
    return res.ok ? await res.json() : []
}

/* ------------------------------------------------------------------ */
/*  Respaldos digitales                                                */
/* ------------------------------------------------------------------ */
const tiposRespaldo = ['FACTURA', 'NOTA', 'COMPROBANTE', 'OTRO']
const respaldos = ref([])   // [{ archivo, tipo_respaldo, preview, previewType }]

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

/* ------------------------------------------------------------------ */
/*  Resumen de monto total                                             */
/* ------------------------------------------------------------------ */
const totalMonto = computed(() => {
    const l = Number(form.litros)
    const p = Number(form.precio)
    return !isNaN(l) && !isNaN(p) && l > 0 && p > 0 ? (l * p).toFixed(2) : null
})

/* ------------------------------------------------------------------ */
/*  Envío                                                              */
/* ------------------------------------------------------------------ */
function submit() {
    form
        .transform((data) => {
            const out = {
                ...data,
                id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
                id_grifo: data.id_grifo?.id ?? data.id_grifo,
                id_conductor: data.id_conductor?.id ?? data.id_conductor,
                id_vale: data.id_vale?.id ?? data.id_vale ?? null,
                respaldo_count: respaldos.value.filter(r => r.archivo).length,
            }
            respaldos.value.forEach((r, i) => {
                if (r.archivo) {
                    out[`respaldo_archivo_${i}`] = r.archivo
                    out[`respaldo_tipo_${i}`] = r.tipo_respaldo
                }
            })
            return out
        })
        .post(route('cargas.store'), { forceFormData: true })
}


const optionVehiculos = ref([])

onMounted(() => {
    // Si hay un conductor asignado desde el servidor, auto-seleccionarlo
    console.log('props.conductor', props.conductor);

    if (props.conductor) {
        form.id_conductor = props.conductor.id
        form.id_tipo_combustible = props.conductor.tipo_combustible_id

        if (props.conductor.conductor.asignaciones_activas.length > 0) {

            optionVehiculos.value = props.conductor.conductor.asignaciones_activas.map(asignacion => ({
                id: asignacion.id,
                label: `${asignacion.nro_placa} - ${asignacion.marca} (${asignacion.anio})`,
            }))

            form.id_vehiculo = props.conductor.conductor.asignaciones_activas[0].id

            form.id_tipo_combustible = props.conductor.conductor.asignaciones_activas[0].id_tipo_combustible

        }
    }

})
</script>

<template>

    <Head title="Nueva Carga de Combustible" />
    <Maindashboard>
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
                        <li class="breadcrumb-item active">Nueva</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Registrar Carga de Combustible</h1>
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

                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Vehículo <span class="text-danger">*</span>
                                    </label>
                                    <Multiselect v-if="props.conductor" v-model="form.id_vehiculo"
                                        :options="optionVehiculos" value-prop="id" label="label"
                                        placeholder="Conductor asignado al vehículo" />

                                    <Multiselect v-else @select="(opt) => console.log(opt)" v-model="form.id_vehiculo"
                                        :options="buscarVehiculos" value-prop="id" label="label" :searchable="true"
                                        :min-chars="2" :delay="300" :resolve-on-load="false"
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
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Tipo de Combustible <span class="text-danger">*</span>
                                        <span v-if="form.id_vehiculo"
                                            class="badge bg-success-transparent text-success ms-2 fs-10">Auto-llenado</span>
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

                                <!-- Conductor (auto-llenado desde asignación activa) -->
                                <div class="col-12">
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


                                    <Multiselect v-if="props.conductor" v-model="form.id_conductor"
                                        :options="[{ id: props.conductor.id, label: props.conductor.nombre_completo }]"
                                        value-prop="id" label="label" disabled
                                        placeholder="Conductor asignado al vehículo" />

                                    <Multiselect v-else v-model="form.id_conductor" :options="buscarConductores"
                                        value-prop="id" label="label" :searchable="true" :min-chars="2" :delay="300"
                                        :resolve-on-load="false" placeholder="Buscar por CI, nombre o apellido..."
                                        no-options-text="Escriba para buscar" no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_conductor }">

                                        <template #noResult>
                                            <div class="text-muted small">
                                                <i class="ri-information-line me-1"></i>
                                                No se encontró conductor. Asegúrese de que esté activo y asignado al
                                                vehículo.
                                            </div>
                                        </template>
                                    </Multiselect>
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
                                    <label class="form-label fw-medium">Fecha de Carga <span
                                            class="text-danger">*</span></label>
                                    <input v-model="form.fecha_carga" type="date" class="form-control"
                                        :class="{ 'is-invalid': form.errors.fecha_carga }" />
                                    <div v-if="form.errors.fecha_carga" class="invalid-feedback">{{
                                        form.errors.fecha_carga }}</div>
                                </div>

                                <div v-if="!props.conductor" class="col-sm-6">
                                    <label class="form-label fw-medium">Estado</label>
                                    <select v-model="form.estado_carga" class="form-select"
                                        :class="{ 'is-invalid': form.errors.estado_carga }">
                                        <option value="REGISTRADO">REGISTRADO</option>
                                        <option value="VERIFICADO">VERIFICADO</option>
                                        <option value="ANULADO">ANULADO</option>
                                    </select>
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Litros <span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input v-model="form.litros" type="number" step="0.01" min="0.01"
                                            class="form-control" :class="{ 'is-invalid': form.errors.litros }"
                                            placeholder="0.00" />
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
                                            class="form-control" :class="{ 'is-invalid': form.errors.precio }"
                                            placeholder="0.00" />
                                        <div v-if="form.errors.precio" class="invalid-feedback">{{ form.errors.precio }}
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Kilometraje</label>
                                    <div class="input-group">
                                        <input v-model="form.kilometraje" type="number" min="0" class="form-control"
                                            :class="{ 'is-invalid': form.errors.kilometraje }" placeholder="0" />
                                        <span class="input-group-text">km</span>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Nro. Factura</label>
                                    <input v-model="form.nro_factura" type="text" class="form-control"
                                        placeholder="Nro de factura..." maxlength="50" />
                                </div>

                                <!-- Total estimado -->
                                <div v-if="totalMonto" class="col-12">
                                    <div class="alert alert-success py-2 mb-0 d-flex align-items-center gap-2">
                                        <i class="ri-money-dollar-box-line fs-18"></i>
                                        <div>
                                            <span class="fw-medium">Total:</span>
                                            <strong class="ms-1">Bs {{ totalMonto }}</strong>
                                            <small class="text-muted ms-2">({{ form.litros }} Lt × Bs {{ form.precio
                                            }})</small>
                                        </div>
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

                                <!-- Vale (opcional, búsqueda async) -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Nro de Vale <span class="text-muted small">(Si aplica)</span>
                                    </label>
                                    <Multiselect v-model="form.id_vale" :options="buscarVales" value-prop="id"
                                        label="label" :searchable="true" :min-chars="1" :delay="300"
                                        :resolve-on-load="false"
                                        placeholder="Buscar vale por número (mín. 1 caracter)..."
                                        no-options-text="Escriba el número de vale"
                                        no-results-text="Sin vales PENDIENTE" :can-clear="true"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_vale }" />
                                    <small class="text-muted">
                                        <i class="ri-information-line me-1"></i>
                                        Solo vales PENDIENTE. Si hay vehículo seleccionado, filtra por ese vehículo.
                                    </small>
                                    <div v-if="form.errors.id_vale" class="text-danger small mt-1">{{
                                        form.errors.id_vale }}</div>
                                </div>

                                <!-- Tipo de carga -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">Tipo de Carga <span
                                            class="text-danger">*</span></label>
                                    <select v-model="form.tipo_carga" class="form-select"
                                        :class="{ 'is-invalid': form.errors.tipo_carga }" :disabled="!!form.id_vale">
                                        <option value="VALE">VALE</option>
                                        <option value="PREPAGO">PREPAGO</option>
                                    </select>
                                    <small v-if="form.id_vale" class="text-info">
                                        <i class="ri-information-line me-1"></i>Bloqueado: hay un vale seleccionado
                                    </small>
                                </div>



                                <!-- Grifo (estático, cargado desde servidor) -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">Grifo <span class="text-danger">*</span></label>
                                    <Multiselect v-model="form.id_grifo" :options="grifos" value-prop="id" label="label"
                                        :searchable="true" :filter-results="true" placeholder="Buscar grifo..."
                                        no-options-text="Sin grifos activos" no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_grifo }" />
                                    <div v-if="form.errors.id_grifo" class="text-danger small mt-1">{{
                                        form.errors.id_grifo }}</div>
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
                            <div v-if="respaldos.length === 0" class="text-center text-muted py-3">
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
                    {{ form.processing ? 'Guardando...' : 'Guardar Carga' }}
                </button>
            </div>
        </form>
    </Maindashboard>
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
