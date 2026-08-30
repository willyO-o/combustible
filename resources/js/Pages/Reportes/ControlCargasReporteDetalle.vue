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
    vehiculo: Object,
    detalle: Object,
    filtros: Object,
})

const filtros = ref({
    fecha_desde: props.filtros?.fecha_desde ?? '',
    fecha_hasta: props.filtros?.fecha_hasta ?? '',
    id_vehiculo_externo: props.filtros?.id_vehiculo_externo ?? '',
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
        router.get(route('control-cargas.reporte.detalle'), {
            fecha_desde: val.fecha_desde,
            fecha_hasta: val.fecha_hasta,
            id_vehiculo_externo: val.id_vehiculo_externo || undefined,
        }, { preserveState: true, replace: true })
    }, 400)
}, { deep: true })

function generarPDF() {
    if (!filtros.value.id_vehiculo_externo) {
        alert('Selecciona un vehículo externo')
        return
    }
    if (!filtros.value.fecha_desde || !filtros.value.fecha_hasta) {
        alert('Selecciona un rango de fechas')
        return
    }

    window.open(route('control-cargas.reporte.detalle.pdf', {
        fecha_desde: filtros.value.fecha_desde,
        fecha_hasta: filtros.value.fecha_hasta,
        id_vehiculo_externo: filtros.value.id_vehiculo_externo,
    }), '_blank')
}

