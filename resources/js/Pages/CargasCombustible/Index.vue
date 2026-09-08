<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router, InfiniteScroll } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import CargaCombustibleDetalleModal from '@/Components/CargaCombustibleDetalleModal.vue'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
import { confirm } from '@/Utils/alertUtil.js'
import { formatDate } from '@/Utils/dateUtil'
import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'

defineOptions({ layout: Maindashboard })

// Debajo de "lg" (tablet en portrait y celular) se muestra un segundo
// listado en tarjetas con scroll infinito en vez de la tabla (ver
// .ai/rules/pages.md, "Listado responsivo con scroll infinito").
const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('lg')

const props = defineProps({
    cargas:  Object,
    filters: Object,
    flash:   Object,
})

const cargaDetalleId = ref(null)
function verDetalle(carga) {
    cargaDetalleId.value = carga.id
}

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
            // Sin "|| undefined": si el usuario limpia el filtro debe viajar
            // como '' explícito (no ausente), o el backend reaplicaría el
            // rango por defecto ("Este mes") al no encontrar la clave en el request.
            fecha_desde: val.fecha_desde,
            fecha_hasta: val.fecha_hasta,
            tipo_carga:   val.tipo_carga   || undefined,
            estado_carga: val.estado_carga || undefined,
        }, { preserveState: true, replace: true })
    }, 350)
}, { deep: true })

function clearFilters() {
    filters.value = { nro_placa: '', fecha_desde: '', fecha_hasta: '', tipo_carga: '', estado_carga: '' }
}

async function confirmDelete(carga) {
    const confirmado = await confirm(
        `¿Eliminar la carga del ${formatDate(carga.fecha_carga)} — ${carga.vehiculo?.nro_placa}?`,
        'Eliminar Carga',
        'Sí, eliminar',
    )

    if (!confirmado) {
        return
    }

    router.delete(route('cargas.destroy', carga.id))
}

const estadoBadge = (estado) => {
    const map = { REGISTRADO: 'bg-info-transparent text-info', VERIFICADO: 'bg-success-transparent text-success', ANULADO: 'bg-danger-transparent text-danger' }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}
const tipoBadge = (tipo) =>
    tipo === 'VALE' ? 'bg-primary-transparent text-primary' : 'bg-warning-transparent text-warning'

</script>

