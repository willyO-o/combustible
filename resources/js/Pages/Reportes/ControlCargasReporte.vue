<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import Multiselect from '@vueform/multiselect'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
import BotonDescargarGrafico from '@/Components/BotonDescargarGrafico.vue'
import { useTemaGraficos } from '@/Composables/useTemaGraficos'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculosExternos: Array,
    datosResumen: Object,
    filtros: Object,
})

const filtros = ref({
    fecha_desde: props.filtros?.fecha_desde ?? '',
    fecha_hasta: props.filtros?.fecha_hasta ?? '',
    id_vehiculo_externo: props.filtros?.id_vehiculo_externo ?? '',
    ambito: props.filtros?.ambito ?? 'todos',
})

const vehiculoOptions = computed(() =>
    props.vehiculosExternos.map((v) => ({
        id: v.id,
        label: `${v.nro_placa ?? 'Sin placa'}${v.propietario ? ' — ' + v.propietario : ''}`,
    })),
)

let debounceTimer = null
watch(filtros, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('control-cargas.reporte.index'), {
            // fecha_desde/fecha_hasta viajan tal cual (incluso ''): si se
            // omitieran, "Limpiar" no podría quitar el rango y el backend
            // reaplicaría el default "Este mes".
            fecha_desde: val.fecha_desde,
            fecha_hasta: val.fecha_hasta,
            id_vehiculo_externo: val.id_vehiculo_externo || undefined,
            ambito: val.ambito !== 'todos' ? val.ambito : undefined,
        }, { preserveState: true, replace: true })
    }, 500)
}, { deep: true })

function clearFilters() {
    filtros.value = { fecha_desde: '', fecha_hasta: '', id_vehiculo_externo: '', ambito: 'todos' }
}

function generarPDF() {
    if (!filtros.value.fecha_desde || !filtros.value.fecha_hasta) {
        alert('Por favor, selecciona un rango de fechas')
        return
    }

    const url = route('control-cargas.reporte.pdf', {
        fecha_desde: filtros.value.fecha_desde,
        fecha_hasta: filtros.value.fecha_hasta,
        id_vehiculo_externo: filtros.value.id_vehiculo_externo || undefined,
        ambito: filtros.value.ambito !== 'todos' ? filtros.value.ambito : undefined,
    })

    window.open(url, '_blank')
}

/**
 * Mismo reporte que generarPDF(), en Excel. El .xlsx viaja como adjunto, así
 * que se navega a la URL (el navegador descarga sin abrir una pestaña vacía).
 */
function generarExcel() {
    if (!filtros.value.fecha_desde || !filtros.value.fecha_hasta) {
        alert('Por favor, selecciona un rango de fechas')
        return
    }

    window.location.href = route('control-cargas.reporte.excel', {
        fecha_desde: filtros.value.fecha_desde,
        fecha_hasta: filtros.value.fecha_hasta,
        id_vehiculo_externo: filtros.value.id_vehiculo_externo || undefined,
        ambito: filtros.value.ambito !== 'todos' ? filtros.value.ambito : undefined,
    })
}

const vehiculos = computed(() => props.datosResumen?.vehiculos ?? [])
const totales = computed(() => props.datosResumen?.totales ?? {
    total_vehiculos: 0, total_fletes: 0, total_viajes: 0, viajes_exterior: 0, viajes_nacional: 0, monto_total: 0,
})
const hayDatos = computed(() => vehiculos.value.length > 0)

