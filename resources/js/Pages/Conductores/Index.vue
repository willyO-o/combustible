<script setup>
import { ref, computed, nextTick, watch } from 'vue'
import { Head, Link, router, useForm, InfiniteScroll } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

import { showToast, confirm , showError} from '@/Utils/alertUtil.js'
import InputError from '@/Components/InputError.vue'
import SearchSelect from '@/Components/SearchSelect.vue'
import { useBootstrapModal } from '@/Composables/useBootstrapModal'
import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'

const props = defineProps({
    conductores: Object,
    filters: Object,
    flash: Object,
})

// Debajo de "lg" (tablet en portrait y celular) se muestra un segundo
// listado en tarjetas con scroll infinito en vez de la tabla (ver
// .ai/rules/pages.md, "Listado responsivo con scroll infinito").
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

// Filtros reactivos inicializados con los valores que llegan del servidor
const filters = ref({
    ci: props.filters?.ci ?? '',
    nombres: props.filters?.nombres ?? '',
    paterno: props.filters?.paterno ?? '',
    celular: props.filters?.celular ?? '',
})

// Debounce para no hacer petición en cada tecla
let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('conductores.index'),
                {
                    ci: val.ci || undefined,
                    nombres: val.nombres || undefined,
                    paterno: val.paterno || undefined,
                    celular: val.celular || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { ci: '', nombres: '', paterno: '', celular: '' }
}

// Confirmación de borrado
const confirmDelete = async (conductor) => {
    const confirmar = await confirm(`¿Eliminar al conductor ${conductor.nombres} ${conductor.paterno ?? ''}?`, "Confirmación", "Si, eliminar");
    if (!confirmar) return;

    router.delete(route('conductores.destroy', conductor.id),
        {
            _method: 'DELETE',
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast('Conductor eliminado')
            },
        },
    )

}