<template>
    <Head title="Cargas de Combustible" />
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav><ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item active">Cargas de Combustible</li>
                </ol></nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Cargas de Combustible</h1>
            </div>
            <Link v-can="'cargas-combustible.registrar'" :href="route('cargas.create')" class="btn btn-primary btn-wave">
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
                    <div class="col-sm-6 col-xl-3">
                        <DateRangeFilter v-model:fecha-desde="filters.fecha_desde"
                            v-model:fecha-hasta="filters.fecha_hasta" label="Fecha carga" default-range="Este mes" />
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
            <!-- Vista tabla: desktop -->
            <div v-if="!isMobile" class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover text-wrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nro.</th>
                                <th>Fecha</th>
                                <th>Vehículo</th>
                                <th>Conductor</th>
                                <th>Estacio servicio</th>
                                <th>Combustible</th>
                                <th class="text-end">Litros</th>
                                <th class="text-end">P/U (Bs)</th>
                                <th class="text-end">Precio Total (Bs)</th>
                                <th>Tipo</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="cargas.data.length === 0">
                                <td colspan="12" class="text-center py-4 text-muted">
                                    <i class="ri-gas-station-line fs-3 d-block mb-2"></i>
                                    No se encontraron registros
                                </td>
                            </tr>
                            <tr v-for="carga in cargas.data" :key="carga.id">
                                <td class="fw-medium">{{ carga.nro }}</td>
                                <td>{{ (carga.fecha_carga_formateada) }}</td>
                                <td class="text-nowrap">
                                    <span class="fw-semibold d-block" >{{ carga.vehiculo?.codigo ?? '—' }}</span>
                                    <span class="fw-semibold">{{ carga.vehiculo?.nro_placa ?? '—' }}</span>
                                    <small v-if="carga.vehiculo?.marca" class="text-muted d-block">{{ carga.vehiculo.marca }}</small>
                                </td>
                                <td>
                                    {{ carga.conductor?.persona?.nombre_completo ?? '—' }}
                                </td>
                                <td>
                                    {{ carga.grifo?.razon_social ?? '—' }}
                                </td>
                                <td>
                                    <span class="badge bg-warning-transparent text-warning">
                                        {{ carga.tipo_combustible?.tipo_combustible ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-end fw-medium">{{ Number(carga.litros).toFixed(2) }}</td>
                                <td class="text-end fw-medium"> {{ Number(carga.precio).toFixed(2) }}</td>
                                <td class="text-end fw-medium"> {{ (Number(carga.litros) * Number(carga.precio)).toFixed(2) }}</td>
                                <td>
                                    <span class="badge" :class="tipoBadge(carga.tipo_carga)">{{ carga.tipo_carga }}</span>
                                    <span class="s" >{{ carga?.vale?.nro }}</span>
                                </td>
                                <td>
                                    <span v-if="carga.estado_carga" class="badge" :class="estadoBadge(carga.estado_carga)">
                                        {{ carga.estado_carga }}
                                    </span>
                                    <span v-else class="text-muted">—</span>
                                </td>

                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <button type="button" class="btn btn-sm btn-icon btn-primary-light" title="Ver detalle" @click="verDetalle(carga)">
                                            <i class="ri-eye-line"></i>
                                        </button>
                                        <Link :href="route('cargas.comprobante', carga.id)" target="_blank"
                                            class="btn btn-sm btn-icon btn-warning-light" title="Imprimir comprobante de egreso">
                                            <i class="ri-printer-line"></i>
                                        </Link>
                                        <Link  v-if="carga.estado != 'REGISTRADO'" v-can="'cargas-combustible.editar'" :href="route('cargas.edit', carga.id)" class="btn btn-sm btn-icon btn-info-light" title="Editar">
                                            <i class="ri-edit-line"></i>
                                        </Link>
                                        <button v-if="carga.estado != 'REGISTRADO' && !carga.vale" v-can="'cargas-combustible.eliminar'" type="button" class="btn btn-sm btn-icon btn-danger-light" title="Eliminar" @click="confirmDelete(carga)">
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
                <div v-if="cargas.data.length === 0" class="text-center py-4 text-muted">
                    <i class="ri-gas-station-line fs-3 d-block mb-2"></i>
                    No se encontraron registros
                </div>

                <InfiniteScroll v-else data="cargas" only-next as="div" class="d-flex flex-column gap-2">
                    <div v-for="carga in cargas.data" :key="carga.id" class="list-card-mobile border rounded-3 p-3"
                        @click="verDetalle(carga)">

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-primary fs-13 fw-semibold">{{ carga.nro }}</span>
                            <span v-if="carga.estado_carga" class="badge" :class="estadoBadge(carga.estado_carga)">
                                {{ carga.estado_carga }}
                            </span>
                        </div>

                        <div class="mb-2">
                            <div class="fw-semibold">
                                {{ carga.vehiculo?.codigo ?? '—' }} — {{ carga.vehiculo?.nro_placa ?? '—' }}
                            </div>
                            <small v-if="carga.vehiculo?.marca" class="text-muted">{{ carga.vehiculo.marca }}</small>
                        </div>

                        <div class="row g-2 small mb-2">
                            <div class="col-6">
                                <span class="text-muted d-block">Conductor</span>
                                <span>{{ carga.conductor?.persona?.nombre_completo ?? '—' }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Estación de servicio</span>
                                <span>{{ carga.grifo?.razon_social ?? '—' }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Fecha</span>
                                <span>{{ carga.fecha_carga_formateada }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block">Tipo</span>
                                <span class="badge" :class="tipoBadge(carga.tipo_carga)">{{ carga.tipo_carga }}</span>
                                <span v-if="carga?.vale?.nro" class="text-muted"> {{ carga.vale.nro }}</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center justify-content-between border-top pt-2">
                            <div class="small">
                                <span class="badge bg-warning-transparent text-warning">
                                    {{ carga.tipo_combustible?.tipo_combustible ?? '—' }}
                                </span>
                                <div class="fw-bold mt-1">
                                    {{ Number(carga.litros).toFixed(2) }} Lt × Bs {{ Number(carga.precio).toFixed(2) }}
                                    = Bs {{ (Number(carga.litros) * Number(carga.precio)).toFixed(2) }}
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-1" @click.stop>
                                <Link :href="route('cargas.comprobante', carga.id)" target="_blank"
                                    class="btn btn-icon btn-warning-light" title="Imprimir comprobante de egreso">
                                    <i class="ri-printer-line"></i>
                                </Link>

                                <div v-if="carga.estado != 'REGISTRADO'" class="dropdown">
                                    <button type="button" class="btn btn-icon btn-light" data-bs-toggle="dropdown"
                                        aria-expanded="false" title="Más acciones">
                                        <i class="ri-more-2-fill"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li v-can="'cargas-combustible.editar'">
                                            <Link class="dropdown-item" :href="route('cargas.edit', carga.id)">
                                                <i class="ri-edit-line me-2"></i> Editar
                                            </Link>
                                        </li>
                                        <li v-if="!carga.vale" v-can="'cargas-combustible.eliminar'">
                                            <a class="dropdown-item text-danger" href="javascript:void(0);"
                                                @click="confirmDelete(carga)">
                                                <i class="ri-delete-bin-line me-2"></i> Eliminar
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
                            Cargando más registros...
                        </div>
                        <div v-else-if="!hasMore" class="text-center text-muted small py-2">
                            No hay más registros para mostrar.
                        </div>
                    </template>
                </InfiniteScroll>
            </div>

            <!-- Paginador: sólo la tabla desktop. El listado mobile usa
                 scroll infinito (InfiniteScroll arriba) en vez de páginas
                 numeradas. -->
            <div v-if="!isMobile" class="card-footer d-flex align-items-center justify-content-between flex-wrap gap-2">
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

        <CargaCombustibleDetalleModal :cargaId="cargaDetalleId" @close="cargaDetalleId = null" />
</template>
