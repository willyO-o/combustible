<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'

const props = defineProps({
    cargas:  Object,
    filters: Object,
    flash:   Object,
})

const filters = ref({
    nro_placa:   props.filters?.nro_placa   ?? '',
    fecha_desde: props.filters?.fecha_desde ?? '',
    fecha_hasta: props.filters?.fecha_hasta ?? '',
    tipo_carga:  props.filters?.tipo_carga  ?? '',
    estado_carga: props.filters?.estado_carga ?? '',
})

let debounceTimer = null
watch(filters, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('cargas.index'), {
            nro_placa:    val.nro_placa    || undefined,
            fecha_desde:  val.fecha_desde  || undefined,
            fecha_hasta:  val.fecha_hasta  || undefined,
            tipo_carga:   val.tipo_carga   || undefined,
            estado_carga: val.estado_carga || undefined,
        }, { preserveState: true, replace: true })
    }, 350)
}, { deep: true })

function clearFilters() {
    filters.value = { nro_placa: '', fecha_desde: '', fecha_hasta: '', tipo_carga: '', estado_carga: '' }
}

function confirmDelete(carga) {
    if (confirm(`¿Eliminar la carga del ${formatDate(carga.fecha_carga)} — ${carga.vehiculo?.nro_placa}?`)) {
        router.delete(route('cargas.destroy', carga.id))
    }
}

const estadoBadge = (estado) => {
    const map = { REGISTRADO: 'bg-info-transparent text-info', VERIFICADO: 'bg-success-transparent text-success', ANULADO: 'bg-danger-transparent text-danger' }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}
const tipoBadge = (tipo) =>
    tipo === 'VALE' ? 'bg-primary-transparent text-primary' : 'bg-warning-transparent text-warning'

const formatDate = (d) => d ? new Date(d).toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—'
</script>

<template>
    <Head title="Cargas de Combustible" />
    <Maindashboard>
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav><ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item active">Cargas de Combustible</li>
                </ol></nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Cargas de Combustible</h1>
            </div>
            <Link :href="route('cargas.create')" class="btn btn-primary btn-wave">
                <i class="ri-add-line me-1"></i> Nueva Carga
            </Link>
        </div>

        <div v-if="flash?.success" class="alert alert-success alert-dismissible fade show">
            <i class="ri-checkbox-circle-line me-2"></i>{{ flash.success }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <!-- Filtros -->
        <div class="card custom-card mb-4">
            <div class="card-header"><div class="card-title">Filtros</div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label">Nro. Placa</label>
                        <input v-model="filters.nro_placa" type="text" class="form-control" placeholder="Placa..." />
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label">Fecha desde</label>
                        <input v-model="filters.fecha_desde" type="date" class="form-control" />
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label">Fecha hasta</label>
                        <input v-model="filters.fecha_hasta" type="date" class="form-control" />
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label">Tipo</label>
                        <select v-model="filters.tipo_carga" class="form-select">
                            <option value="">Todos</option>
                            <option value="VALE">VALE</option>
                            <option value="PREPAGO">PREPAGO</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-2">
                        <label class="form-label">Estado</label>
                        <select v-model="filters.estado_carga" class="form-select">
                            <option value="">Todos</option>
                            <option value="REGISTRADO">REGISTRADO</option>
                            <option value="VERIFICADO">VERIFICADO</option>
                            <option value="ANULADO">ANULADO</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary btn-wave" @click="clearFilters">
                        <i class="ri-refresh-line me-1"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="card-title">
                    Registros
                    <span class="badge bg-primary-transparent text-primary ms-2">{{ cargas.total }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Vehículo</th>
                                <th>Conductor</th>
                                <th>Grifo</th>
                                <th>Combustible</th>
                                <th class="text-end">Litros</th>
                                <th class="text-end">P/U (Bs)</th>
                                <th class="text-end">Precio Total (Bs)</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th>Respaldos</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="cargas.data.length === 0">
                                <td colspan="11" class="text-center py-4 text-muted">
                                    <i class="ri-gas-station-line fs-3 d-block mb-2"></i>
                                    No se encontraron registros
                                </td>
                            </tr>
                            <tr v-for="carga in cargas.data" :key="carga.id">
                                <td>{{ formatDate(carga.fecha_carga) }}</td>
                                <td>
                                    <span class="fw-semibold">{{ carga.vehiculo?.nro_placa ?? '—' }}</span>
                                    <small v-if="carga.vehiculo?.marca" class="text-muted d-block">{{ carga.vehiculo.marca }}</small>
                                </td>
                                <td>
                                    {{ carga.conductor?.persona?.nombre_completo ?? '—' }}
                                </td>
                                <td>{{ carga.grifo?.razon_social ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-warning-transparent text-warning">
                                        {{ carga.tipo_combustible?.tipo_combustible ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-end fw-medium">{{ Number(carga.litros).toFixed(2) }}</td>
                                <td class="text-end fw-medium">Bs {{ Number(carga.precio).toFixed(2) }}</td>
                                <td class="text-end fw-medium">Bs {{ (Number(carga.litros) * Number(carga.precio)).toFixed(2) }}</td>
                                <td>
                                    <span class="badge" :class="tipoBadge(carga.tipo_carga)">{{ carga.tipo_carga }}</span>
                                </td>
                                <td>
                                    <span v-if="carga.estado_carga" class="badge" :class="estadoBadge(carga.estado_carga)">
                                        {{ carga.estado_carga }}
                                    </span>
                                    <span v-else class="text-muted">—</span>
                                </td>
                                <td class="text-center">
                                    <span v-if="carga.respaldos_digitales_count > 0" class="badge bg-info-transparent text-info">
                                        <i class="ri-attachment-2 me-1"></i>{{ carga.respaldos_digitales_count }}
                                    </span>
                                    <span v-else class="text-muted small">—</span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <Link :href="route('cargas.edit', carga.id)" class="btn btn-sm btn-icon btn-info-light" title="Editar">
                                            <i class="ri-edit-line"></i>
                                        </Link>
                                        <button type="button" class="btn btn-sm btn-icon btn-danger-light" title="Eliminar" @click="confirmDelete(carga)">
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
                <div class="text-muted small">Mostrando {{ cargas.from ?? 0 }} - {{ cargas.to ?? 0 }} de {{ cargas.total }}</div>
                <nav v-if="cargas.last_page > 1">
                    <ul class="pagination pagination-sm mb-0">
                        <li v-for="link in cargas.links" :key="link.label" class="page-item" :class="{ active: link.active, disabled: !link.url }">
                            <Link v-if="link.url" :href="link.url" class="page-link" preserve-state v-html="link.label" />
                            <span v-else class="page-link" v-html="link.label" />
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </Maindashboard>
</template>
