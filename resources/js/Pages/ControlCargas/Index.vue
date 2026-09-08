<script setup>
import { reactive, computed, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import Multiselect from '@vueform/multiselect'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    cargas: Object,
    vehiculosExternos: Array,
    filters: Object,
    flash: Object,
})

const filtros = reactive({
    estado_carga: props.filters?.estado_carga ?? '',
    q: props.filters?.q ?? '',
    id_vehiculo_externo: props.filters?.id_vehiculo_externo ?? '',
    fecha_desde: props.filters?.fecha_desde ?? '',
    fecha_hasta: props.filters?.fecha_hasta ?? '',
})

const vehiculoOptions = computed(() =>
    props.vehiculosExternos.map((v) => ({
        id: v.id,
        label: `${v.nro_placa ?? 'Sin placa'}${v.propietario ? ' — ' + v.propietario : ''}`,
    })),
)

let debounce = null
watch(filtros, () => {
    clearTimeout(debounce)
    debounce = setTimeout(() => {
        router.get(route('control-cargas.index'), {
            estado_carga: filtros.estado_carga || undefined,
            q: filtros.q || undefined,
            id_vehiculo_externo: filtros.id_vehiculo_externo || undefined,
            // Sin "|| undefined": si el usuario limpia el rango debe viajar
            // como '' explícito (no ausente), o el backend reaplicaría el
            // default ("Este mes") al no encontrar la clave en el request.
            fecha_desde: filtros.fecha_desde,
            fecha_hasta: filtros.fecha_hasta,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }, 300)
})

function limpiarFiltros() {
    filtros.estado_carga = ''
    filtros.q = ''
    filtros.id_vehiculo_externo = ''
    filtros.fecha_desde = ''
    filtros.fecha_hasta = ''
}

const estadoBadge = (estado) => ({
    ABIERTA: 'bg-success-transparent text-success',
    CERRADA: 'bg-secondary-transparent text-secondary',
    PAGADA: 'bg-primary-transparent text-primary',
}[estado] ?? 'bg-secondary-transparent text-secondary')
</script>

<template>
    <Head title="Fletes y Viajes" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item active">Fletes y Viajes</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Fletes y Viajes</h1>
        </div>
        <Link v-can="'control-cargas.crear'" :href="route('control-cargas.create')" class="btn btn-primary btn-wave">
            <i class="ri-add-line me-1"></i> Nuevo Flete
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
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label">Buscar</label>
                    <input
                        v-model="filtros.q"
                        type="text"
                        class="form-control"
                        placeholder="Buscar por N° de flete o placa..."
                    />
                </div>
                <div class="col-sm-6 col-lg-3">
                    <DateRangeFilter
                        v-model:fecha-desde="filtros.fecha_desde"
                        v-model:fecha-hasta="filtros.fecha_hasta"
                        label="Fecha de apertura"
                        default-range="Este mes"
                    />
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label">Placa</label>
                    <Multiselect
                        v-model="filtros.id_vehiculo_externo"
                        :options="vehiculoOptions"
                        value-prop="id"
                        label="label"
                        :searchable="true"
                        :filter-results="true"
                        placeholder="Todas las placas"
                        no-options-text="Sin vehículos externos"
                        no-results-text="Sin resultados"
                    />
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label">Estado</label>
                    <select v-model="filtros.estado_carga" class="form-select">
                        <option value="">Todos los estados</option>
                        <option value="ABIERTA">Abierta</option>
                        <option value="CERRADA">Cerrada</option>
                        <option value="PAGADA">Pagada</option>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="button" class="btn btn-outline-secondary btn-wave" @click="limpiarFiltros">
                        <i class="ri-refresh-line me-1"></i> Limpiar filtros
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sin fletes -->
    <div v-if="cargas.data.length === 0" class="card custom-card">
        <div class="card-body text-center py-5 text-muted">
            <i class="ri-truck-line fs-1 d-block mb-2"></i>
            No se encontraron fletes.
        </div>
    </div>

    <!-- Listado de fletes (cards, mobile-first) -->
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
                            <Link
                                v-can="'control-cargas.ver'"
                                :href="route('control-cargas.imprimir', carga.id)"
                                target="_blank"
                                class="btn btn-sm btn-icon btn-warning-light"
                                title="Imprimir informe del flete"
                            >
                                <i class="ri-printer-line"></i>
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
