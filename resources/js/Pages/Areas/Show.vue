<script setup>
import { ref, computed, nextTick } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import { confirm as confirmSwal } from '@/Utils/alertUtil.js'
import { useBootstrapModal } from '@/Composables/useBootstrapModal'

const props = defineProps({
    area: Object,
    resumen: Object,
    encargados: { type: Array, default: () => [] },
    vehiculos: { type: Array, default: () => [] },
    historialVehiculos: { type: Array, default: () => [] },
    areasDisponibles: { type: Array, default: () => [] },
})

const titular = computed(
    () => props.encargados.find((e) => e.tipo_encargo === 'TITULAR' && e.vigente) ?? null,
)
const suplente = computed(
    () => props.encargados.find((e) => e.tipo_encargo === 'SUPLENTE' && e.vigente) ?? null,
)
const encargadosHistoricos = computed(() => props.encargados.filter((e) => !e.vigente))

const estadoAreaBadge = (estado) =>
    estado === 'ACTIVO' ? 'bg-success-transparent text-success' : 'bg-danger-transparent text-danger'

const estadoVehiculoBadge = (estado) =>
    ({
        ACTIVO: 'bg-success-transparent text-success',
        RETIRADO: 'bg-warning-transparent text-warning',
        VENDIDO: 'bg-secondary-transparent text-secondary',
    })[estado] ?? 'bg-secondary-transparent text-secondary'

const estadoAsignacionBadge = (estado) =>
    ({
        ACTIVO: 'bg-success-transparent text-success',
        PROVISIONAL: 'bg-info-transparent text-info',
        REASIGNADO: 'bg-warning-transparent text-warning',
        CULMINADO: 'bg-secondary-transparent text-secondary',
    })[estado] ?? 'bg-secondary-transparent text-secondary'

const encargoBadge = (tipo) =>
    tipo === 'TITULAR' ? 'bg-primary-transparent text-primary' : 'bg-info-transparent text-info'

const tipoMedicionLabel = (tipo) => (tipo === 'horometro' ? 'Horómetro' : 'Kilometraje')

const nombreVehiculo = (v) => [v.marca, v.modelo].filter(Boolean).join(' ') || '—'
const codigoPlaca = (v) => [v.codigo, v.nro_placa].filter(Boolean).join(' · ') || '—'

/* ------------------------------------------------------------------ *
 * Modal: cambiar asignación del vehículo (reasignar / finalizar)
 * ------------------------------------------------------------------ */
const asignacionModalEl = ref(null)
const asignacionModal = useBootstrapModal()

// Guardamos sólo el id: el modal siempre refleja los datos frescos que
// llegan en `vehiculos` tras cada cambio.
const vehiculoSeleccionadoId = ref(null)
const vehiculoSeleccionado = computed(
    () => props.vehiculos.find((v) => v.id === vehiculoSeleccionadoId.value) ?? null,
)

const asignacionForm = useForm({
    id_area: '',
    estado_asignacion: 'ACTIVO',
    fecha_culminacion: '',
    motivo_asignacion: '',
})

function abrirCambiarAsignacion(v) {
    vehiculoSeleccionadoId.value = v.id
    asignacionForm.reset()
    asignacionForm.clearErrors()
    // Preselecciona el área actual: reasignar a la misma área sólo cambia
    // el tipo / motivo; elegir otra área mueve el vehículo.
    asignacionForm.id_area = props.area.id

    nextTick(() => {
        asignacionModal.mostrar(asignacionModalEl.value)
    })
}

function submitCambiarAsignacion() {
    asignacionForm
        .transform((data) => ({
            ...data,
            fecha_culminacion:
                data.estado_asignacion === 'PROVISIONAL' ? data.fecha_culminacion || null : null,
        }))
        .post(route('areas.vehiculos.reasignar', [props.area.id, vehiculoSeleccionadoId.value]), {
            preserveScroll: true,
            onSuccess: () => asignacionForm.reset(),
        })
}

async function finalizarAsignacion(v) {
    const confirmado = await confirmSwal(
        `¿Finalizar la asignación de <strong>${nombreVehiculo(v)}</strong> a esta área?`,
        'Finalizar Asignación',
        'Sí, finalizar',
    )

    if (!confirmado) {
        return
    }

    router.patch(
        route('areas.vehiculos.finalizar', [props.area.id, v.id_asignacion]),
        {},
        { preserveScroll: true },
    )
}
</script>