const estadoBadge = (estado) => {
    const map = {
        ACTIVO: 'bg-success-transparent text-success',
        INACTIVO: 'bg-warning-transparent text-warning',
        RETIRADO: 'bg-danger-transparent text-danger',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

const fotoUrl = (foto) =>
    foto ? `/storage/${foto}` : '/images/faces/1.jpg'


const cambiarEstado = async (conductorId, nuevoEstado) => {
    const confirmar = await confirm(`¿Desea cambiar el estado del conductor a <b>${nuevoEstado}</b>?`, "Confirmación", "Si, cambiar");
    if (!confirmar) return;

    router.put(route('conductores.update', conductorId),
        {
            _method: 'PUT',
            estado_conductor: nuevoEstado
        },
        {
            //prevenir scroll,
            preserveScroll: true,
            onSuccess: () => {
                showToast('Estado del conductor cambiado', 'success')
            },
            onError: (error)  => {
                showError(error);
            }
        })


}

/* ------------------------------------------------------------------ *
 * Modal: reasignar vehículo
 * ------------------------------------------------------------------ */
const asignacionModalEl = ref(null)
const asignacionModal = useBootstrapModal()

// Guardamos sólo el id: así el modal siempre refleja los datos frescos
// que llegan en `conductores` tras cada asignación/finalización.
const conductorAsignacionId = ref(null)
const conductorAsignacion = computed(() =>
    props.conductores.data.find((c) => c.id === conductorAsignacionId.value) ?? null
)
const asignacionActual = computed(() => conductorAsignacion.value?.asignaciones_activas?.[0] ?? null)

const asignacionForm = useForm({
    id_vehiculo:          null,
    estado_asignacion:    'ACTIVO',
    fecha_culminacion:    '',
    detalle:              '',
    kilometraje_inicial:  '',
    horometro_inicial:    '',
})

const tipoMedicionSeleccionado = ref(null)

function abrirAsignacion(conductor) {
    conductorAsignacionId.value = conductor.id
    asignacionForm.reset()
    asignacionForm.clearErrors()
    tipoMedicionSeleccionado.value = null

    nextTick(() => {
        asignacionModal.mostrar(asignacionModalEl.value)
    })
}

function onVehiculoSeleccionado(vehiculo) {
    tipoMedicionSeleccionado.value = vehiculo?.meta?.tipo_medicion ?? null
    asignacionForm.kilometraje_inicial = ''
    asignacionForm.horometro_inicial = ''
}

function onVehiculoLimpiado() {
    tipoMedicionSeleccionado.value = null
}

function submitAsignacion() {
    asignacionForm
        .transform((data) => ({
            ...data,
            id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
            fecha_culminacion: data.estado_asignacion === 'PROVISIONAL' ? (data.fecha_culminacion || null) : null,
        }))
        .post(route('conductores.asignaciones.asignar', conductorAsignacionId.value), {
            preserveScroll: true,
            onSuccess: () => {
                asignacionForm.reset()
                tipoMedicionSeleccionado.value = null
            },
        })
}

async function finalizarAsignacion(asignacion) {
    const confirmado = await confirm(
        `¿Finalizar la asignación del vehículo <strong>${asignacion.nro_placa}</strong>?`,
        'Finalizar Asignación',
        'Sí, finalizar',
    )

    if (!confirmado) {
        return
    }

    router.patch(
        route('conductores.asignaciones.finalizar', [conductorAsignacionId.value, asignacion.pivot.id]),
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

    <Head title="Conductores" />

        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Conductores</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Gestión de Conductores</h1>
            </div>
            <div>
                <Link v-can="'conductores.crear'" :href="route('conductores.create')" class="btn btn-primary btn-wave">
                    <i class="ri-add-line me-1"></i> Nuevo Conductor
                </Link>
            </div>
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
                        <label class="form-label">Carnet de Identidad</label>
                        <input v-model="filters.ci" type="text" class="form-control" placeholder="Buscar por CI..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Nombres</label>
                        <input v-model="filters.nombres" type="text" class="form-control"
                            placeholder="Buscar por nombres..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Apellido Paterno</label>
                        <input v-model="filters.paterno" type="text" class="form-control"
                            placeholder="Buscar por apellido..." />
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Celular</label>
                        <input v-model="filters.celular" type="text" class="form-control"
                            placeholder="Buscar por celular..." />
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
                    Conductores
                    <span class="badge bg-primary-transparent text-primary ms-2">
                        {{ conductores.total }} registros
                    </span>
                </div>
            </div>
            <!-- Vista tabla: desktop -->
            <div v-if="!isMobile" class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Foto</th>
                                <th>CI</th>
                                <th>Nombre Completo</th>
                                <th>Celular</th>
                                <th>Vehiculos Asignados</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="conductores.data.length === 0">
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="ri-user-search-line fs-3 d-block mb-2"></i>
                                    No se encontraron conductores
                                </td>
                            </tr>
                            <tr v-for="(conductor, index) in conductores.data" :key="conductor.id">
                                <td>{{ index + 1 }}</td>
                                <td>
                                    <span class="avatar avatar-lg">
                                        <img :src="fotoUrl(conductor.foto)" :alt="conductor.nombres"
                                            class="rounded-circle" style="width:36px;height:36px;object-fit:cover;" />
                                    </span>
                                </td>
                                <td><span class="fw-medium">{{ conductor.ci }}</span></td>
                                <td>{{ conductor.nombres }} {{ conductor.paterno ?? '—' }} {{ conductor.materno ?? '—'
                                }}</td>
                                <td> {{ conductor.celular ?? '—' }} </td>

                                <td>
                                    <ul>
                                        <li v-for="vehiculo in conductor.asignaciones_activas" :key="vehiculo.id">
                                            {{ vehiculo.nro_placa }} ({{ vehiculo.marca }})
                                        </li>

                                        <li v-if="conductor.asignaciones_activas.length === 0" class="text-danger">
                                            No asignado
                                        </li>
                                    </ul>
                                </td>
                                <td>
                                    <!-- <span class="badge btn " :class="estadoBadge(conductor.estado_conductor)"
                                        @click="cambiarEstado(conductor.id)">
                                        {{ conductor.estado_conductor }}
                                    </span> -->
                                    <div class="btn-list">
                                        <div class="btn-group">
                                            <button
                                                :class="`btn btn- ${estadoBadge(conductor.estado_conductor)} dropdown-toggle`"
                                                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                {{ conductor.estado_conductor }}
                                            </button>
                                            <ul :class="`dropdown-menu `">
                                                <li v-if="conductor.estado_conductor !== 'ACTIVO'"><a
                                                        @click="cambiarEstado(conductor.id, 'ACTIVO')"
                                                        class="dropdown-item" href="javascript:void(0);">Activo</a></li>
                                                <li v-if="conductor.estado_conductor !== 'INACTIVO'"><a
                                                        @click="cambiarEstado(conductor.id, 'INACTIVO')"
                                                        class="dropdown-item" href="javascript:void(0);">Inactivo</a>
                                                </li>
                                                <li v-if="conductor.estado_conductor !== 'RETIRADO'"><a
                                                        @click="cambiarEstado(conductor.id, 'RETIRADO')"
                                                        class="dropdown-item" href="javascript:void(0);">Retirado</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button
                                            v-can="'conductores.asignar-vehiculo'"
                                            type="button"
                                            class="btn btn-sm btn-icon btn-primary-light"
                                            title="Reasignar Vehículo"
                                            @click="abrirAsignacion(conductor)"
                                        >
                                            <i class="ri-exchange-line"></i>
                                        </button>
                                        <Link :href="route('conductores.show', conductor.id)"
                                            class="btn btn-sm btn-icon btn-light" title="Ver">
                                            <i class="ri-eye-line"></i>
                                        </Link>
                                        <Link v-can="'conductores.editar'" :href="route('conductores.edit', conductor.id)"
                                            class="btn btn-sm btn-icon btn-light" title="Editar">
                                            <i class="ri-edit-line"></i>
                                        </Link>
                                        <button v-can="'conductores.eliminar'" type="button" class="btn btn-sm btn-icon btn-light" title="Eliminar"
                                            @click="confirmDelete(conductor)">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Vista tarjetas: tablet y celular, con scroll infinito -->
            <div v-else class="card-body p-2">
                <div v-if="conductores.data.length === 0" class="text-center py-4 text-muted">
                    <i class="ri-user-search-line fs-3 d-block mb-2"></i>
                    No se encontraron conductores
                </div>

                <InfiniteScroll v-else data="conductores" only-next as="div" class="d-flex flex-column gap-2">
                    <div v-for="conductor in conductores.data" :key="conductor.id" class="list-card-mobile border rounded-3 p-3"
                        @click="router.get(route('conductores.show', conductor.id))">

                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="avatar avatar-md">
                                <img :src="fotoUrl(conductor.foto)" :alt="conductor.nombres" class="rounded-circle"
                                    style="width:36px;height:36px;object-fit:cover;" />
                            </span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ conductor.nombres }} {{ conductor.paterno ?? '' }} {{ conductor.materno ?? '' }}</div>
                                <small class="text-muted">CI: {{ conductor.ci }}</small>
                            </div>
                            <span class="badge" :class="estadoBadge(conductor.estado_conductor)">{{ conductor.estado_conductor }}</span>
                        </div>

                        <div class="row g-2 small mb-2">
                            <div class="col-6">
                                <span class="text-muted d-block">Celular</span>
                                <span>{{ conductor.celular ?? '—' }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Vehículo(s) asignado(s)</span>
                                <span v-if="conductor.asignaciones_activas.length === 0" class="text-danger">No asignado</span>
                                <span v-else>
                                    <span v-for="vehiculo in conductor.asignaciones_activas" :key="vehiculo.id" class="d-block">
                                        {{ vehiculo.nro_placa }} ({{ vehiculo.marca }})
                                    </span>
                                </span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-end gap-1 border-top pt-2" @click.stop>
                            <button v-can="'conductores.asignar-vehiculo'" type="button" class="btn btn-icon btn-primary-light"
                                title="Reasignar Vehículo" @click="abrirAsignacion(conductor)">
                                <i class="ri-exchange-line"></i>
                            </button>

                            <div class="dropdown">
                                <button type="button" class="btn btn-icon btn-light" data-bs-toggle="dropdown"
                                    aria-expanded="false" title="Más acciones">
                                    <i class="ri-more-2-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <Link class="dropdown-item" :href="route('conductores.show', conductor.id)">
                                            <i class="ri-eye-line me-2"></i> Ver
                                        </Link>
                                    </li>
                                    <li v-can="'conductores.editar'">
                                        <Link class="dropdown-item" :href="route('conductores.edit', conductor.id)">
                                            <i class="ri-edit-line me-2"></i> Editar
                                        </Link>
                                    </li>
                                    <li v-can="'conductores.eliminar'">
                                        <a class="dropdown-item text-danger" href="javascript:void(0);" @click="confirmDelete(conductor)">
                                            <i class="ri-delete-bin-line me-2"></i> Eliminar
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Indicador de carga / fin de lista del scroll infinito -->
                    <template #next="{ loading, hasMore }">
                        <div v-if="loading" class="text-center text-muted small py-2">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Cargando más conductores...
                        </div>
                        <div v-else-if="!hasMore" class="text-center text-muted small py-2">
                            No hay más conductores para mostrar.
                        </div>
                    </template>
                </InfiniteScroll>
            </div>

            <!-- Paginador: sólo la tabla desktop. El listado mobile usa
                 scroll infinito (InfiniteScroll arriba) en vez de páginas
                 numeradas. -->
            <div v-if="!isMobile" class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="text-muted small">
                    Mostrando {{ conductores.from ?? 0 }} - {{ conductores.to ?? 0 }}
                    de {{ conductores.total }} resultados
                </div>
                <nav v-if="conductores.last_page > 1">
                    <ul class="pagination pagination-sm mb-0">
                        <li v-for="link in conductores.links" :key="link.label" class="page-item"
                            :class="{ active: link.active, disabled: !link.url }">
                            <Link v-if="link.url" :href="link.url" class="page-link" preserve-state
                                v-html="link.label" />
                            <span v-else class="page-link" v-html="link.label" />
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- Modal: reasignar vehículo -->
        <div ref="asignacionModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-medium">
                            Vehículo asignado:
                            <span class="text-primary">
                                {{ conductorAsignacion?.nombres }} {{ conductorAsignacion?.paterno ?? '' }}
                            </span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <!-- Estado actual -->
                        <div class="card custom-card border mb-4">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span v-if="asignacionActual" class="badge" :class="tipoAsignacionBadge(asignacionActual.pivot.estado_asignacion)">
                                        {{ asignacionActual.pivot.estado_asignacion }}
                                    </span>
                                    <span v-else class="badge bg-secondary-transparent text-secondary">Sin vehículo</span>
                                    <button
                                        v-if="asignacionActual"
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        title="Finalizar asignación"
                                        @click="finalizarAsignacion(asignacionActual)"
                                    >
                                        <i class="ri-close-circle-line"></i>
                                    </button>
                                </div>
                                <div v-if="asignacionActual">
                                    <div class="fw-medium">
                                        {{ asignacionActual.codigo }} — {{ asignacionActual.nro_placa }}
                                        <span v-if="asignacionActual.marca" class="text-muted">({{ asignacionActual.marca }})</span>
                                    </div>
                                    <small class="text-muted d-block">Desde: {{ asignacionActual.pivot.fecha_asignacion }}</small>
                                    <small v-if="asignacionActual.pivot.fecha_culminacion" class="text-muted d-block">
                                        Hasta: {{ asignacionActual.pivot.fecha_culminacion }}
                                    </small>
                                    <small v-if="asignacionActual.pivot.detalle" class="text-muted d-block">
                                        Motivo: {{ asignacionActual.pivot.detalle }}
                                    </small>
                                </div>
                                <div v-else class="text-muted small">Este conductor no tiene un vehículo asignado</div>
                            </div>
                        </div>

                        <!-- Formulario de asignación -->
                        <form @submit.prevent="submitAsignacion">
                            <h6 class="fw-medium mb-3">Asignar / Reasignar Vehículo</h6>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Vehículo <span class="text-danger">*</span>
                                    </label>
                                    <SearchSelect
                                        v-model="asignacionForm.id_vehiculo"
                                        :object="true"
                                        :search-url="route('search.vehiculos')"
                                        placeholder="Buscar por placa, código o marca (mín. 2 caracteres)..."
                                        :invalid="!!asignacionForm.errors.id_vehiculo"
                                        @selected="onVehiculoSeleccionado"
                                        @cleared="onVehiculoLimpiado"
                                    />
                                    <InputError :message="asignacionForm.errors.id_vehiculo" class="mt-1" />
                                </div>

                                <div class="col-sm-6">
                                    <label class="form-label fw-medium">
                                        Tipo de Asignación <span class="text-danger">*</span>
                                    </label>
                                    <div class="d-flex gap-4 mt-1">
                                        <div class="form-check">
                                            <input
                                                id="asignacion_activo"
                                                v-model="asignacionForm.estado_asignacion"
                                                class="form-check-input"
                                                type="radio"
                                                value="ACTIVO"
                                            />
                                            <label for="asignacion_activo" class="form-check-label">ACTIVO</label>
                                        </div>
                                        <div class="form-check">
                                            <input
                                                id="asignacion_provisional"
                                                v-model="asignacionForm.estado_asignacion"
                                                class="form-check-input"
                                                type="radio"
                                                value="PROVISIONAL"
                                            />
                                            <label for="asignacion_provisional" class="form-check-label">PROVISIONAL</label>
                                        </div>
                                    </div>
                                    <InputError :message="asignacionForm.errors.estado_asignacion" class="mt-1" />
                                    <small class="text-muted">
                                        Provisional: cuando el encargado oficial está de permiso o ausencia justificada.
                                    </small>
                                </div>

                                <div v-if="asignacionForm.estado_asignacion === 'PROVISIONAL'" class="col-sm-6">
                                    <label class="form-label fw-medium">Fecha de Finalización (opcional)</label>
                                    <input
                                        v-model="asignacionForm.fecha_culminacion"
                                        type="date"
                                        class="form-control"
                                        :class="{ 'is-invalid': asignacionForm.errors.fecha_culminacion }"
                                    />
                                    <InputError :message="asignacionForm.errors.fecha_culminacion" class="mt-1" />
                                </div>

                                <div v-if="tipoMedicionSeleccionado === 'kilometraje'" class="col-sm-6">
                                    <label class="form-label fw-medium">
                                        Kilometraje Inicial <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input
                                            v-model="asignacionForm.kilometraje_inicial"
                                            type="number"
                                            min="0"
                                            class="form-control"
                                            :class="{ 'is-invalid': asignacionForm.errors.kilometraje_inicial }"
                                        />
                                        <span class="input-group-text">km</span>
                                    </div>
                                    <InputError :message="asignacionForm.errors.kilometraje_inicial" class="mt-1" />
                                </div>

                                <div v-if="tipoMedicionSeleccionado === 'horometro'" class="col-sm-6">
                                    <label class="form-label fw-medium">
                                        Horómetro Inicial <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input
                                            v-model="asignacionForm.horometro_inicial"
                                            type="number"
                                            min="0"
                                            class="form-control"
                                            :class="{ 'is-invalid': asignacionForm.errors.horometro_inicial }"
                                        />
                                        <span class="input-group-text">h</span>
                                    </div>
                                    <InputError :message="asignacionForm.errors.horometro_inicial" class="mt-1" />
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-medium">Motivo</label>
                                    <textarea
                                        v-model="asignacionForm.detalle"
                                        class="form-control"
                                        :class="{ 'is-invalid': asignacionForm.errors.detalle }"
                                        rows="2"
                                        placeholder="Ej: asignación inicial, reemplazo por mantenimiento..."
                                    ></textarea>
                                    <InputError :message="asignacionForm.errors.detalle" class="mt-1" />
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-3">
                                <button type="submit" class="btn btn-primary btn-wave" :disabled="asignacionForm.processing">
                                    <span v-if="asignacionForm.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    <i v-else class="ri-exchange-line me-1"></i>
                                    {{ asignacionForm.processing ? 'Asignando...' : 'Asignar' }}
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
