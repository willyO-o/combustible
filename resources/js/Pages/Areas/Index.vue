<script setup>
import { ref, computed, nextTick } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Modal } from 'bootstrap'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import SearchSelect from '@/Components/SearchSelect.vue'
import { confirm as confirmSwal } from '@/Utils/alertUtil.js'

const props = defineProps({
    areas: Object,
    flash: Object,
})

/* ------------------------------------------------------------------ *
 * Modal: crear / editar área
 * ------------------------------------------------------------------ */
const modalEl = ref(null)
let modalInstance = null

// null = creando un área nueva; objeto = editando esa área.
const editando = ref(null)

const form = useForm({
    nombre_area:      '',
    descripcion_area: '',
    estado_area:      'ACTIVO',
})

function abrirModal() {
    nextTick(() => {
        if (!modalInstance) {
            modalInstance = new Modal(modalEl.value)
        }
        modalInstance.show()
    })
}

function abrirCrear() {
    editando.value = null
    form.reset()
    form.clearErrors()
    abrirModal()
}

function abrirEditar(area) {
    editando.value = area
    form.nombre_area = area.nombre_area
    form.descripcion_area = area.descripcion_area ?? ''
    form.estado_area = area.estado_area
    form.clearErrors()
    abrirModal()
}

function submit() {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            modalInstance?.hide()
            form.reset()
        },
    }

    if (editando.value) {
        form.put(route('areas.update', editando.value.id), opciones)
    } else {
        form.post(route('areas.store'), opciones)
    }
}

async function confirmDelete(area) {
    const confirmado = await confirmSwal(
        `¿Eliminar el área <strong>${area.nombre_area}</strong>?`,
        'Eliminar Área',
        'Sí, eliminar',
    )

    if (!confirmado) {
        return
    }

    router.delete(route('areas.destroy', area.id))
}

const estadoBadge = (estado) =>
    estado === 'ACTIVO'
        ? 'bg-success-transparent text-success'
        : 'bg-danger-transparent text-danger'

/* ------------------------------------------------------------------ *
 * Modal: encargados del área (titular / suplente)
 * ------------------------------------------------------------------ */
const encargadosModalEl = ref(null)
let encargadosModalInstance = null

// Guardamos sólo el id: así el modal siempre refleja los datos frescos
// que llegan en `areas` tras cada asignación/finalización.
const areaEncargadosId = ref(null)
const areaEncargados = computed(() =>
    props.areas.data.find((a) => a.id === areaEncargadosId.value) ?? null
)

const titular = computed(() =>
    areaEncargados.value?.encargados_activos?.find((e) => e.tipo_encargo === 'TITULAR') ?? null
)
const suplente = computed(() =>
    areaEncargados.value?.encargados_activos?.find((e) => e.tipo_encargo === 'SUPLENTE') ?? null
)

const encargadoForm = useForm({
    id_persona:   null,
    email:        '',
    tipo_encargo: 'TITULAR',
    fecha_fin:    '',
    motivo:       '',
})

const personaTieneUsuario = ref(false)
const personaEmailActual = ref(null)

function abrirEncargados(area) {
    areaEncargadosId.value = area.id
    encargadoForm.reset()
    encargadoForm.clearErrors()
    personaTieneUsuario.value = false
    personaEmailActual.value = null

    nextTick(() => {
        if (!encargadosModalInstance) {
            encargadosModalInstance = new Modal(encargadosModalEl.value)
        }
        encargadosModalInstance.show()
    })
}

function onPersonaSeleccionada(persona) {
    personaTieneUsuario.value = persona?.tiene_usuario ?? false
    personaEmailActual.value = persona?.email_actual ?? null
    if (personaTieneUsuario.value) {
        encargadoForm.email = ''
    }
}

function onPersonaLimpiada() {
    personaTieneUsuario.value = false
    personaEmailActual.value = null
}

function submitEncargado() {
    encargadoForm
        .transform((data) => ({
            ...data,
            id_persona: data.id_persona?.id ?? data.id_persona,
            fecha_fin: data.fecha_fin || null,
        }))
        .post(route('areas.encargados.asignar', areaEncargadosId.value), {
            preserveScroll: true,
            onSuccess: () => {
                encargadoForm.reset()
                personaTieneUsuario.value = false
                personaEmailActual.value = null
            },
        })
}

