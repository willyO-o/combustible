<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

import { formatDate, getExpirationStatus } from '@/Utils/dateUtil'

const props = defineProps({
    cargaId: { type: Number, default: null },
})

const emit = defineEmits(['close'])

const loading = ref(false)
const error = ref(null)
const carga = ref(null)

watch(
    () => props.cargaId,
    async (id) => {
        if (!id) return
        loading.value = true
        error.value = null
        carga.value = null
        try {
            const { data } = await axios.get(route('cargas.detalle', id))
            carga.value = data
        } catch (e) {
            error.value = 'No se pudieron cargar los detalles de la carga.'
        } finally {
            loading.value = false
        }
    },
)

const estadoBadgeClass = (estado) => {
    const map = {
        REGISTRADO: 'bg-info text-white',
        VERIFICADO: 'bg-success text-white',
        ANULADO: 'bg-danger text-white',
    }
    return map[estado] ?? 'bg-secondary text-white'
}

const tipoBadgeClass = (tipo) =>
    tipo === 'VALE' ? 'bg-primary text-white' : 'bg-warning text-dark'

const valeEstadoBadgeClass = (estado) => {
    const map = {
        PENDIENTE: 'bg-warning text-dark',
        USADO: 'bg-success text-white',
        ANULADO: 'bg-danger text-white',
    }
    return map[estado] ?? 'bg-secondary text-white'
}

const respaldoUrl = (ruta) => `/storage/${ruta}`

const fmt = (val, decimals = 2) =>
    val !== null && val !== undefined ? Number(val).toFixed(decimals) : '—'
</script>

