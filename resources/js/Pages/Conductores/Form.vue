<script setup>
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import Multiselect from '@vueform/multiselect'

defineOptions({ layout: Maindashboard })

const props = defineProps({
    // Presentes sólo al editar.
    conductor: Object,
    asignacionActual: Object,
    // Vehículos activos para el selector de asignación (sólo al registrar).
    vehiculos: {
        type: Array,
        default: () => [],
    },
})

const esEdicion = computed(() => !!props.conductor)
const persona = computed(() => props.conductor?.persona ?? {})

const hoy = new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
    .toISOString()
    .slice(0, 10)

const form = useForm({
    ...(esEdicion.value ? { _method: 'PUT' } : {}),
    ci: persona.value.ci ?? '',
    nombres: persona.value.nombres ?? '',
    paterno: persona.value.paterno ?? '',
    materno: persona.value.materno ?? '',
    foto: null,
    celular: persona.value.celular ?? '',
    direccion: persona.value.direccion ?? '',
    fecha_nacimiento: persona.value.fecha_nacimiento
        ? persona.value.fecha_nacimiento.substring(0, 10)
        : '',
    estado_conductor: props.conductor?.estado_conductor ?? 'ACTIVO',
    documentos: (props.conductor?.documentos ?? []).map((documento) => ({
        id: documento.id,
        tipo_documento: documento.tipo_documento,
        numero_documento: documento.numero_documento ?? '',
        categoria: documento.categoria ?? '',
        fecha_emision: documento.fecha_emision ? documento.fecha_emision.substring(0, 10) : '',
        fecha_vencimiento: documento.fecha_vencimiento ? documento.fecha_vencimiento.substring(0, 10) : '',
        archivo: null,
        archivo_actual: documento.archivo_url ?? null,
        estado_documento: documento.estado_documento,
        observacion: documento.observacion ?? '',
    })),

    // --- Asignación opcional de un vehículo (sólo al registrar) -----------
    id_vehiculo: null,
    estado_asignacion: 'ACTIVO',
    fecha_asignacion: hoy,
    fecha_culminacion: '',
    detalle: '',
    kilometraje_inicial: '',
    horometro_inicial: '',
})

/* ---------------------------------------------------------------- *
 * Foto
 * ---------------------------------------------------------------- */
const fotoPreview = ref(persona.value.foto_url ?? null)

function onFotoChange(e) {
    const file = e.target.files[0]
    if (!file) return
    form.foto = file
    const reader = new FileReader()
    reader.onload = (ev) => (fotoPreview.value = ev.target.result)
    reader.readAsDataURL(file)
}

/* ---------------------------------------------------------------- *
 * Documentos
 * ---------------------------------------------------------------- */
const documentoVacio = () => ({
    id: null,
    tipo_documento: 'LICENCIA_DE_CONDUCIR',
    numero_documento: '',
    categoria: '',
    fecha_emision: '',
    fecha_vencimiento: '',
    archivo: null,
    archivo_actual: null,
    estado_documento: 'VIGENTE',
    observacion: '',
})

function agregarDocumento() {
    form.documentos.push(documentoVacio())
}

function eliminarDocumento(idx) {
    form.documentos.splice(idx, 1)
}

function onDocumentoArchivoChange(e, documento) {
    documento.archivo = e.target.files[0] ?? null
}

/* ---------------------------------------------------------------- *
 * Asignación de vehículo (sólo al registrar)
 * ---------------------------------------------------------------- */
// Multiselect con filtrado local: todos los vehículos activos llegan como
// prop y cada opción lleva una etiqueta que indica si ya está asignado a
// otro operario o está libre.
const vehiculosOpt = computed(() =>
    (props.vehiculos ?? []).map((v) => ({
        id: v.id,
        label:
            [v.codigo, v.nro_placa, v.marca].filter(Boolean).join(' — ') +
            (v.anio ? ` (${v.anio})` : ''),
        tipo_medicion: v.tipo_medicion,
        conductor_asignado: v.conductor_asignado ?? null,
    })),
)

const vehiculoSeleccionado = computed(() =>
    vehiculosOpt.value.find((v) => v.id === form.id_vehiculo) ?? null,
)
const tipoMedicionSeleccionado = computed(() => vehiculoSeleccionado.value?.tipo_medicion ?? null)

