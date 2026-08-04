<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
    valeId: { type: Number, default: null },
})

const emit = defineEmits(['close'])

const loading = ref(false)
const error = ref(null)
const vale = ref(null)

watch(
    () => props.valeId,
    async (id) => {
        if (!id) return
        loading.value = true
        error.value = null
        vale.value = null
        try {
            const { data } = await axios.get(route('vales.detalle', id))
            vale.value = data
        } catch (e) {
            error.value = 'No se pudieron cargar los detalles del vale.'
        } finally {
            loading.value = false
        }
    },
)

const estadoBadgeClass = (estado) => {
    const map = {
        PENDIENTE: 'bg-warning text-dark',
        USADO: 'bg-success text-white',
        ANULADO: 'bg-danger text-white',
    }
    return map[estado] ?? 'bg-secondary text-white'
}

const fmt = (val, decimals = 2) =>
    val !== null && val !== undefined ? Number(val).toFixed(decimals) : '—'
</script>

<template>
    <!-- Backdrop -->
    <teleport to="body">
        <div
            v-if="valeId"
            class="modal fade show d-block"
            tabindex="-1"
            style="background: rgba(0,0,0,.5);"
            @click.self="emit('close')"
        >
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">

                    <!-- Header -->
                    <div class="modal-header bg-primary text-white">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ri-file-list-3-line fs-4"></i>
                            <div>
                                <h5 class="modal-title mb-0 fw-semibold">
                                    Detalle del Vale
                                    <span v-if="vale" class="ms-2 fs-14 opacity-75">#{{ vale.nro }}</span>
                                </h5>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" @click="emit('close')"></button>
                    </div>

                    <!-- Body -->
                    <div class="modal-body p-4">

                        <!-- Loading -->
                        <div v-if="loading" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="text-muted mt-2 mb-0">Cargando detalles…</p>
                        </div>

                        <!-- Error -->
                        <div v-else-if="error" class="alert alert-danger">
                            <i class="ri-error-warning-line me-2"></i>{{ error }}
                        </div>

                        <!-- Contenido -->
                        <template v-else-if="vale">

                            <!-- Estado + tipo combustible -->
                            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge fs-13 px-3 py-2" :class="estadoBadgeClass(vale.estado_vale)">
                                        {{ vale.estado_vale }}
                                    </span>
                                    <span v-if="vale.tipo_combustible" class="badge bg-info-transparent text-info fs-12 px-3 py-2">
                                        <i class="ri-gas-station-line me-1"></i>{{ vale.tipo_combustible }}
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <i class="ri-calendar-line me-1"></i>{{ vale.fecha_emision }}
                                </small>
                            </div>

                            <!-- Fila superior: Vehículo / Conductor / Grifo -->
                            <div class="row g-3 mb-4">
                                <!-- Vehículo -->
                                <div class="col-md-4">
                                    <div class="card border h-100">
                                        <div class="card-body p-3">
                                            <p class="text-muted fs-11 text-uppercase fw-semibold mb-2">
                                                <i class="ri-car-line me-1"></i>Vehículo
                                            </p>
                                            <template v-if="vale.vehiculo">
                                                <p class="fw-bold fs-15 mb-1">{{ vale.vehiculo.nro_placa }}</p>
                                                <p class="text-muted mb-1 fs-13">{{ vale.vehiculo.marca ?? '—' }}</p>
                                                <p class="text-muted mb-0 fs-12">
                                                    <span v-if="vale.vehiculo.modelo">{{ vale.vehiculo.modelo }}</span>
                                                    <span v-if="vale.vehiculo.anio"> · {{ vale.vehiculo.anio }}</span>
                                                </p>
                                            </template>
                                            <p v-else class="text-muted mb-0">—</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Conductor -->
                                <div class="col-md-4">
                                    <div class="card border h-100">
                                        <div class="card-body p-3">
                                            <p class="text-muted fs-11 text-uppercase fw-semibold mb-2">
                                                <i class="ri-user-line me-1"></i>Conductor
                                            </p>
                                            <template v-if="vale.conductor">
                                                <p class="fw-semibold fs-14 mb-1">{{ vale.conductor.nombre_completo }}</p>
                                                <p class="text-muted mb-0 fs-13">CI: {{ vale.conductor.ci }}</p>
                                            </template>
                                            <p v-else class="text-muted mb-0">—</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Grifo -->
                                <div class="col-md-4">
                                    <div class="card border h-100">
                                        <div class="card-body p-3">
                                            <p class="text-muted fs-11 text-uppercase fw-semibold mb-2">
                                                <i class="ri-store-2-line me-1"></i>Grifo
                                            </p>
                                            <template v-if="vale.grifo">
                                                <p class="fw-semibold fs-14 mb-1">{{ vale.grifo.razon_social }}</p>
                                                <p class="text-muted mb-1 fs-13">{{ vale.grifo.ciudad ?? '—' }}</p>
                                                <p class="text-muted mb-0 fs-12">NIT: {{ vale.grifo.nit ?? '—' }}</p>
                                            </template>
                                            <p v-else class="text-muted mb-0">—</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Importes del vale -->
                            <div class="card border mb-4">
                                <div class="card-header py-2 px-3">
                                    <span class="fw-semibold fs-13 text-muted text-uppercase">
                                        <i class="ri-money-dollar-circle-line me-1"></i>Importes del Vale
                                    </span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row g-0 text-center">
                                        <div class="col-4 border-end py-3">
                                            <p class="text-muted fs-11 text-uppercase mb-1">Litros autorizados</p>
                                            <p class="fw-bold fs-20 mb-0 text-primary">{{ fmt(vale.litros) }}</p>
                                            <small class="text-muted">lts</small>
                                        </div>
                                        <div class="col-4 border-end py-3">
                                            <p class="text-muted fs-11 text-uppercase mb-1">Precio unitario</p>
                                            <p class="fw-bold fs-20 mb-0 text-info">{{ fmt(vale.precio) }}</p>
                                            <small class="text-muted">Bs/lt</small>
                                        </div>
                                        <div class="col-4 py-3">
                                            <p class="text-muted fs-11 text-uppercase mb-1">Total</p>
                                            <p class="fw-bold fs-20 mb-0 text-success">{{ fmt(vale.total) }}</p>
                                            <small class="text-muted">Bs</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Detalle de carga (si fue USADO) -->
                            <template v-if="vale.estado_vale === 'USADO' && vale.carga">
                                <div class="card border border-success mb-3">
                                    <div class="card-header bg-success-transparent py-2 px-3">
                                        <span class="fw-semibold fs-13 text-success text-uppercase">
                                            <i class="ri-gas-station-fill me-1"></i>Datos de la Carga Realizada
                                        </span>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="row g-3">
                                            <div class="col-sm-6 col-md-3">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Fecha de carga</p>
                                                <p class="fw-semibold mb-0">{{ vale.carga.fecha_carga ?? '—' }}</p>
                                            </div>
                                            <div class="col-sm-6 col-md-3">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Nro. Factura</p>
                                                <p class="fw-semibold mb-0">{{ vale.carga.nro_factura ?? '—' }}</p>
                                            </div>
                                            <div class="col-sm-6 col-md-3">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Tipo carga</p>
                                                <p class="fw-semibold mb-0">{{ vale.carga.tipo_carga ?? '—' }}</p>
                                            </div>
                                            <div class="col-sm-6 col-md-3">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Estado carga</p>
                                                <p class="fw-semibold mb-0">{{ vale.carga.estado_carga ?? '—' }}</p>
                                            </div>
                                        </div>

                                        <!-- Importes de la carga -->
                                        <div class="row g-0 text-center border rounded mt-3">
                                            <div class="col-4 border-end py-2">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Litros cargados</p>
                                                <p class="fw-bold fs-18 mb-0 text-primary">{{ fmt(vale.carga.litros) }}</p>
                                                <small class="text-muted">lts</small>
                                            </div>
                                            <div class="col-4 border-end py-2">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Precio unitario</p>
                                                <p class="fw-bold fs-18 mb-0 text-info">{{ fmt(vale.carga.precio) }}</p>
                                                <small class="text-muted">Bs/lt</small>
                                            </div>
                                            <div class="col-4 py-2">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Total carga</p>
                                                <p class="fw-bold fs-18 mb-0 text-success">{{ fmt(vale.carga.total) }}</p>
                                                <small class="text-muted">Bs</small>
                                            </div>
                                        </div>

                                        <!-- Kilometraje / Horómetro -->
                                        <div v-if="vale.carga.kilometraje || vale.carga.horometro" class="row g-3 mt-1">
                                            <div v-if="vale.carga.kilometraje" class="col-sm-6">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Kilometraje</p>
                                                <p class="fw-semibold mb-0">{{ fmt(vale.carga.kilometraje, 2) }} km</p>
                                            </div>
                                            <div v-if="vale.carga.horometro" class="col-sm-6">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Horómetro</p>
                                                <p class="fw-semibold mb-0">{{ fmt(vale.carga.horometro, 2) }} hr</p>
                                            </div>
                                        </div>

                                        <!-- Grifo de la carga (si difiere) -->
                                        <div v-if="vale.carga.grifo" class="mt-3 pt-3 border-top">
                                            <p class="text-muted fs-11 text-uppercase mb-1">Grifo de la carga</p>
                                            <p class="fw-semibold mb-0">
                                                {{ vale.carga.grifo.razon_social }}
                                                <span v-if="vale.carga.grifo.ciudad" class="text-muted fw-normal"> — {{ vale.carga.grifo.ciudad }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Vale USADO pero sin carga registrada (caso inconsistente) -->
                            <div
                                v-else-if="vale.estado_vale === 'USADO' && !vale.carga"
                                class="alert alert-warning mb-3"
                            >
                                <i class="ri-information-line me-2"></i>
                                El vale figura como USADO pero no tiene una carga de combustible asociada.
                            </div>

                            <!-- Vale PENDIENTE -->
                            <div v-else-if="vale.estado_vale === 'PENDIENTE'" class="alert alert-warning-transparent mb-3">
                                <i class="ri-time-line me-2"></i>
                                Este vale aún no ha sido utilizado.
                            </div>

                            <!-- Vale ANULADO -->
                            <div v-else-if="vale.estado_vale === 'ANULADO'" class="alert alert-danger-transparent mb-3">
                                <i class="ri-close-circle-line me-2"></i>
                                Este vale ha sido anulado.
                            </div>

                            <!-- Registrado por -->
                            <div v-if="vale.registrado_por" class="text-end">
                                <small class="text-muted">
                                    <i class="ri-user-settings-line me-1"></i>Registrado por: <span class="fw-medium">{{ vale.registrado_por }}</span>
                                </small>
                            </div>

                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer">
                        <a
                            v-if="vale"
                            :href="route('vales.imprimir', vale.id)"
                            target="_blank"
                            class="btn btn-outline-warning btn-wave"
                        >
                            <i class="ri-printer-line me-1"></i> Imprimir
                        </a>
                        <button type="button" class="btn btn-secondary btn-wave" @click="emit('close')">
                            <i class="ri-close-line me-1"></i> Cerrar
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </teleport>
</template>
