<script setup>
import { reactive, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    cargas: Object,
    filters: Object,
    flash: Object,
})

const filtros = reactive({
    estado_carga: props.filters?.estado_carga ?? '',
    q: props.filters?.q ?? '',
})

let debounce = null
watch(filtros, () => {
    clearTimeout(debounce)
    debounce = setTimeout(() => {
        router.get(route('control-cargas.index'), filtros, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 300)
})

const estadoBadge = (estado) => ({
    ABIERTA: 'bg-success-transparent text-success',
    CERRADA: 'bg-secondary-transparent text-secondary',
    PAGADA: 'bg-primary-transparent text-primary',
}[estado] ?? 'bg-secondary-transparent text-secondary')
</script>

<template>
    <Head title="Control de Cargas" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item active">Control de Cargas</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Control de Cargas</h1>
        </div>
        <Link v-can="'control-cargas.crear'" :href="route('control-cargas.create')" class="btn btn-primary btn-wave">
            <i class="ri-add-line me-1"></i> Nueva Carga
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
    <div class="card custom-card mb-3">
        <div class="card-body py-3">
            <div class="row g-2">
                <div class="col-sm-7 col-lg-8">
                    <input
                        v-model="filtros.q"
                        type="text"
                        class="form-control"
                        placeholder="Buscar por N° de carga o placa..."
                    />
                </div>
                <div class="col-sm-5 col-lg-4">
                    <select v-model="filtros.estado_carga" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="ABIERTA">Abierta</option>
                        <option value="CERRADA">Cerrada</option>
                        <option value="PAGADA">Pagada</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Sin cargas -->
    <div v-if="cargas.data.length === 0" class="card custom-card">
        <div class="card-body text-center py-5 text-muted">
            <i class="ri-truck-line fs-1 d-block mb-2"></i>
            No se encontraron cargas.
        </div>
    </div>

    <!-- Listado de cargas (cards, mobile-first) -->
    <div v-else class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3">
        <div v-for="carga in cargas.data" :key="carga.id" class="col">
            <div class="card custom-card h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <div>
                            <div class="fw-semibold fs-16">{{ carga.vehiculo_externo?.nro_placa ?? '—' }}</div>
                            <small class="text-muted">{{ carga.vehiculo_externo?.propietario || 'Sin propietario registrado' }}</small>
                        </div>
                        <span class="badge" :class="estadoBadge(carga.estado_carga)">#{{ carga.nro }}</span>
                    </div>

                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge" :class="estadoBadge(carga.estado_carga)">{{ carga.estado_carga }}</span>
                        <span v-if="carga.es_propia" class="badge bg-info-transparent text-info">Tuya</span>
                        <span v-if="carga.es_al_exterior" class="badge bg-warning-transparent text-warning">
                            <i class="ri-earth-line me-1"></i>{{ carga.pais || 'Al exterior' }}
                        </span>
                    </div>

                    <div v-if="carga.nombre_conductor" class="small text-muted mb-1">
                        <i class="ri-user-line me-1"></i>{{ carga.nombre_conductor }}
                        <span v-if="carga.telefono"> — {{ carga.telefono }}</span>
                    </div>

                    <div class="small text-muted mb-3">
                        <i class="ri-time-line me-1"></i>{{ carga.fecha_apertura }}
                        <span v-if="!carga.es_propia"> · por {{ carga.abierta_por }}</span>
                    </div>

                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <span class="badge bg-secondary-transparent text-secondary">
                            <i class="ri-route-line me-1"></i>{{ carga.viajes_count }} viaje{{ carga.viajes_count === 1 ? '' : 's' }}
                        </span>
                        <div class="d-flex gap-1">
                            <Link
                                v-if="carga.puede_editar"
                                v-can="'control-cargas.editar'"
                                :href="route('control-cargas.edit', carga.id)"
                                class="btn btn-sm btn-icon btn-info-light"
                                title="Editar"
                            >
                                <i class="ri-edit-line"></i>
                            </Link>
                            <Link :href="route('control-cargas.show', carga.id)" class="btn btn-sm btn-primary btn-wave">
                                Ver <i class="ri-arrow-right-line ms-1"></i>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Paginador -->
    <div v-if="cargas.data.length > 0" class="d-flex align-items-center justify-content-between flex-wrap gap-2 mt-3">
        <div class="text-muted small">
            Mostrando
            <strong>{{ cargas.from ?? 0 }}</strong> -
            <strong>{{ cargas.to ?? 0 }}</strong>
            de <strong>{{ cargas.total }}</strong> resultados
        </div>
        <nav v-if="cargas.last_page > 1" aria-label="Paginación">
            <ul class="pagination pagination-sm mb-0">
                <li
                    v-for="link in cargas.links"
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
</template>