// Al cambiar de vehículo se limpian las lecturas iniciales (dependen del
// tipo de medición) y, si se deja vacío, todo el bloque de asignación.
watch(
    () => form.id_vehiculo,
    (valor) => {
        form.kilometraje_inicial = ''
        form.horometro_inicial = ''

        if (!valor) {
            form.estado_asignacion = 'ACTIVO'
            form.fecha_asignacion = hoy
            form.fecha_culminacion = ''
            form.detalle = ''
        }

        form.clearErrors(
            'id_vehiculo',
            'estado_asignacion',
            'fecha_asignacion',
            'fecha_culminacion',
            'detalle',
            'kilometraje_inicial',
            'horometro_inicial',
        )
    },
)

/* ---------------------------------------------------------------- *
 * Envío
 * ---------------------------------------------------------------- */
const CAMPOS_ASIGNACION = [
    'id_vehiculo',
    'estado_asignacion',
    'fecha_asignacion',
    'fecha_culminacion',
    'detalle',
    'kilometraje_inicial',
    'horometro_inicial',
]

function submit() {
    form
        .transform((data) => {
            const payload = { ...data }

            if (esEdicion.value) {
                // Al editar, la asignación de vehículo no se toca desde aquí.
                CAMPOS_ASIGNACION.forEach((campo) => delete payload[campo])
                return payload
            }

            if (!data.id_vehiculo) {
                CAMPOS_ASIGNACION.forEach((campo) => {
                    payload[campo] = ''
                })
            } else if (data.estado_asignacion !== 'PROVISIONAL') {
                payload.fecha_culminacion = ''
            }

            return payload
        })
        .post(
            esEdicion.value
                ? route('conductores.update', props.conductor.id)
                : route('conductores.store'),
            { forceFormData: true },
        )
}
</script>

