<script setup>
import { onMounted, ref, computed, nextTick, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { Modal } from 'bootstrap'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import { confirm } from '@/Utils/alertUtil.js'
import InputError from '@/Components/InputError.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculos: Object,
    tiposVehiculo: Array,
    areas: Array,
    filters: Object,
    flash: Object,
})

const filters = ref({
    nro_placa: props.filters?.nro_placa ?? '',
    codigo: props.filters?.codigo ?? '',
    marca: props.filters?.marca ?? '',
    estado_vehiculo: props.filters?.estado_vehiculo ?? '',
    id_tipo_vehiculo: props.filters?.id_tipo_vehiculo ?? '',
    id_area: props.filters?.id_area ?? '',
})

let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('vehiculos.index'),
                {
                    nro_placa: val.nro_placa || undefined,
                    codigo: val.codigo || undefined,
                    marca: val.marca || undefined,
                    estado_vehiculo: val.estado_vehiculo || undefined,
                    id_tipo_vehiculo: val.id_tipo_vehiculo || undefined,
                    id_area: val.id_area || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { nro_placa: '', codigo: '', marca: '', estado_vehiculo: '', id_tipo_vehiculo: '', id_area: '' }
}

async function confirmDelete(vehiculo) {
    const confirmado = await confirm(
        `¿Eliminar el vehículo con placa "${vehiculo.nro_placa}"?`,
        'Eliminar Vehículo',
        'Sí, eliminar',
    )

    if (!confirmado) {
        return
    }

    router.delete(route('vehiculos.destroy', vehiculo.id))
}