const bs = (v) => `Bs. ${Number(v ?? 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

/* ------------------------------------------------------------------ */
/*  Gráfico: viajes por vehículo externo (top 15)                      */
/* ------------------------------------------------------------------ */
const { paleta } = useTemaGraficos()
const a2Decimales = (v) => Math.round((Number(v) || 0) * 100) / 100
const TOPE = 15

const grafViajes = ref(null)

const vehiculosGrafico = computed(() =>
    [...vehiculos.value].sort((a, b) => b.total_viajes - a.total_viajes).slice(0, TOPE),
)

const barSeries = computed(() => [
    { name: 'Al exterior', data: vehiculosGrafico.value.map((v) => a2Decimales(v.viajes_exterior)) },
    { name: 'Nacionales', data: vehiculosGrafico.value.map((v) => a2Decimales(v.viajes_nacional)) },
])

const barOptions = computed(() => {
    const horizontal = vehiculosGrafico.value.length > 6

    return {
        chart: { type: 'bar', stacked: true, toolbar: { show: false } },
        colors: [paleta.value[2] ?? '#f5b849', paleta.value[0]],
        plotOptions: { bar: { horizontal, borderRadius: 3, columnWidth: '55%', barHeight: '65%' } },
        dataLabels: { enabled: false },
        xaxis: { categories: vehiculosGrafico.value.map((v) => v.nro_placa || v.propietario || '—') },
        yaxis: { title: { text: horizontal ? '' : 'Viajes' } },
        legend: { position: 'top' },
        tooltip: { y: { formatter: (v) => `${v} viajes` } },
        grid: { strokeDashArray: 4 },
    }
})
</script>

<template>
    <Head title="Reporte de Control de Carga de Material" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item active">Reportes</li>
                    <li class="breadcrumb-item active">Control de Carga de Material</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Reporte de Control de Carga de Material</h1>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card custom-card mb-4">
        <div class="card-header"><div class="card-title">Filtros de Búsqueda</div></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <DateRangeFilter v-model:fecha-desde="filtros.fecha_desde"
                        v-model:fecha-hasta="filtros.fecha_hasta" label="Fecha de apertura del flete" default-range="Este mes" />
                </div>
                <div class="col-sm-6 col-lg-5">
                    <label class="form-label">Vehículo externo</label>
                    <Multiselect
                        v-model="filtros.id_vehiculo_externo"
                        :options="vehiculoOptions"
                        value-prop="id"
                        label="label"
                        :searchable="true"
                        :filter-results="true"
                        placeholder="Todos los vehículos externos"
                        no-options-text="Sin vehículos externos"
                        no-results-text="Sin resultados"
                    />
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label">Viajes al exterior</label>
                    <select v-model="filtros.ambito" class="form-select">
                        <option value="todos">Todos</option>
                        <option value="exterior">Sólo al exterior</option>
                        <option value="nacional">Sólo nacionales</option>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-wave" @click="clearFilters">
                        <i class="ri-refresh-line me-1"></i> Limpiar
                    </button>
                    <button v-can="'control-cargas.reporte.pdf'" type="button" class="btn btn-primary btn-wave" @click="generarPDF">
                        <i class="ri-file-pdf-line me-1"></i> PDF
                    </button>
                    <button v-can="'control-cargas.reporte.excel'" type="button" class="btn btn-success btn-wave" @click="generarExcel">
                        <i class="ri-file-excel-2-line me-1"></i> Excel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Resumen -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4 col-xl">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Vehículos externos</p>
                            <h3 class="fw-bold mb-0">{{ totales.total_vehiculos }}</h3>
                        </div>
                        <div class="avatar avatar-lg bg-warning-transparent">
                            <i class="ri-truck-line fs-24 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Total de fletes</p>
                            <h3 class="fw-bold mb-0">{{ totales.total_fletes }}</h3>
                        </div>
                        <div class="avatar avatar-lg bg-success-transparent">
                            <i class="ri-archive-line fs-24 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Total de viajes</p>
                            <h3 class="fw-bold mb-0">{{ totales.total_viajes }}</h3>
                        </div>
                        <div class="avatar avatar-lg bg-primary-transparent">
                            <i class="ri-route-line fs-24 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Al exterior / Nacionales</p>
                            <h3 class="fw-bold mb-0">{{ totales.viajes_exterior }} / {{ totales.viajes_nacional }}</h3>
                        </div>
                        <div class="avatar avatar-lg bg-info-transparent">
                            <i class="ri-global-line fs-24 text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Monto pagado</p>
                            <h3 class="fw-bold mb-0">{{ bs(totales.monto_total) }}</h3>
                        </div>
                        <div class="avatar avatar-lg bg-secondary-transparent">
                            <i class="ri-money-dollar-circle-line fs-24 text-secondary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico -->
    <div v-if="hayDatos" class="card custom-card mb-4">
        <div class="card-header justify-content-between flex-wrap gap-2">
            <div class="card-title">Viajes por Vehículo Externo</div>
            <BotonDescargarGrafico :grafico="grafViajes" nombre="control-cargas-viajes-por-vehiculo"
                titulo="Viajes por Vehículo Externo" subtitulo="Reporte de Control de Carga de Material" />
        </div>
        <div class="card-body">
            <Apexchart ref="grafViajes" type="bar" height="380" :options="barOptions" :series="barSeries" />
        </div>
    </div>

    <!-- Tabla de Detalle -->
    <div class="card custom-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title">
                Detalle por Vehículo Externo
                <span class="badge bg-primary-transparent text-primary ms-2">{{ vehiculos.length }} vehículos</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Placa</th>
                            <th>Propietario</th>
                            <th class="text-center">Fletes</th>
                            <th class="text-center">Viajes</th>
                            <th class="text-center">Al exterior</th>
                            <th class="text-center">Nacionales</th>
                            <th class="text-end">Monto pagado</th>
                            <th class="text-center">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!hayDatos">
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="ri-inbox-line fs-3 d-block mb-2"></i>
                                No hay fletes registrados para el rango y el filtro seleccionados
                            </td>
                        </tr>
                        <tr v-for="(v, idx) in vehiculos" :key="v.id_vehiculo_externo">
                            <td>{{ idx + 1 }}</td>
                            <td class="fw-medium">{{ v.nro_placa ?? '—' }}</td>
                            <td>{{ v.propietario ?? '—' }}</td>
                            <td class="text-center"><span class="badge bg-success-transparent text-success">{{ v.total_fletes }}</span></td>
                            <td class="text-center"><span class="badge bg-primary-transparent text-primary">{{ v.total_viajes }}</span></td>
                            <td class="text-center"><span class="badge bg-info-transparent text-info">{{ v.viajes_exterior }}</span></td>
                            <td class="text-center"><span class="badge bg-secondary-transparent text-secondary">{{ v.viajes_nacional }}</span></td>
                            <td class="text-end fw-medium">{{ bs(v.monto_total) }}</td>
                            <td class="text-center">
                                <Link
                                    :href="route('control-cargas.reporte.detalle', {
                                        id_vehiculo_externo: v.id_vehiculo_externo,
                                        fecha_desde: filtros.fecha_desde,
                                        fecha_hasta: filtros.fecha_hasta,
                                    })"
                                    class="btn btn-sm btn-icon btn-info-light"
                                    title="Ver detalle de fletes y materiales"
                                >
                                    <i class="ri-eye-line"></i>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="hayDatos" class="table-active fw-bold">
                            <td colspan="3">TOTALES</td>
                            <td class="text-center">{{ totales.total_fletes }}</td>
                            <td class="text-center">{{ totales.total_viajes }}</td>
                            <td class="text-center">{{ totales.viajes_exterior }}</td>
                            <td class="text-center">{{ totales.viajes_nacional }}</td>
                            <td class="text-end">{{ bs(totales.monto_total) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