<template>
    <Head :title="esEdicion ? 'Editar Operario' : 'Nuevo Operario'" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item"><Link :href="route('conductores.index')">Operarios</Link></li>
                    <li class="breadcrumb-item active">{{ esEdicion ? 'Editar' : 'Nuevo' }}</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                <template v-if="esEdicion">
                    Editar Operario:
                    <span class="text-primary">{{ persona.nombres }} {{ persona.paterno ?? '' }}</span>
                </template>
                <template v-else>Registrar Operario</template>
            </h1>
        </div>
        <Link :href="route('conductores.index')" class="btn btn-outline-secondary btn-wave">
            <i class="ri-arrow-left-line me-1"></i> Volver
        </Link>
    </div>

    <form class="op-form" @submit.prevent="submit" enctype="multipart/form-data">
        <div class="row g-4">
            <!-- Foto -->
            <div class="col-xl-3">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <div class="card-title"><i class="ri-camera-line me-2 text-muted"></i>Foto del Operario</div>
                    </div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center gap-3">
                        <label
                            class="op-form__photo"
                            :class="{ 'op-form__photo--invalid': form.errors.foto }"
                        >
                            <img v-if="fotoPreview" :src="fotoPreview" alt="Foto del operario" />
                            <span v-else class="op-form__photo-empty"><i class="ri-user-3-line"></i></span>
                            <span class="op-form__photo-overlay">
                                <i class="ri-camera-line"></i>
                                <span>{{ fotoPreview ? 'Cambiar foto' : 'Subir foto' }}</span>
                            </span>
                            <input
                                type="file"
                                class="op-form__photo-input"
                                accept="image/jpeg,image/png,image/webp"
                                @change="onFotoChange"
                            />
                        </label>
                        <div class="text-center">
                            <div v-if="form.errors.foto" class="text-danger small mb-1">{{ form.errors.foto }}</div>
                            <small class="text-muted">
                                {{ esEdicion
                                    ? 'Dejar vacío para conservar la foto actual.'
                                    : 'Obligatoria · JPG, PNG o WEBP · máx. 2 MB.' }}
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos personales -->
            <div class="col-xl-9">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <div class="card-title"><i class="ri-user-line me-2 text-muted"></i>Datos Personales</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- CI -->
                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">
                                    Carnet de Identidad <span class="text-danger">*</span>
                                </label>
                                <input
                                    v-model="form.ci"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.ci }"
                                    placeholder="Ej: 12345678"
                                    maxlength="20"
                                />
                                <div v-if="form.errors.ci" class="invalid-feedback">{{ form.errors.ci }}</div>
                            </div>

                            <!-- Nombres -->
                            <div class="col-sm-6 col-xl-8">
                                <label class="form-label fw-medium">
                                    Nombres <span class="text-danger">*</span>
                                </label>
                                <input
                                    v-model="form.nombres"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.nombres }"
                                    placeholder="Nombres completos"
                                    maxlength="150"
                                />
                                <div v-if="form.errors.nombres" class="invalid-feedback">{{ form.errors.nombres }}</div>
                            </div>

                            <!-- Paterno -->
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Apellido Paterno</label>
                                <input
                                    v-model="form.paterno"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.paterno }"
                                    placeholder="Apellido paterno"
                                    maxlength="150"
                                />
                                <div v-if="form.errors.paterno" class="invalid-feedback">{{ form.errors.paterno }}</div>
                            </div>

                            <!-- Materno -->
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">Apellido Materno</label>
                                <input
                                    v-model="form.materno"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.materno }"
                                    placeholder="Apellido materno"
                                    maxlength="150"
                                />
                                <div v-if="form.errors.materno" class="invalid-feedback">{{ form.errors.materno }}</div>
                            </div>

                            <div class="col-12"><hr class="op-form__rule" /></div>

                            <!-- Celular -->
                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Celular</label>
                                <input
                                    v-model="form.celular"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.celular }"
                                    placeholder="Ej: 70000000"
                                    maxlength="20"
                                />
                                <div v-if="form.errors.celular" class="invalid-feedback">{{ form.errors.celular }}</div>
                            </div>

                            <!-- Fecha nacimiento -->
                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">Fecha de Nacimiento</label>
                                <input
                                    v-model="form.fecha_nacimiento"
                                    type="date"
                                    :max="hoy"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.fecha_nacimiento }"
                                />
                                <div v-if="form.errors.fecha_nacimiento" class="invalid-feedback">{{ form.errors.fecha_nacimiento }}</div>
                            </div>

                            <!-- Estado -->
                            <div class="col-sm-6 col-xl-4">
                                <label class="form-label fw-medium">
                                    Estado <span class="text-danger">*</span>
                                </label>
                                <select
                                    v-model="form.estado_conductor"
                                    class="form-select"
                                    :class="{ 'is-invalid': form.errors.estado_conductor }"
                                >
                                    <option value="ACTIVO">ACTIVO</option>
                                    <option value="INACTIVO">INACTIVO</option>
                                    <option value="RETIRADO">RETIRADO</option>
                                </select>
                                <div v-if="form.errors.estado_conductor" class="invalid-feedback">{{ form.errors.estado_conductor }}</div>
                            </div>

                            <!-- Dirección -->
                            <div class="col-12">
                                <label class="form-label fw-medium">Dirección</label>
                                <textarea
                                    v-model="form.direccion"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.direccion }"
                                    rows="2"
                                    placeholder="Dirección del operario"
                                    maxlength="250"
                                ></textarea>
                                <div v-if="form.errors.direccion" class="invalid-feedback">{{ form.errors.direccion }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Asignación de vehículo -->
        <div class="card custom-card mt-4">
            <div class="card-header">
                <div class="card-title">
                    <i class="ri-steering-2-line me-2 text-muted"></i>
                    {{ esEdicion ? 'Vehículo Asignado' : 'Asignación de Vehículo' }}
                    <span v-if="!esEdicion" class="text-muted fw-normal">(opcional)</span>
                </div>
            </div>

            <!-- EDICIÓN: sólo lectura -->
            <div v-if="esEdicion" class="card-body">
                <div class="op-form__assign-current">
                    <span class="op-form__assign-current-icon">
                        <i :class="asignacionActual ? 'ri-truck-line' : 'ri-steering-2-line'"></i>
                    </span>
                    <div class="flex-grow-1">
                        <template v-if="asignacionActual">
                            <div class="fw-semibold">
                                {{ asignacionActual.vehiculo?.codigo }} — {{ asignacionActual.vehiculo?.nro_placa }}
                                <span v-if="asignacionActual.vehiculo?.marca" class="text-muted fw-normal">
                                    ({{ [asignacionActual.vehiculo.marca, asignacionActual.vehiculo.anio].filter(Boolean).join(' · ') }})
                                </span>
                            </div>
                            <div class="mt-1">
                                <span
                                    class="badge"
                                    :class="asignacionActual.estado_asignacion === 'ACTIVO'
                                        ? 'bg-primary-transparent text-primary'
                                        : 'bg-info-transparent text-info'"
                                >
                                    {{ asignacionActual.estado_asignacion }}
                                </span>
                                <span class="text-muted small ms-2">Asignado desde {{ asignacionActual.fecha_asignacion }}</span>
                            </div>
                        </template>
                        <template v-else>
                            <div class="fw-semibold">Sin vehículo asignado</div>
                            <div class="text-muted small mt-1">Este operario todavía no tiene un vehículo a su cargo.</div>
                        </template>
                    </div>
                </div>
                <p class="op-form__assign-hint mb-0">
                    <i class="ri-information-line me-1"></i>
                    La asignación y reasignación de vehículos se gestiona desde el
                    <Link :href="route('conductores.index')">listado de operarios</Link>,
                    con la acción «Reasignar vehículo».
                </p>
            </div>

            <!-- ALTA: se puede asignar un vehículo -->
            <div v-else class="card-body">
                <label class="form-label fw-medium">Vehículo</label>
                <Multiselect
                    v-model="form.id_vehiculo"
                    :options="vehiculosOpt"
                    value-prop="id"
                    label="label"
                    :searchable="true"
                    :filter-results="true"
                    :can-clear="true"
                    placeholder="Buscar por código, placa o marca…"
                    no-options-text="No hay vehículos activos"
                    no-results-text="Sin resultados"
                    :class="{ 'is-invalid-multiselect': form.errors.id_vehiculo }"
                >
                    <template #option="{ option }">
                        <span class="me-2">{{ option.label }}</span>
                        <span
                            class="badge ms-auto"
                            :class="option.conductor_asignado
                                ? 'bg-warning-transparent text-warning'
                                : 'bg-success-transparent text-success'"
                        >
                            {{ option.conductor_asignado ? `Asignado · ${option.conductor_asignado}` : 'Libre' }}
                        </span>
                    </template>
                    <template #singlelabel="{ value }">
                        <div class="multiselect-single-label">
                            <span>{{ value.label }}</span>
                            <span
                                class="badge ms-2"
                                :class="value.conductor_asignado
                                    ? 'bg-warning-transparent text-warning'
                                    : 'bg-success-transparent text-success'"
                            >
                                {{ value.conductor_asignado ? 'Reasignación' : 'Libre' }}
                            </span>
                        </div>
                    </template>
                </Multiselect>
                <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">{{ form.errors.id_vehiculo }}</div>
                <small class="text-muted d-block mt-1">
                    Opcional. Deja el campo vacío para asignar el vehículo más tarde desde el listado de operarios.
                </small>

                <!-- Detalle de la asignación: aparece al elegir un vehículo -->
                <div v-if="form.id_vehiculo" class="op-form__assign mt-3">
                    <p v-if="vehiculoSeleccionado?.conductor_asignado" class="op-form__assign-warn">
                        <i class="ri-error-warning-line me-1"></i>
                        <span>
                            <strong>{{ vehiculoSeleccionado.conductor_asignado }}</strong> tiene este vehículo asignado.
                            Al asignarlo al nuevo operario, esa asignación pasará a <strong>REASIGNADO</strong>.
                        </span>
                    </p>

                    <div class="row g-3">
                        <!-- Tipo de asignación -->
                        <div class="col-sm-6">
                            <label class="form-label fw-medium">
                                Tipo de Asignación <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex gap-4 mt-1">
                                <div class="form-check">
                                    <input
                                        id="asignacion_activo"
                                        v-model="form.estado_asignacion"
                                        class="form-check-input"
                                        type="radio"
                                        value="ACTIVO"
                                    />
                                    <label for="asignacion_activo" class="form-check-label">Activo</label>
                                </div>
                                <div class="form-check">
                                    <input
                                        id="asignacion_provisional"
                                        v-model="form.estado_asignacion"
                                        class="form-check-input"
                                        type="radio"
                                        value="PROVISIONAL"
                                    />
                                    <label for="asignacion_provisional" class="form-check-label">Provisional</label>
                                </div>
                            </div>
                            <div v-if="form.errors.estado_asignacion" class="text-danger small mt-1">{{ form.errors.estado_asignacion }}</div>
                            <small class="text-muted">Provisional: reemplazo temporal del encargado titular.</small>
                        </div>

                        <!-- Fecha de asignación -->
                        <div class="col-sm-6">
                            <label class="form-label fw-medium">Fecha de Asignación</label>
                            <input
                                v-model="form.fecha_asignacion"
                                type="date"
                                :max="hoy"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.fecha_asignacion }"
                            />
                            <div v-if="form.errors.fecha_asignacion" class="invalid-feedback">{{ form.errors.fecha_asignacion }}</div>
                        </div>

                        <!-- Fecha de finalización (sólo provisional) -->
                        <div v-if="form.estado_asignacion === 'PROVISIONAL'" class="col-sm-6">
                            <label class="form-label fw-medium">Fecha de Finalización <span class="text-muted fw-normal">(opcional)</span></label>
                            <input
                                v-model="form.fecha_culminacion"
                                type="date"
                                :min="hoy"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.fecha_culminacion }"
                            />
                            <div v-if="form.errors.fecha_culminacion" class="invalid-feedback">{{ form.errors.fecha_culminacion }}</div>
                        </div>

                        <!-- Lectura inicial según tipo de medición -->
                        <div v-if="tipoMedicionSeleccionado === 'kilometraje'" class="col-sm-6">
                            <label class="form-label fw-medium">
                                Kilometraje Inicial <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input
                                    v-model="form.kilometraje_inicial"
                                    type="number"
                                    min="0"
                                    step="any"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.kilometraje_inicial }"
                                />
                                <span class="input-group-text">km</span>
                            </div>
                            <div v-if="form.errors.kilometraje_inicial" class="text-danger small mt-1">{{ form.errors.kilometraje_inicial }}</div>
                        </div>

                        <div v-if="tipoMedicionSeleccionado === 'horometro'" class="col-sm-6">
                            <label class="form-label fw-medium">
                                Horómetro Inicial <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input
                                    v-model="form.horometro_inicial"
                                    type="number"
                                    min="0"
                                    step="any"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.horometro_inicial }"
                                />
                                <span class="input-group-text">h</span>
                            </div>
                            <div v-if="form.errors.horometro_inicial" class="text-danger small mt-1">{{ form.errors.horometro_inicial }}</div>
                        </div>

                        <!-- Motivo -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Motivo <span class="text-muted fw-normal">(opcional)</span></label>
                            <textarea
                                v-model="form.detalle"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors.detalle }"
                                rows="2"
                                maxlength="1000"
                                placeholder="Ej: asignación inicial, reemplazo por mantenimiento…"
                            ></textarea>
                            <div v-if="form.errors.detalle" class="invalid-feedback">{{ form.errors.detalle }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documentos del Operario -->
        <div class="card custom-card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="card-title">
                    <i class="ri-folder-3-line me-2 text-muted"></i>Documentos del Operario
                    <span class="text-muted fw-normal">(opcional)</span>
                </div>
                <button
                    v-if="form.documentos.length"
                    type="button"
                    class="btn btn-sm btn-outline-primary btn-wave"
                    @click="agregarDocumento"
                >
                    <i class="ri-add-line me-1"></i> Agregar
                </button>
            </div>

            <div v-if="form.documentos.length === 0" class="card-body op-form__docs-empty">
                <i class="ri-file-list-3-line"></i>
                <p class="mb-2">Sin documentos registrados</p>
                <button type="button" class="btn btn-sm btn-primary btn-wave" @click="agregarDocumento">
                    <i class="ri-add-line me-1"></i> Agregar Documento
                </button>
            </div>

            <div v-else class="card-body d-flex flex-column gap-3">
                <div v-for="(documento, idx) in form.documentos" :key="idx" class="op-form__doc">
                    <div class="op-form__doc-head">
                        <span class="fw-medium">Documento {{ idx + 1 }}</span>
                        <button
                            type="button"
                            class="btn btn-sm btn-icon btn-danger-light"
                            title="Quitar documento"
                            @click="eliminarDocumento(idx)"
                        >
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">
                                Tipo de Documento <span class="text-danger">*</span>
                            </label>
                            <select
                                v-model="documento.tipo_documento"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors[`documentos.${idx}.tipo_documento`] }"
                            >
                                <option value="LICENCIA_DE_CONDUCIR">Licencia de Conducir</option>
                                <option value="CI">Carnet de Identidad</option>
                                <option value="CERTIFICADO_MEDICO">Certificado Médico</option>
                                <option value="OTRO">Otro</option>
                            </select>
                            <div v-if="form.errors[`documentos.${idx}.tipo_documento`]" class="invalid-feedback">
                                {{ form.errors[`documentos.${idx}.tipo_documento`] }}
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Número de Documento</label>
                            <input
                                v-model="documento.numero_documento"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors[`documentos.${idx}.numero_documento`] }"
                                maxlength="100"
                                placeholder="Ej: LIC-12345"
                            />
                            <div v-if="form.errors[`documentos.${idx}.numero_documento`]" class="invalid-feedback">
                                {{ form.errors[`documentos.${idx}.numero_documento`] }}
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-2">
                            <label class="form-label fw-medium">Categoría</label>
                            <input
                                v-model="documento.categoria"
                                type="text"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors[`documentos.${idx}.categoria`] }"
                                maxlength="10"
                                placeholder="Ej: B-2"
                            />
                            <div v-if="form.errors[`documentos.${idx}.categoria`]" class="invalid-feedback">
                                {{ form.errors[`documentos.${idx}.categoria`] }}
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-2">
                            <label class="form-label fw-medium">Estado</label>
                            <select
                                v-model="documento.estado_documento"
                                class="form-select"
                                :class="{ 'is-invalid': form.errors[`documentos.${idx}.estado_documento`] }"
                            >
                                <option value="VIGENTE">VIGENTE</option>
                                <option value="VENCIDO">VENCIDO</option>
                                <option value="OBSERVADO">OBSERVADO</option>
                            </select>
                            <div v-if="form.errors[`documentos.${idx}.estado_documento`]" class="invalid-feedback">
                                {{ form.errors[`documentos.${idx}.estado_documento`] }}
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-2">
                            <label class="form-label fw-medium">Fecha de Emisión</label>
                            <input
                                v-model="documento.fecha_emision"
                                type="date"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors[`documentos.${idx}.fecha_emision`] }"
                            />
                            <div v-if="form.errors[`documentos.${idx}.fecha_emision`]" class="invalid-feedback">
                                {{ form.errors[`documentos.${idx}.fecha_emision`] }}
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Fecha de Vencimiento</label>
                            <input
                                v-model="documento.fecha_vencimiento"
                                type="date"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors[`documentos.${idx}.fecha_vencimiento`] }"
                            />
                            <div v-if="form.errors[`documentos.${idx}.fecha_vencimiento`]" class="invalid-feedback">
                                {{ form.errors[`documentos.${idx}.fecha_vencimiento`] }}
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Archivo</label>
                            <input
                                type="file"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors[`documentos.${idx}.archivo`] }"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                @change="onDocumentoArchivoChange($event, documento)"
                            />
                            <div v-if="form.errors[`documentos.${idx}.archivo`]" class="invalid-feedback">
                                {{ form.errors[`documentos.${idx}.archivo`] }}
                            </div>
                            <a
                                v-if="documento.archivo_actual"
                                :href="documento.archivo_actual"
                                target="_blank"
                                class="d-inline-flex align-items-center gap-1 fs-12 mt-1"
                            >
                                <i class="ri-external-link-line"></i> Ver archivo actual
                            </a>
                            <small class="text-muted d-block">
                                JPG, PNG, WEBP o PDF · máx. 4 MB.
                                <template v-if="esEdicion">Dejar vacío para conservar el actual.</template>
                            </small>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-medium">Observación</label>
                            <textarea
                                v-model="documento.observacion"
                                class="form-control"
                                :class="{ 'is-invalid': form.errors[`documentos.${idx}.observacion`] }"
                                rows="2"
                                maxlength="1000"
                                placeholder="Observaciones sobre el documento…"
                            ></textarea>
                            <div v-if="form.errors[`documentos.${idx}.observacion`]" class="invalid-feedback">
                                {{ form.errors[`documentos.${idx}.observacion`] }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Barra de acciones -->
        <div class="op-form__actions">
            <Link :href="route('conductores.index')" class="btn btn-outline-secondary btn-wave">
                Cancelar
            </Link>
            <button
                type="submit"
                class="btn btn-primary btn-wave"
                :disabled="form.processing"
            >
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                <i v-else class="ri-save-line me-1"></i>
                {{ form.processing
                    ? (esEdicion ? 'Actualizando…' : 'Guardando…')
                    : (esEdicion ? 'Actualizar Operario' : 'Guardar Operario') }}
            </button>
        </div>
    </form>
</template>