const estadoBadge = (estado) => {
    const map = {
        ACTIVO: 'bg-success-transparent text-success',
        RETIRADO: 'bg-warning-transparent text-warning',
        VENDIDO: 'bg-secondary-transparent text-secondary',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

const fotoUrl = (foto) => foto ? `/storage/${foto}` : null


onMounted(() => {
    console.log(props.vehiculos);
})

/* ------------------------------------------------------------------ *
 * Modal: reasignar área
 * ------------------------------------------------------------------ */
const areaModalEl = ref(null)
let areaModalInstance = null

// Guardamos sólo el id: así el modal siempre refleja los datos frescos
// que llegan en `vehiculos` tras cada asignación/finalización.
const vehiculoAsignacionId = ref(null)
const vehiculoAsignacion = computed(() =>
    props.vehiculos.data.find((v) => v.id === vehiculoAsignacionId.value) ?? null
)
const asignacionAreaActual = computed(() => vehiculoAsignacion.value?.areas_asignadas?.[0] ?? null)

const areaForm = useForm({
    id_area: '',
    estado_asignacion: 'ACTIVO',
    fecha_culminacion: '',
    motivo_asignacion: '',
})

function abrirAsignarArea(vehiculo) {
    vehiculoAsignacionId.value = vehiculo.id
    areaForm.reset()
    areaForm.clearErrors()

    nextTick(() => {
        if (!areaModalInstance) {
            areaModalInstance = new Modal(areaModalEl.value)
        }
        areaModalInstance.show()
    })
}

function submitAsignarArea() {
    areaForm
        .transform((data) => ({
            ...data,
            fecha_culminacion: data.estado_asignacion === 'PROVISIONAL' ? (data.fecha_culminacion || null) : null,
        }))
        .post(route('vehiculos.areas.asignar', vehiculoAsignacionId.value), {
            preserveScroll: true,
            onSuccess: () => {
                areaForm.reset()
            },
        })
}

async function finalizarAsignacionArea(asignacion) {
    const confirmado = await confirm(
        `¿Finalizar la asignación al área <strong>${asignacion.nombre_area}</strong>?`,
        'Finalizar Asignación',
        'Sí, finalizar',
    )

    if (!confirmado) {
        return
    }

    router.patch(
        route('vehiculos.areas.finalizar', [vehiculoAsignacionId.value, asignacion.pivot.id]),
        {},
        { preserveScroll: true },
    )
}

const tipoAsignacionBadge = (tipo) =>
    tipo === 'ACTIVO'
        ? 'bg-primary-transparent text-primary'
        : 'bg-info-transparent text-info'
</script>

<template>

    <Head title="Vehículos" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item active">Vehículos</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Gestión de Vehículos</h1>
        </div>
        <Link :href="route('vehiculos.create')" class="btn btn-primary btn-wave">
            <i class="ri-add-line me-1"></i> Nuevo Vehículo
        </Link>
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

    <!-- Filtros -->
    <div class="card custom-card mb-4">
        <div class="card-header">
            <div class="card-title">Filtros de búsqueda</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label">Nro. Placa</label>
                    <input v-model="filters.nro_placa" type="text" class="form-control"
                        placeholder="Buscar por placa..." />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label">Código</label>
                    <input v-model="filters.codigo" type="text" class="form-control"
                        placeholder="Buscar por código..." />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label">Marca</label>
                    <input v-model="filters.marca" type="text" class="form-control" placeholder="Buscar por marca..." />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label">Tipo de Vehículo</label>
                    <select v-model="filters.id_tipo_vehiculo" class="form-select">
                        <option value="">Todos</option>
                        <option v-for="tv in tiposVehiculo" :key="tv.id" :value="tv.id">
                            {{ tv.tipo_vehiculo }}
                        </option>
                    </select>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label">Estado</label>
                    <select v-model="filters.estado_vehiculo" class="form-select">
                        <option value="">Todos</option>
                        <option value="ACTIVO">ACTIVO</option>
                        <option value="RETIRADO">RETIRADO</option>
                        <option value="VENDIDO">VENDIDO</option>
                    </select>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <label class="form-label">Área</label>
                    <select v-model="filters.id_area" class="form-select">
                        <option value="">Todas</option>
                        <option v-for="a in areas" :key="a.id" :value="a.id">
                            {{ a.nombre_area }}
                        </option>
                    </select>
                </div>
            </div>
            <div class="mt-3 d-flex justify-content-end">
                <button type="button" class="btn btn-outline-secondary btn-wave" @click="clearFilters">
                    <i class="ri-refresh-line me-1"></i> Limpiar filtros
                </button>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card custom-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title">
                Vehículos
                <span class="badge bg-primary-transparent text-primary ms-2">
                    {{ vehiculos.total }} registros
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm  mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Vehiculo / Placa / Modelo</th>
                            <th>Codigo</th>
                            <th>Tipo Vehículo</th>
                            <th>Área Asignada</th>
                            <th>Estado</th>
                            <th>Operario</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="vehiculos.data.length === 0">
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="ri-car-line fs-3 d-block mb-2"></i>
                                No se encontraron vehículos
                            </td>
                        </tr>
                        <tr v-for="vehiculo in vehiculos.data" :key="vehiculo.id">
                            <td>
                                <div class="d-flex align-items-center gap-3 position-relative">
                                    <div class="lh-1">
                                        <span class="avatar avatar-lg bg-light">
                                            <img v-if="fotoUrl(vehiculo.fotografia)" :src="fotoUrl(vehiculo.fotografia)"
                                                :alt="vehiculo.nro_placa" class="rounded"
                                                style="width:36px;height:36px;object-fit:cover;" />
                                            <span v-else
                                                class="avatar avatar-sm avatar-rounded bg-light text-muted d-flex align-items-center justify-content-center">
                                                <i class="ri-car-line"></i>
                                            </span>
                                        </span>
                                    </div>
                                    <div>
                                        <span class="d-block fw-semibold">{{ vehiculo.nro_placa }}</span>
                                        <span class="text-muted fs-13">
                                            {{ vehiculo.marca ?? '—' }} {{ vehiculo.modelo ?? '' }}
                                        </span>
                                        <span class="text-muted fs-13 d-block">
                                            {{ vehiculo.anio ?? '—' }}
                                        </span>
                                    </div>
                                </div>

                            </td>
                            <td>{{ vehiculo.codigo ?? '—' }}</td>
                            <td>
                                <span class=" small text-wrap">
                                    {{ vehiculo.tipo_vehiculo?.tipo_vehiculo ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center small gap-2">
                                    <div>
                                        <span v-for="area in vehiculo.areas_asignadas" class="fw-semibold">
                                            <i class="ri-building-line me-1"></i>
                                            {{ area.nombre_area }}
                                            <span class="badge ms-1"
                                                :class="tipoAsignacionBadge(area.pivot.estado_asignacion)">
                                                {{ area.pivot.estado_asignacion == 'PROVISIONAL' ? 'Provisional' : '' }}
                                            </span>
                                        </span>
                                        <span v-if="!vehiculo.areas_asignadas?.length" class="text-muted">Sin asignar</span>
                                    </div>
                                    <button v-can="'vehiculos.asignar-area'" type="button"
                                        class="btn btn-sm btn-icon btn-light" title="Reasignar Área"
                                        @click="abrirAsignarArea(vehiculo)">
                                        <i class="ri-edit-line"></i>
                                    </button>
                                </div>
                            </td>


                            <td>
                                <span class="badge" :class="estadoBadge(vehiculo.estado_vehiculo)">
                                    {{ vehiculo.estado_vehiculo }}
                                </span>
                            </td>
                            <td>
                                <span v-if="vehiculo.conductor_asignado" class="fw-semibold">
                                    <i class="ri-user-line me-1"></i>
                                    {{ vehiculo.conductor_asignado.persona.nombre_completo }}
                                </span>
                                <span v-else class="text-muted">
                                    N/A
                                </span>
                            </td>

                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <Link :href="route('vehiculos.show', vehiculo.id)"
                                        class="btn btn-sm btn-icon btn-primary-light" title="Editar">
                                        <i class="ri-eye-line"></i>
                                    </Link>
                                    <Link :href="route('vehiculos.edit', vehiculo.id)"
                                        class="btn btn-sm btn-icon btn-info-light" title="Editar">
                                        <i class="ri-edit-line"></i>
                                    </Link>
                                    <button type="button" class="btn btn-sm btn-icon btn-danger-light" title="Eliminar"
                                        @click="confirmDelete(vehiculo)">
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
                Mostrando {{ vehiculos.from ?? 0 }} - {{ vehiculos.to ?? 0 }}
                de {{ vehiculos.total }} resultados
            </div>
            <nav v-if="vehiculos.last_page > 1">
                <ul class="pagination pagination-sm mb-0">
                    <li v-for="link in vehiculos.links" :key="link.label" class="page-item"
                        :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" class="page-link" preserve-state v-html="link.label" />
                        <span v-else class="page-link" v-html="link.label" />
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Modal: reasignar área -->
    <div ref="areaModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-medium">
                        Área asignada:
                        <span class="text-primary">{{ vehiculoAsignacion?.nro_placa }}</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Estado actual -->
                    <div class="card custom-card border mb-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span v-if="asignacionAreaActual" class="badge"
                                    :class="tipoAsignacionBadge(asignacionAreaActual.pivot.estado_asignacion)">
                                    {{ asignacionAreaActual.pivot.estado_asignacion }}
                                </span>
                                <span v-else class="badge bg-secondary-transparent text-secondary">Sin área
                                    asignada</span>
                                <button v-if="asignacionAreaActual" type="button" class="btn btn-sm btn-outline-danger"
                                    title="Finalizar asignación" @click="finalizarAsignacionArea(asignacionAreaActual)">
                                    <i class="ri-close-circle-line"></i>
                                </button>
                            </div>
                            <div v-if="asignacionAreaActual">
                                <div class="fw-medium">{{ asignacionAreaActual.nombre_area }}</div>
                                <small class="text-muted d-block">Desde: {{ asignacionAreaActual.pivot.fecha_asignacion
                                    }}</small>
                                <small v-if="asignacionAreaActual.pivot.fecha_culminacion" class="text-muted d-block">
                                    Hasta: {{ asignacionAreaActual.pivot.fecha_culminacion }}
                                </small>
                                <small v-if="asignacionAreaActual.pivot.motivo_asignacion" class="text-muted d-block">
                                    Motivo: {{ asignacionAreaActual.pivot.motivo_asignacion }}
                                </small>
                            </div>
                            <div v-else class="text-muted small">Este vehículo no tiene un área asignada</div>
                        </div>
                    </div>

                    <!-- Formulario de asignación -->
                    <form @submit.prevent="submitAsignarArea">
                        <h6 class="fw-medium mb-3">Asignar / Reasignar Área</h6>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">
                                    Área <span class="text-danger">*</span>
                                </label>
                                <select v-model="areaForm.id_area" class="form-select"
                                    :class="{ 'is-invalid': areaForm.errors.id_area }">
                                    <option value="">— Seleccionar —</option>
                                    <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.nombre_area }}</option>
                                </select>
                                <InputError :message="areaForm.errors.id_area" class="mt-1" />
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label fw-medium">
                                    Tipo de Asignación <span class="text-danger">*</span>
                                </label>
                                <div class="d-flex gap-4 mt-1">
                                    <div class="form-check">
                                        <input id="vehiculo_area_activo" v-model="areaForm.estado_asignacion"
                                            class="form-check-input" type="radio" value="ACTIVO" />
                                        <label for="vehiculo_area_activo" class="form-check-label">ACTIVO</label>
                                    </div>
                                    <div class="form-check">
                                        <input id="vehiculo_area_provisional" v-model="areaForm.estado_asignacion"
                                            class="form-check-input" type="radio" value="PROVISIONAL" />
                                        <label for="vehiculo_area_provisional"
                                            class="form-check-label">PROVISIONAL</label>
                                    </div>
                                </div>
                                <InputError :message="areaForm.errors.estado_asignacion" class="mt-1" />
                                <small class="text-muted">
                                    Provisional: préstamo temporal a otra área.
                                </small>
                            </div>

                            <div v-if="areaForm.estado_asignacion === 'PROVISIONAL'" class="col-sm-6">
                                <label class="form-label fw-medium">Fecha de Finalización (opcional)</label>
                                <input v-model="areaForm.fecha_culminacion" type="date" class="form-control"
                                    :class="{ 'is-invalid': areaForm.errors.fecha_culminacion }" />
                                <InputError :message="areaForm.errors.fecha_culminacion" class="mt-1" />
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-medium">Motivo</label>
                                <textarea v-model="areaForm.motivo_asignacion" class="form-control"
                                    :class="{ 'is-invalid': areaForm.errors.motivo_asignacion }" rows="2"
                                    placeholder="Ej: préstamo por campaña, reasignación permanente..."></textarea>
                                <InputError :message="areaForm.errors.motivo_asignacion" class="mt-1" />
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary btn-wave" :disabled="areaForm.processing">
                                <span v-if="areaForm.processing" class="spinner-border spinner-border-sm me-1"
                                    role="status"></span>
                                <i v-else class="ri-exchange-line me-1"></i>
                                {{ areaForm.processing ? 'Asignando...' : 'Asignar' }}
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
