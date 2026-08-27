<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, InfiniteScroll } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import ValeDetalleModal from '@/Components/ValeDetalleModal.vue'
import { getExpirationStatus, formatDate } from '@/Utils/dateUtil'
import { confirm } from '@/Utils/alertUtil.js'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'


defineOptions({ layout: Maindashboard })

// Debajo de "lg" (tablet en portrait y celular) se muestra un segundo
// listado en tarjetas en vez de la tabla: más fácil de leer/tocar en
// pantallas angostas que una tabla de 11 columnas con scroll horizontal.
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const props = defineProps({
    vales: Object,
    filters: Object,
    flash: Object,
})

const filters = ref({
    nro_vale: props.filters?.nro_vale ?? '',
    fecha_desde: props.filters?.fecha_desde ?? '',
    fecha_hasta: props.filters?.fecha_hasta ?? '',
    estado_vale: props.filters?.estado_vale ?? '',
})

let debounceTimer = null
watch(
    filters,
    (val) => {
        clearTimeout(debounceTimer)
        debounceTimer = setTimeout(() => {
            router.get(
                route('vales.index'),
                {
                    nro_vale: val.nro_vale || undefined,
                    // Sin "|| undefined": si el usuario limpia el filtro debe
                    // viajar como '' explícito (no ausente), o el backend
                    // reaplicaría el rango por defecto ("Este mes") al no
                    // encontrar la clave en el request.
                    fecha_desde: val.fecha_desde,
                    fecha_hasta: val.fecha_hasta,
                    estado_vale: val.estado_vale || undefined,
                },
                { preserveState: true, replace: true },
            )
        }, 350)
    },
    { deep: true },
)

function clearFilters() {
    filters.value = { nro_vale: '', fecha_desde: '', fecha_hasta: '', estado_vale: '' }
}

// Los vales no se eliminan: se anulan (ver ValeController::destroy). Se
// reutiliza la misma ruta/método DELETE y el permiso 'vales.eliminar'.
async function confirmAnular(vale) {
    const confirmado = await confirm(`¿Anular el Vale #${vale.nro_vale}?`, 'Anular Vale', 'Sí, anular')

    if (!confirmado) {
        return
    }

    router.delete(route('vales.destroy', vale.id))
}

// Sólo un vale PENDIENTE puede editarse o anularse: uno USADO ya no debe
// modificarse, y uno ANULADO tampoco (ni tiene sentido volver a anularlo).
const puedeEditarseOAnularse = (vale) => vale.estado_vale === 'PENDIENTE'

const selectedValeId = ref(null)
const openDetalle = (id) => { selectedValeId.value = id }
const closeDetalle = () => { selectedValeId.value = null }