const bs = (v) => `Bs. ${Number(v ?? 0).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

function formatFecha(fecha) {
    if (!fecha) return '—'
    return new Date(fecha).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const estadoBadge = (estado) => ({
    ABIERTA: 'bg-warning-transparent text-warning',
    CERRADA: 'bg-info-transparent text-info',
    PAGADA: 'bg-success-transparent text-success',
}[estado] ?? 'bg-secondary-transparent text-secondary')

const fletes = computed(() => props.detalle?.fletes ?? [])
const materiales = computed(() => props.detalle?.materiales ?? [])
const totales = computed(() => props.detalle?.totales ?? {
    total_fletes: 0, total_viajes: 0, total_materiales: 0, monto_total: 0,
})
const hayFletes = computed(() => fletes.value.length > 0)

const porcentaje = (viajes) => {
    const total = totales.value.total_viajes
    return total > 0 ? Math.round((viajes / total) * 1000) / 10 : 0
}

/* ------------------------------------------------------------------ */
/*  Gráfico de pastel: viajes por material                             */
/* ------------------------------------------------------------------ */
const { paleta } = useTemaGraficos()
const grafMateriales = ref(null)

const pieSeries = computed(() => materiales.value.map((m) => m.viajes))
const pieOptions = computed(() => ({
    chart: { type: 'pie', toolbar: { show: false } },
    labels: materiales.value.map((m) => m.material),
    colors: paleta.value,
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (val) => `${Number(val).toFixed(1)}%` },
    tooltip: { y: { formatter: (v) => `${v} viajes` } },
    stroke: { width: 2 },
}))
</script>

<template>
    <Head title="Detalle de Control de Carga de Material" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item"><Link :href="route('control-cargas.reporte.index')">Reportes</Link></li>
                    <li class="breadcrumb-item active">Detalle de Carga de Material</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                Detalle de Carga de Material
                <span v-if="vehiculo" class="text-primary">— {{ vehiculo.nro_placa ?? 'Sin placa' }}</span>
                <span v-if="vehiculo?.propietario" class="badge bg-info-transparent text-info ms-1">{{ vehiculo.propietario }}</span>
            </h1>
        </div>
        <div class="d-flex gap-2">
            <button v-if="filtros.id_vehiculo_externo" v-can="'control-cargas.reporte.pdf'" type="button" class="btn btn-primary btn-wave" @click="generarPDF">
                <i class="ri-file-pdf-line me-1"></i> PDF
            </button>
            <Link :href="route('control-cargas.reporte.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver al resumen
            </Link>
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
                <div class="col-lg-6">
                    <label class="form-label">Vehículo externo <span class="text-danger">*</span></label>
                    <Multiselect
                        v-model="filtros.id_vehiculo_externo"
                        :options="vehiculoOptions"
                        value-prop="id"
                        label="label"
                        :searchable="true"
                        :filter-results="true"
                        placeholder="— Seleccionar un vehículo externo —"
                        no-options-text="Sin vehículos externos"
                        no-results-text="Sin resultados"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Estado vacío: sin vehículo seleccionado -->
    <div v-if="!filtros.id_vehiculo_externo" class="card custom-card">
        <div class="card-body text-center py-5 text-muted">
            <i class="ri-truck-line fs-3 d-block mb-2"></i>
            Selecciona un vehículo externo para ver sus fletes y el desglose de viajes por material.
        </div>
    </div>

    <template v-else>
        <!-- Tarjetas de Resumen -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Fletes en el rango</p>
                        <h3 class="fw-bold mb-0">{{ totales.total_fletes }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Total de viajes</p>
                        <h3 class="fw-bold mb-0">{{ totales.total_viajes }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Tipos de material</p>
                        <h3 class="fw-bold mb-0">{{ totales.total_materiales }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Monto pagado</p>
                        <h3 class="fw-bold mb-0">{{ bs(totales.monto_total) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sin fletes -->
        <div v-if="!hayFletes" class="card custom-card">
            <div class="card-body text-center py-5 text-muted">
                <i class="ri-inbox-line fs-3 d-block mb-2"></i>
                Este vehículo no tiene fletes abiertos en el rango de fechas seleccionado.
            </div>
        </div>

        <template v-else>
            <div v-if="materiales.length" class="row g-3 mb-4">
                <!-- Gráfico de pastel: viajes por material -->
                <div class="col-xl-5">
                    <div class="card custom-card h-100">
                        <div class="card-header justify-content-between">
                            <div class="card-title">Viajes por Material</div>
                            <BotonDescargarGrafico :grafico="grafMateriales" nombre="control-cargas-viajes-por-material"
                                titulo="Viajes por Material" subtitulo="Detalle de Carga de Material" />
                        </div>
                        <div class="card-body d-flex justify-content-center">
                            <div style="width: 100%; max-width: 340px;">
                                <Apexchart ref="grafMateriales" type="pie" height="320" :options="pieOptions" :series="pieSeries" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen por material -->
                <div class="col-xl-7">
                    <div class="card custom-card h-100">
                        <div class="card-header"><div class="card-title">Resumen de Tipos de Carga</div></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover text-nowrap mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Material</th>
                                            <th class="text-center">Viajes</th>
                                            <th class="text-end">% del total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="m in materiales" :key="m.material">
                                            <td class="fw-medium">{{ m.material }}</td>
                                            <td class="text-center"><span class="badge bg-primary-transparent text-primary">{{ m.viajes }}</span></td>
                                            <td class="text-end">{{ porcentaje(m.viajes) }} %</td>
                                        </tr>
                                        <tr class="table-active fw-bold">
                                            <td>TOTAL</td>
                                            <td class="text-center">{{ totales.total_viajes }}</td>
                                            <td class="text-end">100 %</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Fletes -->
            <div class="card custom-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title">
                        Fletes del Vehículo
                        <span class="badge bg-primary-transparent text-primary ms-2">{{ fletes.length }} fletes</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover text-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Nº Flete</th>
                                    <th>Apertura</th>
                                    <th>Cierre</th>
                                    <th>Estado</th>
                                    <th>Ámbito</th>
                                    <th class="text-center">Viajes</th>
                                    <th>Viajes por material</th>
                                    <th class="text-end">Monto pagado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(f, idx) in fletes" :key="f.id">
                                    <td>{{ idx + 1 }}</td>
                                    <td class="fw-medium">{{ f.nro }}</td>
                                    <td>{{ formatFecha(f.fecha_apertura) }}</td>
                                    <td>{{ formatFecha(f.fecha_cierre) }}</td>
                                    <td><span class="badge" :class="estadoBadge(f.estado_carga)">{{ f.estado_carga }}</span></td>
                                    <td>
                                        <span v-if="f.es_al_exterior" class="badge bg-info-transparent text-info">
                                            Exterior<template v-if="f.pais"> · {{ f.pais }}</template>
                                        </span>
                                        <span v-else class="badge bg-secondary-transparent text-secondary">Nacional</span>
                                    </td>
                                    <td class="text-center"><span class="badge bg-primary-transparent text-primary">{{ f.viajes_count }}</span></td>
                                    <td>
                                        <span v-for="m in f.materiales" :key="m.material" class="badge bg-light text-dark border me-1">
                                            {{ m.material }}: {{ m.viajes }}
                                        </span>
                                        <span v-if="!f.materiales.length" class="text-muted">—</span>
                                    </td>
                                    <td class="text-end">{{ f.monto_pago !== null ? bs(f.monto_pago) : '—' }}</td>
                                </tr>
                                <tr class="table-active fw-bold">
                                    <td colspan="6">TOTALES</td>
                                    <td class="text-center">{{ totales.total_viajes }}</td>
                                    <td></td>
                                    <td class="text-end">{{ bs(totales.monto_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </template>
</template>
