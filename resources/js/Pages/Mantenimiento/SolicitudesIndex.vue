<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import { showToast, confirm } from '@/Utils/alertUtil.js'

const props = defineProps({
    solicitudes: Object,
    vehiculos: Array,
    filters: Object,
    flash: Object,
})

const filters = ref({
    estado: props.filters?.estado ?? '',
    tipo_mantenimiento: props.filters?.tipo_mantenimiento ?? '',
    id_vehiculo: props.filters?.id_vehiculo ?? '',
})

let debounceTimer = null
watch(filters, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('mantenimiento.solicitudes.index'), {
            estado: val.estado || undefined,
            tipo_mantenimiento: val.tipo_mantenimiento || undefined,
            id_vehiculo: val.id_vehiculo || undefined,
        }, { preserveState: true, replace: true })
    }, 350)
}, { deep: true })

function clearFilters() {
    filters.value = { estado: '', tipo_mantenimiento: '', id_vehiculo: '' }
}

const estadoBadge = (estado) => {
    const map = {
        PENDIENTE: 'bg-warning-transparent text-warning',
        APROBADA:  'bg-success-transparent text-success',
        RECHAZADA: 'bg-danger-transparent text-danger',
        ANULADA:   'bg-secondary-transparent text-secondary',
    }
    return map[estado] ?? 'bg-light text-dark'
}

const tipoBadge = (tipo) => {
    return tipo === 'PREVENTIVO'
        ? 'bg-info-transparent text-info'
        : 'bg-danger-transparent text-danger'
}
</script>

<template>
    <Head title="Solicitudes de Mantenimiento" />
    <Maindashboard>
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item active">Solicitudes de Mantenimiento</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Solicitudes de Mantenimiento</h1>
            </div>
            <Link :href="route('mantenimiento.solicitudes.create')" class="btn btn-primary btn-wave">
                <i class="ri-add-line me-1"></i> Nueva Solicitud
            </Link>
        </div>

        <!-- Flash -->
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
            <div class="card-header"><div class="card-title">Filtros</div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-xl-4">
                        <label class="form-label">Estado</label>
                        <select v-model="filters.estado" class="form-select">
                            <option value="">Todos</option>
                            <option value="PENDIENTE">Pendiente</option>
                            <option value="APROBADA">Aprobada</option>
                            <option value="RECHAZADA">Rechazada</option>
                            <option value="ANULADA">Anulada</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <label class="form-label">Tipo</label>
                        <select v-model="filters.tipo_mantenimiento" class="form-select">
                            <option value="">Todos</option>
                            <option value="PREVENTIVO">Preventivo</option>
                            <option value="CORRECTIVO">Correctivo</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-4">
                        <label class="form-label">Vehículo</label>
                        <select v-model="filters.id_vehiculo" class="form-select">
                            <option value="">Todos</option>
                            <option v-for="v in vehiculos" :key="v.id" :value="v.id">
                                {{ v.nro_placa }} – {{ v.marca }}
                            </option>
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
                    Solicitudes
                    <span class="badge bg-primary-transparent text-primary ms-2">{{ solicitudes.total }} registros</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Nro</th>
                                <th>Fecha</th>
                                <th>Vehículo</th>
                                <th>Conductor</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th>Km actual</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="solicitudes.data.length === 0">
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="ri-tools-line fs-3 d-block mb-2"></i>
                                    No se encontraron solicitudes
                                </td>
                            </tr>
                            <tr v-for="(s, idx) in solicitudes.data" :key="s.id">
                                <td>{{ idx + 1 }}</td>
                                <td>{{ s.nro }}</td>
                                <td>{{ s.fecha }}</td>
                                <td>
                                    <span class="fw-medium">{{ s.vehiculo?.nro_placa ?? '—' }}</span>
                                    <br /><small class="text-muted">{{ s.vehiculo?.marca ?? '' }}</small>
                                </td>
                                <td>{{ s.conductor ? `${s.conductor.persona.nombre_completo}` : '—' }}</td>
                                <td><span class="badge" :class="tipoBadge(s.tipo_mantenimiento)">{{ s.tipo_mantenimiento }}</span></td>
                                <td style="max-width:220px;white-space:normal;">
                                    {{ s.descripcion_problema?.substring(0, 80) }}{{ s.descripcion_problema?.length > 80 ? '…' : '' }}
                                </td>
                                <td>{{ s.kilometraje_actual != null ? s.kilometraje_actual.toLocaleString() + ' km' : '—' }}</td>
                                <td><span class="badge" :class="estadoBadge(s.estado)">{{ s.estado }}</span></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <Link :href="route('mantenimiento.solicitudes.show', s.id)"
                                            class="btn btn-outline-primary btn-wave" title="Ver detalle">
                                            <i class="ri-eye-line"></i>
                                        </Link>
                                        <Link :href="route('mantenimiento.solicitudes.imprimir', s.id)" target="_blank"
                                            class="btn btn-outline-primary btn-wave" title="Ver detalle">
                                            <i class="ri-file-list-3-line"></i>
                                        </Link>
                                        <!-- <Link v-if="s.estado === 'PENDIENTE' && !s.plan_mantenimiento" -->
                                         <Link v-if="false" >
                                            :href="route('mantenimiento.ordenes.create', { solicitud: s.id })"
                                            class="btn btn-outline-success btn-wave" title="Generar orden de trabajo">
                                            <i class="ri-file-list-3-line"></i>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Paginación -->
            <div v-if="solicitudes.last_page > 1" class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Mostrando {{ solicitudes.from }}–{{ solicitudes.to }} de {{ solicitudes.total }}
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item" :class="{ disabled: !solicitudes.prev_page_url }">
                            <Link class="page-link" :href="solicitudes.prev_page_url ?? '#'">‹</Link>
                        </li>
                        <li v-for="page in solicitudes.last_page" :key="page" class="page-item"
                            :class="{ active: page === solicitudes.current_page }">
                            <Link class="page-link"
                                :href="route('mantenimiento.solicitudes.index', { page })">{{ page }}</Link>
                        </li>
                        <li class="page-item" :class="{ disabled: !solicitudes.next_page_url }">
                            <Link class="page-link" :href="solicitudes.next_page_url ?? '#'">›</Link>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
    </Maindashboard>
</template>