<template>
    <Head :title="`Área: ${area.nombre_area}`" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item">
                        <Link :href="route('areas.index')">Áreas</Link>
                    </li>
                    <li class="breadcrumb-item active">Ver detalles</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                Área: <span class="text-primary">{{ area.nombre_area }}</span>
            </h1>
        </div>
        <Link :href="route('areas.index')" class="btn btn-outline-secondary btn-wave">
            <i class="ri-arrow-left-line me-1"></i> Volver
        </Link>
    </div>

    <div class="row g-4 mb-4">
        <!-- Datos del Área -->
        <div class="col-xl-4">
            <div class="card custom-card h-100">
                <div class="card-header justify-content-between">
                    <div class="card-title">Datos del Área</div>
                    <span class="badge" :class="estadoAreaBadge(area.estado_area)">{{ area.estado_area }}</span>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="avatar avatar-md bg-primary-transparent text-primary">
                            <i class="ri-building-line fs-18"></i>
                        </span>
                        <span class="fs-15 fw-semibold">{{ area.nombre_area }}</span>
                    </div>

                    <ul class="list-unstyled mb-0">
                        <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                            <span class="text-muted fs-13">Vehículos asignados</span>
                            <span class="badge bg-primary-transparent text-primary">{{ resumen.vehiculos_vigentes }}</span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                            <span class="text-muted fs-13">Encargados vigentes</span>
                            <span class="badge bg-primary-transparent text-primary">{{ resumen.encargados_vigentes }}</span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                            <span class="text-muted fs-13">Asignaciones históricas</span>
                            <span class="fw-medium">{{ resumen.vehiculos_historico }}</span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between gap-2 py-1">
                            <span class="text-muted fs-13">Registrada el</span>
                            <span class="fw-medium">{{ area.created_at ?? '—' }}</span>
                        </li>
                    </ul>

                    <div class="mt-3">
                        <span class="text-muted fs-13 d-block mb-1">Descripción</span>
                        <p class="fs-13 mb-0">{{ area.descripcion_area || 'Sin descripción.' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Encargados vigentes -->
        <div class="col-xl-8">
            <div class="card custom-card h-100">
                <div class="card-header">
                    <div class="card-title">Encargados</div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <span class="badge bg-primary-transparent text-primary mb-2">TITULAR</span>
                                <div v-if="titular">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="avatar avatar-sm avatar-rounded bg-primary-transparent text-primary d-flex align-items-center justify-content-center">
                                            <img v-if="titular.foto_url" :src="titular.foto_url" :alt="titular.nombre_completo"
                                                class="rounded-circle" style="width: 100%; height: 100%; object-fit: cover" />
                                            <i v-else class="ri-user-3-line"></i>
                                        </span>
                                        <span class="fw-medium">{{ titular.nombre_completo }}</span>
                                    </div>
                                    <small class="text-muted d-block">CI: {{ titular.ci ?? '—' }}</small>
                                    <small class="text-muted d-block">Celular: {{ titular.celular ?? '—' }}</small>
                                    <small class="text-muted d-block">Desde: {{ titular.fecha_inicio ?? '—' }}</small>
                                    <small v-if="titular.fecha_fin" class="text-muted d-block">Hasta: {{ titular.fecha_fin }}</small>
                                </div>
                                <div v-else class="text-muted small">Sin titular asignado</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100">
                                <span class="badge bg-info-transparent text-info mb-2">SUPLENTE</span>
                                <div v-if="suplente">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="avatar avatar-sm avatar-rounded bg-info-transparent text-info d-flex align-items-center justify-content-center">
                                            <img v-if="suplente.foto_url" :src="suplente.foto_url" :alt="suplente.nombre_completo"
                                                class="rounded-circle" style="width: 100%; height: 100%; object-fit: cover" />
                                            <i v-else class="ri-user-3-line"></i>
                                        </span>
                                        <span class="fw-medium">{{ suplente.nombre_completo }}</span>
                                    </div>
                                    <small class="text-muted d-block">CI: {{ suplente.ci ?? '—' }}</small>
                                    <small class="text-muted d-block">Celular: {{ suplente.celular ?? '—' }}</small>
                                    <small class="text-muted d-block">Desde: {{ suplente.fecha_inicio ?? '—' }}</small>
                                    <small v-if="suplente.fecha_fin" class="text-muted d-block">Hasta: {{ suplente.fecha_fin }}</small>
                                </div>
                                <div v-else class="text-muted small">Sin suplente asignado</div>
                            </div>
                        </div>
                    </div>

                    <!-- Histórico de encargos -->
                    <div v-if="encargadosHistoricos.length" class="mt-3">
                        <span class="text-muted fs-13 d-block mb-2">Encargos anteriores</span>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Persona</th>
                                        <th>Tipo</th>
                                        <th>Desde</th>
                                        <th>Hasta</th>
                                        <th>Motivo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="e in encargadosHistoricos" :key="e.id">
                                        <td class="fw-medium">{{ e.nombre_completo }}</td>
                                        <td>
                                            <span class="badge" :class="encargoBadge(e.tipo_encargo)">{{ e.tipo_encargo }}</span>
                                        </td>
                                        <td>{{ e.fecha_inicio ?? '—' }}</td>
                                        <td>{{ e.fecha_fin ?? e.fecha_reasignacion ?? '—' }}</td>
                                        <td style="min-width: 160px">{{ e.motivo || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vehículos Asignados (vigentes) -->
    <div class="card custom-card overflow-hidden mb-4">
        <div class="card-header justify-content-between">
            <div class="card-title">
                <h6 class="mb-0">Vehículos Asignados</h6>
            </div>
            <span class="badge bg-primary-transparent text-primary">{{ vehiculos.length }} vigente(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 48px">#</th>
                            <th>Vehículo</th>
                            <th>Tipo</th>
                            <th>Combustible</th>
                            <th>Conductor Actual</th>
                            <th>Asignación</th>
                            <th>Desde</th>
                            <th>Culminación</th>
                            <th class="text-center" style="width: 96px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(v, index) in vehiculos" :key="v.id">
                            <td class="text-muted">{{ index + 1 }}</td>
                            <td style="min-width: 200px">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar-sm rounded d-flex align-items-center justify-content-center"
                                        style="background: #f8f9fa">
                                        <img v-if="v.url_fotografia" :src="v.url_fotografia" :alt="v.nro_placa"
                                            class="rounded" style="width: 100%; height: 100%; object-fit: cover" />
                                        <i v-else class="ri-car-line text-muted"></i>
                                    </span>
                                    <div>
                                        <span class="fw-semibold d-block">{{ nombreVehiculo(v) }}</span>
                                        <small class="text-muted d-block">{{ codigoPlaca(v) }}</small>
                                        <span class="badge" :class="estadoVehiculoBadge(v.estado_vehiculo)">
                                            {{ v.estado_vehiculo }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ v.tipo_vehiculo ?? '—' }}</td>
                            <td>
                                <span class="badge bg-info-transparent text-info">{{ v.tipo_combustible ?? '—' }}</span>
                            </td>
                            <td>
                                <span v-if="v.conductor" class="fw-medium">
                                    <i class="ri-user-line me-1"></i>{{ v.conductor.nombre_completo }}
                                </span>
                                <span v-else class="text-muted">Sin conductor</span>
                            </td>
                            <td>
                                <span class="badge" :class="estadoAsignacionBadge(v.estado_asignacion)">
                                    {{ v.estado_asignacion }}
                                </span>
                            </td>
                            <td>{{ v.fecha_asignacion ?? '—' }}</td>
                            <td>{{ v.fecha_culminacion ?? 'Indefinida' }}</td>
                            <td>
                                <div class="d-flex gap-1 justify-content-center">
                                    <Link :href="route('vehiculos.show', v.id)"
                                        class="btn btn-sm btn-icon btn-primary-light" title="Ver vehículo">
                                        <i class="ri-eye-line"></i>
                                    </Link>
                                    <button v-can="'vehiculos.asignar-area'" type="button"
                                        class="btn btn-sm btn-icon btn-light" title="Cambiar asignación"
                                        @click="abrirCambiarAsignacion(v)">
                                        <i class="ri-exchange-line"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="vehiculos.length === 0">
                            <td colspan="9" class="p-5 text-center text-muted">
                                <i class="ri-car-line fs-3 d-block mb-2"></i>
                                Esta área no tiene vehículos asignados actualmente.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Historial de Asignaciones de Vehículos -->
    <div class="card custom-card overflow-hidden">
        <div class="card-header justify-content-between">
            <div class="card-title">
                <h6 class="mb-0">Historial de Asignaciones de Vehículos</h6>
            </div>
            <span class="badge bg-primary-transparent text-primary">{{ historialVehiculos.length }} registro(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 48px">#</th>
                            <th>Vehículo</th>
                            <th>Estado</th>
                            <th>Asignación</th>
                            <th>Reasignación</th>
                            <th>Culminación</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(h, index) in historialVehiculos" :key="h.id">
                            <td class="text-muted">{{ index + 1 }}</td>
                            <td style="min-width: 180px">
                                <span class="fw-semibold d-block">{{ nombreVehiculo(h) }}</span>
                                <small class="text-muted">{{ codigoPlaca(h) }}</small>
                            </td>
                            <td>
                                <span class="badge" :class="estadoAsignacionBadge(h.estado_asignacion)">
                                    {{ h.estado_asignacion }}
                                </span>
                            </td>
                            <td>{{ h.fecha_asignacion ?? '—' }}</td>
                            <td>{{ h.fecha_reasignacion ?? '—' }}</td>
                            <td>{{ h.fecha_culminacion ?? '—' }}</td>
                            <td style="min-width: 200px">{{ h.motivo_asignacion || '—' }}</td>
                        </tr>

                        <tr v-if="historialVehiculos.length === 0">
                            <td colspan="7" class="p-5 text-center text-muted">
                                No hay historial de asignaciones de vehículos para esta área.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: cambiar asignación del vehículo -->
    <div ref="asignacionModalEl" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-medium">
                        Cambiar asignación:
                        <span class="text-primary">
                            {{ vehiculoSeleccionado ? nombreVehiculo(vehiculoSeleccionado) : '' }}
                        </span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Asignación actual (a esta área) -->
                    <div v-if="vehiculoSeleccionado" class="card custom-card border mb-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="badge" :class="estadoAsignacionBadge(vehiculoSeleccionado.estado_asignacion)">
                                    {{ vehiculoSeleccionado.estado_asignacion }}
                                </span>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                    title="Finalizar asignación" @click="finalizarAsignacion(vehiculoSeleccionado)">
                                    <i class="ri-close-circle-line me-1"></i> Finalizar
                                </button>
                            </div>
                            <div class="fw-medium">{{ area.nombre_area }}</div>
                            <small class="text-muted d-block">
                                Vehículo: {{ codigoPlaca(vehiculoSeleccionado) }}
                            </small>
                            <small class="text-muted d-block">Desde: {{ vehiculoSeleccionado.fecha_asignacion ?? '—' }}</small>
                            <small class="text-muted d-block">
                                Hasta: {{ vehiculoSeleccionado.fecha_culminacion ?? 'Indefinida' }}
                            </small>
                            <small v-if="vehiculoSeleccionado.motivo_asignacion" class="text-muted d-block">
                                Motivo: {{ vehiculoSeleccionado.motivo_asignacion }}
                            </small>
                        </div>
                    </div>

                    <!-- Formulario de reasignación -->
                    <form @submit.prevent="submitCambiarAsignacion">
                        <h6 class="fw-medium mb-3">Reasignar</h6>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">
                                    Área <span class="text-danger">*</span>
                                </label>
                                <select v-model="asignacionForm.id_area" class="form-select"
                                    :class="{ 'is-invalid': asignacionForm.errors.id_area }">
                                    <option value="">— Seleccionar —</option>
                                    <option v-for="a in areasDisponibles" :key="a.id" :value="a.id">
                                        {{ a.nombre_area }}
                                    </option>
                                </select>
                                <InputError :message="asignacionForm.errors.id_area" class="mt-1" />
                                <small class="text-muted">Elegí otra área para mover el vehículo.</small>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label fw-medium">
                                    Tipo de Asignación <span class="text-danger">*</span>
                                </label>
                                <div class="d-flex gap-4 mt-1">
                                    <div class="form-check">
                                        <input id="area_asig_activo" v-model="asignacionForm.estado_asignacion"
                                            class="form-check-input" type="radio" value="ACTIVO" />
                                        <label for="area_asig_activo" class="form-check-label">ACTIVO</label>
                                    </div>
                                    <div class="form-check">
                                        <input id="area_asig_provisional" v-model="asignacionForm.estado_asignacion"
                                            class="form-check-input" type="radio" value="PROVISIONAL" />
                                        <label for="area_asig_provisional" class="form-check-label">PROVISIONAL</label>
                                    </div>
                                </div>
                                <InputError :message="asignacionForm.errors.estado_asignacion" class="mt-1" />
                                <small class="text-muted">Provisional: préstamo temporal a otra área.</small>
                            </div>

                            <div v-if="asignacionForm.estado_asignacion === 'PROVISIONAL'" class="col-sm-6">
                                <label class="form-label fw-medium">Fecha de Culminación (opcional)</label>
                                <input v-model="asignacionForm.fecha_culminacion" type="date" class="form-control"
                                    :class="{ 'is-invalid': asignacionForm.errors.fecha_culminacion }" />
                                <InputError :message="asignacionForm.errors.fecha_culminacion" class="mt-1" />
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-medium">Motivo</label>
                                <textarea v-model="asignacionForm.motivo_asignacion" class="form-control"
                                    :class="{ 'is-invalid': asignacionForm.errors.motivo_asignacion }" rows="2"
                                    placeholder="Ej: préstamo por campaña, reasignación permanente..."></textarea>
                                <InputError :message="asignacionForm.errors.motivo_asignacion" class="mt-1" />
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary btn-wave" :disabled="asignacionForm.processing">
                                <span v-if="asignacionForm.processing" class="spinner-border spinner-border-sm me-1"
                                    role="status"></span>
                                <i v-else class="ri-exchange-line me-1"></i>
                                {{ asignacionForm.processing ? 'Guardando...' : 'Guardar cambio' }}
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