const estadoBadge = (estado) => {
    const map = {
        PENDIENTE: 'bg-warning-transparent text-warning',
        USADO: 'bg-success-transparent text-success',
        ANULADO: 'bg-danger-transparent text-danger',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

// Sólo se puede usar un vale PENDIENTE y no vencido.
const puedeUsarse = (vale) =>
    vale.estado_vale === 'PENDIENTE' && getExpirationStatus(vale.fecha_vencimiento).text !== 'Expirado'


</script>

<template>

    <Head title="Vales" />

    <!-- Page header -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item active">Vales</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Gestión de Vales</h1>
        </div>
        <Link v-can="'vales.crear'" :href="route('vales.create')" class="btn btn-primary btn-wave">
            <i class="ri-add-line me-1"></i> Nuevo Vale
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
                    <label class="form-label">Nro. Vale</label>
                    <input v-model="filters.nro_vale" type="text" class="form-control"
                        placeholder="Buscar por nro..." />
                </div>
                <div class="col-sm-6 col-xl-3">
                    <DateRangeFilter v-model:fecha-desde="filters.fecha_desde"
                        v-model:fecha-hasta="filters.fecha_hasta" label="Fecha emisión" default-range="Este mes" />
                </div>
                <div class="col-sm-6 col-xl-2">
                    <label class="form-label">Estado</label>
                    <select v-model="filters.estado_vale" class="form-select">
                        <option value="">Todos</option>
                        <option value="PENDIENTE">PENDIENTE</option>
                        <option value="USADO">USADO</option>
                        <option value="ANULADO">ANULADO</option>
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
                Vales
                <span class="badge bg-primary-transparent text-primary ms-2">
                    {{ vales.total }} registros
                </span>
            </div>
        </div>
        <!-- Vista tabla: desktop -->
        <div v-if="!isMobile" class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover text-wrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nro. Vale</th>
                            <th>Fecha Emisión</th>
                            <th>Expiración</th>

                            <th>Vehículo</th>
                            <th>Conductor</th>
                            <th>Estación de Servicio</th>
                            <th class="text-end">Litros</th>
                            <th class="text-end">P/U (Bs)</th>
                            <th class="text-end">Precio Total (Bs)</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="vales.data.length === 0">
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="ri-file-list-3-line fs-3 d-block mb-2"></i>
                                No se encontraron vales
                            </td>
                        </tr>
                        <tr v-for="vale in vales.data" :key="vale.id">
                            <td>
                                <span class="badge bg-primary fs-12 fw-semibold">{{ vale.nro }}</span>
                            </td>
                            <td>{{ formatDate(vale.fecha_emision, true) }}</td>
                            <td>
                                <small class="d-block badge text-muted"> {{ formatDate(vale.fecha_vencimiento, true) }}</small>
                                <span class="badge"
                                    :class="`text-${getExpirationStatus(vale.fecha_vencimiento).color}`">
                                    {{ getExpirationStatus(vale.fecha_vencimiento).text }}
                                </span>
                            </td>
                            <td>
                                <span class="fw-medium d-block">{{ vale.vehiculo?.codigo ?? '—' }}</span>
                                <span class="fw-medium">{{ vale.vehiculo?.nro_placa ?? '—' }}</span>
                                <small v-if="vale.vehiculo?.marca" class="text-muted d-block">{{ vale.vehiculo.marca
                                }}</small>
                            </td>
                            <td>
                                <span>{{ vale.conductor ? `${vale.conductor.persona.nombre_completo}`.trim() : '—'
                                }}</span>
                                <small v-if="vale.conductor" class="text-muted d-block">CI: {{
                                    vale.conductor.persona.ci }}</small>
                            </td>
                            <td>
                                <span>{{ vale.grifo?.razon_social ?? '—' }}</span>
                                <small v-if="vale.grifo?.ciudad" class="text-muted d-block">{{ vale.grifo.ciudad
                                }}</small>
                            </td>
                            <td class="text-end fw-medium">{{ Number(vale.litros).toFixed(2) }}</td>
                            <td class="text-end fw-medium">{{ Number(vale.precio).toFixed(2) }}</td>
                            <td class="text-end fw-medium">{{ (Number(vale.litros) * Number(vale.precio)).toFixed(2)
                            }}</td>

                            <td>
                                <span class="badge" :class="estadoBadge(vale.estado_vale)">
                                    {{ vale.estado_vale }}
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <button type="button" class="btn btn-sm btn-icon btn-primary-light"
                                        title="Ver detalles" @click="openDetalle(vale.id)">
                                        <i class="ri-eye-line"></i>
                                    </button>
                                    <Link v-can="'cargas-combustible.registrar'" v-if="puedeUsarse(vale)"
                                        :href="route('cargas.create', { vale: vale.id })"
                                        class="btn btn-sm btn-icon btn-success-light" title="Usar vale (registrar carga)">
                                        <i class="ri-gas-station-line"></i>
                                    </Link>
                                    <Link v-can="'vales.editar'" v-if="puedeEditarseOAnularse(vale)" :href="route('vales.edit', vale.id)"
                                        class="btn btn-sm btn-icon btn-info-light" title="Editar">
                                        <i class="ri-edit-line"></i>
                                    </Link>
                                    <Link v-can="'vales.imprimir'" :href="route('vales.imprimir', vale.id)" target="_blank"
                                        class="btn btn-sm btn-icon btn-warning-light" title="Imprimir">
                                        <i class="ri-printer-line"></i>
                                    </Link>
                                    <button v-can="'vales.eliminar'" v-if="puedeEditarseOAnularse(vale)" type="button" class="btn btn-sm btn-icon btn-danger-light" title="Anular"
                                        @click="confirmAnular(vale)">
                                        <i class="ri-close-circle-line"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Vista tarjetas: tablet y celular (más fácil de leer/tocar que
             una tabla de 11 columnas con scroll horizontal). Scroll infinito
             sólo acá: la tabla desktop sigue con el paginador numerado de
             siempre (ver .ai/rules/pages.md). -->
        <div v-else class="card-body p-2">
            <div v-if="vales.data.length === 0" class="text-center py-4 text-muted">
                <i class="ri-file-list-3-line fs-3 d-block mb-2"></i>
                No se encontraron vales
            </div>

            <InfiniteScroll v-else data="vales" only-next as="div" class="d-flex flex-column gap-2">
                <div v-for="vale in vales.data" :key="vale.id" class="list-card-mobile border rounded-3 p-3"
                    @click="openDetalle(vale.id)">

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="badge bg-primary fs-13 fw-semibold">{{ vale.nro }}</span>
                        <span class="badge" :class="estadoBadge(vale.estado_vale)">{{ vale.estado_vale }}</span>
                    </div>

                    <div class="mb-2">
                        <div class="fw-semibold">
                            {{ vale.vehiculo?.codigo ?? '—' }} — {{ vale.vehiculo?.nro_placa ?? '—' }}
                        </div>
                        <small v-if="vale.vehiculo?.marca" class="text-muted">{{ vale.vehiculo.marca }}</small>
                    </div>

                    <div class="row g-2 small mb-2">
                        <div class="col-6">
                            <span class="text-muted d-block">Conductor</span>
                            <span>{{ vale.conductor ? `${vale.conductor.persona.nombre_completo}`.trim() : '—' }}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Estación de servicio</span>
                            <span>{{ vale.grifo?.razon_social ?? '—' }}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Emisión</span>
                            <span>{{ formatDate(vale.fecha_emision, true) }}</span>
                        </div>
                        <div class="col-6">
                            <span class="text-muted d-block">Vencimiento</span>
                            <span :class="`text-${getExpirationStatus(vale.fecha_vencimiento).color}`">
                                {{ getExpirationStatus(vale.fecha_vencimiento).text }}
                            </span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between border-top pt-2">
                        <div class="small">
                            <span class="fw-medium">{{ Number(vale.litros).toFixed(2) }} Lt</span>
                            <span class="text-muted mx-1">×</span>
                            <span class="fw-medium">Bs {{ Number(vale.precio).toFixed(2) }}</span>
                            <div class="fw-bold">
                                Bs {{ (Number(vale.litros) * Number(vale.precio)).toFixed(2) }}
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-1" @click.stop>
                            <Link v-can="'cargas-combustible.registrar'" v-if="puedeUsarse(vale)"
                                :href="route('cargas.create', { vale: vale.id })"
                                class="btn btn-icon btn-success-light" title="Usar vale (registrar carga)">
                                <i class="ri-gas-station-line"></i>
                            </Link>

                            <div class="dropdown">
                                <button type="button" class="btn btn-icon btn-light" data-bs-toggle="dropdown"
                                    aria-expanded="false" title="Más acciones">
                                    <i class="ri-more-2-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="javascript:void(0);" @click="openDetalle(vale.id)">
                                            <i class="ri-eye-line me-2"></i> Ver detalles
                                        </a>
                                    </li>
                                    <li v-if="puedeEditarseOAnularse(vale)" v-can="'vales.editar'">
                                        <Link class="dropdown-item" :href="route('vales.edit', vale.id)">
                                            <i class="ri-edit-line me-2"></i> Editar
                                        </Link>
                                    </li>
                                    <li v-can="'vales.imprimir'">
                                        <a class="dropdown-item" :href="route('vales.imprimir', vale.id)" target="_blank">
                                            <i class="ri-printer-line me-2"></i> Imprimir
                                        </a>
                                    </li>
                                    <li v-if="puedeEditarseOAnularse(vale)" v-can="'vales.eliminar'">
                                        <a class="dropdown-item text-danger" href="javascript:void(0);"
                                            @click="confirmAnular(vale)">
                                            <i class="ri-close-circle-line me-2"></i> Anular
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Indicador de carga / fin de lista del scroll infinito -->
                <template #next="{ loading, hasMore }">
                    <div v-if="loading" class="text-center text-muted small py-2">
                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                        Cargando más vales...
                    </div>
                    <div v-else-if="!hasMore" class="text-center text-muted small py-2">
                        No hay más vales para mostrar.
                    </div>
                </template>
            </InfiniteScroll>
        </div>

        <!-- Paginador: sólo la tabla desktop. El listado mobile usa scroll
             infinito (InfiniteScroll arriba) en vez de páginas numeradas. -->
        <div v-if="!isMobile" class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="text-muted small">
                Mostrando {{ vales.from ?? 0 }} - {{ vales.to ?? 0 }}
                de {{ vales.total }} resultados
            </div>
            <nav v-if="vales.last_page > 1">
                <ul class="pagination pagination-sm mb-0">
                    <li v-for="link in vales.links" :key="link.label" class="page-item"
                        :class="{ active: link.active, disabled: !link.url }">
                        <Link v-if="link.url" :href="link.url" class="page-link" preserve-state v-html="link.label" />
                        <span v-else class="page-link" v-html="link.label" />
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Modal Detalle Vale -->
    <ValeDetalleModal :vale-id="selectedValeId" @close="closeDetalle" />

</template>
