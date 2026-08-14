<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    ordenes: Object,
    vehiculos: Array,
    filters: Object,
    flash: Object,
})

const filters = ref({
    estado_plan: props.filters?.estado_plan ?? '',
    tipo_mantenimiento: props.filters?.tipo_mantenimiento ?? '',
    tipo_orden: props.filters?.tipo_orden ?? '',
    id_vehiculo: props.filters?.id_vehiculo ?? '',
})

let debounceTimer = null
watch(filters, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('mantenimiento.ordenes.index'), {
            estado_plan:        val.estado_plan || undefined,
            tipo_mantenimiento: val.tipo_mantenimiento || undefined,
            tipo_orden:         val.tipo_orden || undefined,
            id_vehiculo:        val.id_vehiculo || undefined,
        }, { preserveState: true, replace: true })
    }, 350)
}, { deep: true })

function clearFilters() {
    filters.value = { estado_plan: '', tipo_mantenimiento: '', tipo_orden: '', id_vehiculo: '' }
}

const estadoBadge = (estado) => {
    const map = {
        BORRADOR:   'bg-secondary-transparent text-secondary',
        PENDIENTE:  'bg-warning-transparent text-warning',
        EN_PROCESO: 'bg-info-transparent text-info',
        COMPLETADO: 'bg-success-transparent text-success',
        VENCIDO:    'bg-danger-transparent text-danger',
        ANULADO:    'bg-dark text-white',
    }
    return map[estado] ?? 'bg-light text-dark'
}

const tipoBadge = (tipo) =>
    tipo === 'PREVENTIVO' ? 'bg-info-transparent text-info' : 'bg-danger-transparent text-danger'

const ordenBadge = (tipo) =>
    tipo === 'INTERNO' ? 'bg-primary-transparent text-primary' : 'bg-warning-transparent text-warning'
</script>

<template>
    <Head title="Órdenes de Mantenimiento" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item active">Órdenes de Mantenimiento</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Órdenes de Trabajo – Mantenimiento</h1>
            </div>
            <Link :href="route('mantenimiento.ordenes.create')" class="btn btn-primary btn-wave">
                <i class="ri-add-line me-1"></i> Nueva Orden
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
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Estado</label>
                        <select v-model="filters.estado_plan" class="form-select">
                            <option value="">Todos</option>
                            <option value="BORRADOR">Borrador</option>
                            <option value="PENDIENTE">Pendiente</option>
                            <option value="EN_PROCESO">En Proceso</option>
                            <option value="COMPLETADO">Completado</option>
                            <option value="VENCIDO">Vencido</option>
                            <option value="ANULADO">Anulado</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Tipo</label>
                        <select v-model="filters.tipo_mantenimiento" class="form-select">
                            <option value="">Todos</option>
                            <option value="PREVENTIVO">Preventivo</option>
                            <option value="CORRECTIVO">Correctivo</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <label class="form-label">Orden</label>
                        <select v-model="filters.tipo_orden" class="form-select">
                            <option value="">Todos</option>
                            <option value="INTERNO">Interno</option>
                            <option value="EXTERNO">Externo</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-xl-3">
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
                    Órdenes
                    <span class="badge bg-primary-transparent text-primary ms-2">{{ ordenes.total }} registros</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Vehículo</th>
                                <th>Tipo Mant.</th>
                                <th>Tipo Orden</th>
                                <th>Fecha Orden</th>
                                <th>Fecha Prog.</th>
                                <th>Taller</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="ordenes.data.length === 0">
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="ri-file-list-3-line fs-3 d-block mb-2"></i>
                                    No se encontraron órdenes
                                </td>
                            </tr>
                            <tr v-for="(o, idx) in ordenes.data" :key="o.id">
                                <td>{{ idx + 1 }}</td>
                                <td>
                                    <span class="fw-medium">{{ o.vehiculo?.nro_placa ?? '—' }}</span>
                                    <br /><small class="text-muted">{{ o.vehiculo?.marca ?? '' }}</small>
                                </td>
                                <td><span class="badge" :class="tipoBadge(o.tipo_mantenimiento)">{{ o.tipo_mantenimiento.tipo_mantenimiento }}</span></td>
                                <td><span class="badge" :class="ordenBadge(o.tipo_orden)">{{ o.tipo_orden }}</span></td>
                                <td>{{ o.fecha_orden ?? '—' }}</td>
                                <td>{{ o.fecha_programada ?? '—' }}</td>
                                <td>{{ o.taller?.razon_social ?? (o.tipo_orden === 'INTERNO' ? 'Interno' : '—') }}</td>
                                <td><span class="badge" :class="estadoBadge(o.estado_plan)">{{ o.estado_plan }}</span></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <Link :href="route('mantenimiento.ordenes.show', o.id)"
                                            class="btn btn-outline-primary btn-wave" title="Ver detalle">
                                            <i class="ri-eye-line"></i>
                                        </Link>
                                        <Link v-if="['BORRADOR','PENDIENTE'].includes(o.estado_plan)"
                                            :href="route('mantenimiento.ordenes.edit', o.id)"
                                            class="btn btn-outline-secondary btn-wave" title="Editar">
                                            <i class="ri-pencil-line"></i>
                                        </Link>
                                        <Link v-if="['PENDIENTE','EN_PROCESO'].includes(o.estado_plan)"
                                            :href="route('mantenimiento.ordenes.ejecucion.create', o.id)"
                                            class="btn btn-outline-success btn-wave" title="Registrar ejecución">
                                            <i class="ri-tools-line"></i>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Paginación -->
            <div v-if="ordenes.last_page > 1" class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted">
                    Mostrando {{ ordenes.from }}–{{ ordenes.to }} de {{ ordenes.total }}
                </small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item" :class="{ disabled: !ordenes.prev_page_url }">
                            <Link class="page-link" :href="ordenes.prev_page_url ?? '#'">‹</Link>
                        </li>
                        <li v-for="page in ordenes.last_page" :key="page" class="page-item"
                            :class="{ active: page === ordenes.current_page }">
                            <Link class="page-link"
                                :href="route('mantenimiento.ordenes.index', { page })">{{ page }}</Link>
                        </li>
                        <li class="page-item" :class="{ disabled: !ordenes.next_page_url }">
                            <Link class="page-link" :href="ordenes.next_page_url ?? '#'">›</Link>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>
</template>