async function finalizarEncargo(encargo) {
    const confirmado = await confirmSwal(
        `¿Finalizar el encargo de <strong>${encargo.nombre_completo}</strong>?`,
        'Finalizar Encargo',
        'Sí, finalizar',
    )

    if (!confirmado) {
        return
    }

    router.patch(
        route('areas.encargados.finalizar', [areaEncargadosId.value, encargo.id_encargo]),
        {},
        { preserveScroll: true },
    )
}

const encargoBadge = (tipo) =>
    tipo === 'TITULAR'
        ? 'bg-primary-transparent text-primary'
        : 'bg-info-transparent text-info'
</script>

<template>
    <Head title="Áreas" />

        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item active">Áreas</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Áreas</h1>
            </div>
            <button v-can="'areas.crear'" type="button" class="btn btn-primary btn-wave" @click="abrirCrear">
                <i class="ri-add-line me-1"></i> Nueva Área
            </button>
        </div>

        <!-- Flash messages -->
        <div v-if="flash?.success" class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-checkbox-circle-line me-2"></i>{{ flash.success }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <div v-if="flash?.error" class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ri-error-warning-line me-2"></i>{{ flash.error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <!-- Tabla -->
        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="card-title">
                    Listado
                    <span class="badge bg-primary-transparent text-primary ms-2">
                        {{ areas.total }} registros
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px">#</th>
                                <th>Área</th>
                                <th>Descripción</th>
                                <th style="width:220px">Encargados</th>
                                <th style="width:100px" class="text-center">Vehículos</th>
                                <th style="width:140px">Estado</th>
                                <th style="width:150px" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="areas.data.length === 0">
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="ri-building-line fs-3 d-block mb-2"></i>
                                    No se encontraron áreas
                                </td>
                            </tr>
                            <tr v-for="(area, idx) in areas.data" :key="area.id">
                                <td class="text-muted small">
                                    {{ (areas.current_page - 1) * areas.per_page + idx + 1 }}
                                </td>
                                <td class="fw-medium">{{ area.nombre_area }}</td>
                                <td style="max-width:240px;white-space:normal;">
                                    <small class="text-muted">
                                        {{ area.descripcion_area || '—' }}
                                    </small>
                                </td>
                                <td>
                                    <div v-if="area.encargados_activos.length === 0" class="text-muted small">
                                        Sin asignar
                                    </div>
                                    <div v-for="e in area.encargados_activos" :key="e.id_encargo" class="small mb-1">
                                        <span class="badge" :class="encargoBadge(e.tipo_encargo)">{{ e.tipo_encargo }}</span>
                                        {{ e.nombre_completo }}
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary-transparent text-primary">
                                        {{ area.vehiculos_count }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" :class="estadoBadge(area.estado_area)">
                                        {{ area.estado_area }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button
                                            v-can="'areas.encargados.asignar'"
                                            type="button"
                                            class="btn btn-sm btn-icon btn-primary-light"
                                            title="Encargados"
                                            @click="abrirEncargados(area)"
                                        >
                                            <i class="ri-user-star-line"></i>
                                        </button>
                                        <button
                                            v-can="'areas.editar'"
                                            type="button"
                                            class="btn btn-sm btn-icon btn-info-light"
                                            title="Editar"
                                            @click="abrirEditar(area)"
                                        >
                                            <i class="ri-edit-line"></i>
                                        </button>
                                        <button
                                            v-can="'areas.eliminar'"
                                            type="button"
                                            class="btn btn-sm btn-icon btn-danger-light"
                                            title="Eliminar"
                                            @click="confirmDelete(area)"
                                        >
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Paginador -->
            <div class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="text-muted small">
                    Mostrando
                    <strong>{{ areas.from ?? 0 }}</strong> -
                    <strong>{{ areas.to ?? 0 }}</strong>
                    de <strong>{{ areas.total }}</strong> resultados
                </div>
                <nav v-if="areas.last_page > 1" aria-label="Paginación">
                    <ul class="pagination pagination-sm mb-0">
                        <li
                            v-for="link in areas.links"
                            :key="link.label"
                            class="page-item"
                            :class="{ active: link.active, disabled: !link.url }"
                        >
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                class="page-link"
                                preserve-state
                                v-html="link.label"
                            />
                            <span v-else class="page-link" v-html="link.label" />
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- Modal: crear / editar área -->
        <div ref="modalEl" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form @submit.prevent="submit">
                        <div class="modal-header">
                            <h5 class="modal-title fw-medium">
                                {{ editando ? 'Editar Área' : 'Nueva Área' }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="nombre_area" class="form-label fw-medium">
                                    Nombre del Área <span class="text-danger">*</span>
                                </label>
                                <input
                                    id="nombre_area"
                                    v-model="form.nombre_area"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.nombre_area }"
                                    placeholder="Ej: Mantenimiento, Logística, Operaciones..."
                                    maxlength="200"
                                    autofocus
                                />
                                <InputError :message="form.errors.nombre_area" class="mt-1" />
                            </div>

                            <div class="mb-3">
                                <label for="descripcion_area" class="form-label fw-medium">
                                    Descripción
                                </label>
                                <textarea
                                    id="descripcion_area"
                                    v-model="form.descripcion_area"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.descripcion_area }"
                                    rows="3"
                                    placeholder="Detalle opcional del área (opcional)"
                                ></textarea>
                                <InputError :message="form.errors.descripcion_area" class="mt-1" />
                            </div>

                            <div>
                                <label class="form-label fw-medium">
                                    Estado <span class="text-danger">*</span>
                                </label>
                                <div class="d-flex gap-4 mt-1">
                                    <div class="form-check">
                                        <input
                                            id="area_estado_activo"
                                            v-model="form.estado_area"
                                            class="form-check-input"
                                            type="radio"
                                            value="ACTIVO"
                                        />
                                        <label for="area_estado_activo" class="form-check-label">
                                            <span class="badge bg-success-transparent text-success">ACTIVO</span>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input
                                            id="area_estado_inactivo"
                                            v-model="form.estado_area"
                                            class="form-check-input"
                                            type="radio"
                                            value="INACTIVO"
                                        />
                                        <label for="area_estado_inactivo" class="form-check-label">
                                            <span class="badge bg-danger-transparent text-danger">INACTIVO</span>
                                        </label>
                                    </div>
                                </div>
                                <InputError :message="form.errors.estado_area" class="mt-1" />
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-wave" data-bs-dismiss="modal">
                                Cancelar
                            </button>
                            <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                <i v-else class="ri-save-line me-1"></i>
                                {{ form.processing ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal: encargados del área -->
        <div ref="encargadosModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-medium">
                            Encargados: <span class="text-primary">{{ areaEncargados?.nombre_area }}</span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Estado actual: titular / suplente -->
                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <div class="card custom-card border h-100 mb-0">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="badge bg-primary-transparent text-primary">TITULAR</span>
                                            <button
                                                v-if="titular"
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Finalizar encargo"
                                                @click="finalizarEncargo(titular)"
                                            >
                                                <i class="ri-close-circle-line"></i>
                                            </button>
                                        </div>
                                        <div v-if="titular">
                                            <div class="fw-medium">{{ titular.nombre_completo }}</div>
                                            <small class="text-muted d-block">CI: {{ titular.ci }}</small>
                                            <small class="text-muted d-block">Desde: {{ titular.fecha_inicio }}</small>
                                            <small v-if="titular.fecha_fin" class="text-muted d-block">Hasta: {{ titular.fecha_fin }}</small>
                                        </div>
                                        <div v-else class="text-muted small">Sin titular asignado</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="card custom-card border h-100 mb-0">
                                    <div class="card-body">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <span class="badge bg-info-transparent text-info">SUPLENTE</span>
                                            <button
                                                v-if="suplente"
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Finalizar encargo"
                                                @click="finalizarEncargo(suplente)"
                                            >
                                                <i class="ri-close-circle-line"></i>
                                            </button>
                                        </div>
                                        <div v-if="suplente">
                                            <div class="fw-medium">{{ suplente.nombre_completo }}</div>
                                            <small class="text-muted d-block">CI: {{ suplente.ci }}</small>
                                            <small class="text-muted d-block">Desde: {{ suplente.fecha_inicio }}</small>
                                            <small v-if="suplente.fecha_fin" class="text-muted d-block">Hasta: {{ suplente.fecha_fin }}</small>
                                        </div>
                                        <div v-else class="text-muted small">Sin suplente asignado</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Formulario de asignación -->
                        <form @submit.prevent="submitEncargado">
                            <h6 class="fw-medium mb-3">Asignar / Reasignar Encargado</h6>
                            <div class="row g-3">
                                <div class="col-sm-4">
                                    <label class="form-label fw-medium">
                                        Tipo de Encargo <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        v-model="encargadoForm.tipo_encargo"
                                        class="form-select"
                                        :class="{ 'is-invalid': encargadoForm.errors.tipo_encargo }"
                                    >
                                        <option value="TITULAR">TITULAR</option>
                                        <option value="SUPLENTE">SUPLENTE</option>
                                    </select>
                                    <InputError :message="encargadoForm.errors.tipo_encargo" class="mt-1" />
                                </div>

                                <div class="col-sm-8">
                                    <label class="form-label fw-medium">
                                        Persona <span class="text-danger">*</span>
                                    </label>
                                    <SearchSelect
                                        v-model="encargadoForm.id_persona"
                                        :object="true"
                                        :search-url="route('search.personas-para-encargado')"
                                        placeholder="Buscar por CI o nombre (mín. 2 caracteres)..."
                                        :invalid="!!encargadoForm.errors.id_persona"
                                        @selected="onPersonaSeleccionada"
                                        @cleared="onPersonaLimpiada"
                                    />
                                    <InputError :message="encargadoForm.errors.id_persona" class="mt-1" />
                                    <small class="text-muted">Sólo se muestran personas activas.</small>
                                </div>

                                <div v-if="personaTieneUsuario" class="col-12">
                                    <div class="alert alert-info-transparent py-2 mb-0">
                                        <i class="ri-information-line me-1"></i>
                                        Esta persona ya tiene una cuenta ({{ personaEmailActual }}); se actualizará
                                        su rol a <strong>Jefe de Área</strong>.
                                    </div>
                                </div>
                                <div v-else-if="encargadoForm.id_persona" class="col-sm-8">
                                    <label class="form-label fw-medium">
                                        Correo Electrónico <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        v-model="encargadoForm.email"
                                        type="email"
                                        class="form-control"
                                        :class="{ 'is-invalid': encargadoForm.errors.email }"
                                        placeholder="correo@ejemplo.com"
                                        maxlength="255"
                                    />
                                    <InputError :message="encargadoForm.errors.email" class="mt-1" />
                                    <small class="text-muted">
                                        Esta persona no tiene cuenta de usuario; se creará una nueva con este correo.
                                    </small>
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Fecha de Finalización (opcional)</label>
                                    <input
                                        v-model="encargadoForm.fecha_fin"
                                        type="date"
                                        class="form-control"
                                        :class="{ 'is-invalid': encargadoForm.errors.fecha_fin }"
                                    />
                                    <InputError :message="encargadoForm.errors.fecha_fin" class="mt-1" />
                                    <small class="text-muted">Déjalo vacío si el encargo es indefinido.</small>
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">Motivo</label>
                                    <input
                                        v-model="encargadoForm.motivo"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': encargadoForm.errors.motivo }"
                                        placeholder="Ej: nombramiento, vacaciones..."
                                        maxlength="255"
                                    />
                                    <InputError :message="encargadoForm.errors.motivo" class="mt-1" />
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary btn-wave" :disabled="encargadoForm.processing">
                                    <span v-if="encargadoForm.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    <i v-else class="ri-user-add-line me-1"></i>
                                    {{ encargadoForm.processing ? 'Asignando...' : 'Asignar' }}
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-wave" data-bs-dismiss="modal">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
</template>