<template>
    <!-- Backdrop -->
    <teleport to="body">
        <div
            v-if="cargaId"
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
                            <i class="ri-gas-station-fill fs-4"></i>
                            <div>
                                <h5 class="modal-title mb-0 fw-semibold">
                                    Detalle de Carga de Combustible
                                    <span v-if="carga" class="ms-2 fs-14 opacity-75">#{{ carga.id }}</span>
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
                        <template v-else-if="carga">

                            <!-- Estado + tipo + fecha -->
                            <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span v-if="carga.estado_carga" class="badge fs-13 px-3 py-2" :class="estadoBadgeClass(carga.estado_carga)">
                                        {{ carga.estado_carga }}
                                    </span>
                                    <span class="badge fs-12 px-3 py-2" :class="tipoBadgeClass(carga.tipo_carga)">
                                        {{ carga.tipo_carga }}
                                    </span>
                                    <span v-if="carga.tipo_combustible" class="badge bg-info-transparent text-info fs-12 px-3 py-2">
                                        <i class="ri-gas-station-line me-1"></i>{{ carga.tipo_combustible }}
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <i class="ri-calendar-line me-1"></i>{{ carga.fecha_carga }}
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
                                            <template v-if="carga.vehiculo">
                                                <p class="fw-bold fs-15 mb-1">{{ carga.vehiculo.nro_placa }}</p>
                                                <p class="text-muted mb-1 fs-13">{{ carga.vehiculo.codigo ?? '—' }} · {{ carga.vehiculo.marca ?? '—' }}</p>
                                                <p class="text-muted mb-0 fs-12">
                                                    <span v-if="carga.vehiculo.modelo">{{ carga.vehiculo.modelo }}</span>
                                                    <span v-if="carga.vehiculo.anio"> · {{ carga.vehiculo.anio }}</span>
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
                                            <template v-if="carga.conductor">
                                                <p class="fw-semibold fs-14 mb-1">{{ carga.conductor.nombre_completo }}</p>
                                                <p class="text-muted mb-0 fs-13">CI: {{ carga.conductor.ci }}</p>
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
                                            <template v-if="carga.grifo">
                                                <p class="fw-semibold fs-14 mb-1">{{ carga.grifo.razon_social }}</p>
                                                <p class="text-muted mb-1 fs-13">{{ carga.grifo.ciudad ?? '—' }}</p>
                                                <p class="text-muted mb-0 fs-12">NIT: {{ carga.grifo.nit ?? '—' }}</p>
                                            </template>
                                            <p v-else class="text-muted mb-0">—</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Importes de la carga -->
                            <div class="card border mb-4">
                                <div class="card-header py-2 px-3">
                                    <span class="fw-semibold fs-13 text-muted text-uppercase">
                                        <i class="ri-money-dollar-circle-line me-1"></i>Importes de la Carga
                                    </span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row g-0 text-center">
                                        <div class="col-4 border-end py-3">
                                            <p class="text-muted fs-11 text-uppercase mb-1">Litros cargados</p>
                                            <p class="fw-bold fs-20 mb-0 text-primary">{{ fmt(carga.litros) }}</p>
                                            <small class="text-muted">lts</small>
                                        </div>
                                        <div class="col-4 border-end py-3">
                                            <p class="text-muted fs-11 text-uppercase mb-1">Precio unitario</p>
                                            <p class="fw-bold fs-20 mb-0 text-info">{{ fmt(carga.precio) }}</p>
                                            <small class="text-muted">Bs/lt</small>
                                        </div>
                                        <div class="col-4 py-3">
                                            <p class="text-muted fs-11 text-uppercase mb-1">Total</p>
                                            <p class="fw-bold fs-20 mb-0 text-success">{{ fmt(carga.total) }}</p>
                                            <small class="text-muted">Bs</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Datos adicionales: factura / kilometraje / horómetro -->
                            <div v-if="carga.nro_factura || carga.kilometraje || carga.horometro" class="row g-3 mb-4">
                                <div v-if="carga.nro_factura" class="col-sm-4">
                                    <p class="text-muted fs-11 text-uppercase mb-1">Nro. Factura</p>
                                    <p class="fw-semibold mb-0">{{ carga.nro_factura }}</p>
                                </div>
                                <div v-if="carga.kilometraje" class="col-sm-4">
                                    <p class="text-muted fs-11 text-uppercase mb-1">Kilometraje</p>
                                    <p class="fw-semibold mb-0">{{ fmt(carga.kilometraje, 2) }} km</p>
                                </div>
                                <div v-if="carga.horometro" class="col-sm-4">
                                    <p class="text-muted fs-11 text-uppercase mb-1">Horómetro</p>
                                    <p class="fw-semibold mb-0">{{ fmt(carga.horometro, 2) }} hr</p>
                                </div>
                            </div>

                            <!-- Vale utilizado -->
                            <template v-if="carga.tipo_carga === 'VALE'">
                                <div v-if="carga.vale" class="card border border-primary mb-3">
                                    <div class="card-header bg-primary-transparent py-2 px-3 d-flex align-items-center justify-content-between">
                                        <span class="fw-semibold fs-13 text-primary text-uppercase">
                                            <i class="ri-file-list-3-line me-1"></i>Vale Utilizado
                                            <span class="ms-1">#{{ carga.vale.nro }}</span>
                                        </span>
                                        <span class="badge fs-11" :class="valeEstadoBadgeClass(carga.vale.estado_vale)">
                                            {{ carga.vale.estado_vale }}
                                        </span>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="row g-3 mb-3">
                                            <div class="col-sm-6 col-md-3">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Fecha de emisión</p>
                                                <p class="fw-semibold mb-0">{{ carga.vale.fecha_emision ?? '—' }}</p>
                                            </div>
                                            <div class="col-sm-6 col-md-3">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Fecha de vencimiento</p>
                                                <p class="fw-semibold mb-0">
                                                    {{ carga.vale.fecha_vencimiento ?? '—' }}
                                                    <span
                                                        v-if="carga.vale.estado_vale === 'PENDIENTE' && carga.vale.fecha_vencimiento"
                                                        class="d-block fs-11 fw-semibold"
                                                        :class="`text-${getExpirationStatus(carga.vale.fecha_vencimiento).color}`"
                                                    >
                                                        ({{ getExpirationStatus(carga.vale.fecha_vencimiento).text }})
                                                    </span>
                                                </p>
                                            </div>
                                            <div class="col-sm-6 col-md-3">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Tipo combustible</p>
                                                <p class="fw-semibold mb-0">{{ carga.vale.tipo_combustible ?? '—' }}</p>
                                            </div>
                                            <div class="col-sm-6 col-md-3">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Grifo autorizado</p>
                                                <p class="fw-semibold mb-0">{{ carga.vale.grifo?.razon_social ?? '—' }}</p>
                                            </div>
                                        </div>

                                        <!-- Importes del vale -->
                                        <div class="row g-0 text-center border rounded">
                                            <div class="col-4 border-end py-2">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Litros autorizados</p>
                                                <p class="fw-bold fs-18 mb-0 text-primary">{{ fmt(carga.vale.litros) }}</p>
                                                <small class="text-muted">lts</small>
                                            </div>
                                            <div class="col-4 border-end py-2">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Precio unitario</p>
                                                <p class="fw-bold fs-18 mb-0 text-info">{{ fmt(carga.vale.precio) }}</p>
                                                <small class="text-muted">Bs/lt</small>
                                            </div>
                                            <div class="col-4 py-2">
                                                <p class="text-muted fs-11 text-uppercase mb-1">Total vale</p>
                                                <p class="fw-bold fs-18 mb-0 text-success">{{ fmt(carga.vale.total) }}</p>
                                                <small class="text-muted">Bs</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Carga tipo VALE pero sin vale asociado (caso inconsistente) -->
                                <div v-else class="alert alert-warning mb-3">
                                    <i class="ri-information-line me-2"></i>
                                    Esta carga está marcada como VALE pero no tiene un vale asociado.
                                </div>
                            </template>

                            <!-- Respaldos digitales -->
                            <div class="card border mb-3">
                                <div class="card-header py-2 px-3">
                                    <span class="fw-semibold fs-13 text-muted text-uppercase">
                                        <i class="ri-attachment-2 me-1"></i>Respaldos Digitales
                                        <span v-if="carga.respaldos?.length" class="badge bg-secondary-transparent text-secondary ms-1">{{ carga.respaldos.length }}</span>
                                    </span>
                                </div>
                                <div class="card-body p-3">
                                    <div v-if="carga.respaldos?.length" class="d-flex flex-wrap gap-2">
                                        <a
                                            v-for="resp in carga.respaldos"
                                            :key="resp.id"
                                            :href="respaldoUrl(resp.ruta_respaldo)"
                                            target="_blank"
                                            class="d-flex align-items-center gap-2 border rounded-2 p-2 text-decoration-none"
                                        >
                                            <span
                                                class="badge"
                                                :class="resp.tipo_archivo === 'PDF' ? 'bg-danger-transparent text-danger' : 'bg-info-transparent text-info'"
                                            >
                                                <i :class="resp.tipo_archivo === 'PDF' ? 'ri-file-pdf-line' : 'ri-image-line'" class="me-1"></i>
                                                {{ resp.tipo_archivo }}
                                            </span>
                                            <span class="badge bg-secondary-transparent text-secondary">{{ resp.tipo_respaldo }}</span>
                                            <i class="ri-eye-line text-primary"></i>
                                        </a>
                                    </div>
                                    <p v-else class="text-muted mb-0 fs-13">No hay respaldos digitales adjuntos.</p>
                                </div>
                            </div>

                            <!-- Registrado por -->
                            <div v-if="carga.registrado_por" class="text-end">
                                <small class="text-muted">
                                    <i class="ri-user-settings-line me-1"></i>Registrado por: <span class="fw-medium">{{ carga.registrado_por }}</span>
                                </small>
                            </div>

                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-wave" @click="emit('close')">
                            <i class="ri-close-line me-1"></i> Cerrar
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </teleport>
</template>
