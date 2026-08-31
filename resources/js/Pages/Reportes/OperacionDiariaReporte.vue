<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Multiselect from '@vueform/multiselect'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
import BotonDescargarGrafico from '@/Components/BotonDescargarGrafico.vue'
import { useTemaGraficos } from '@/Composables/useTemaGraficos'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculos: Array,
    tiposCombustible: Array,
    areas: Array,
    datosResumen: Object,
    filtros: Object,
})

const filtros = ref({
    fecha_inicio: props.filtros?.fecha_inicio ?? '',
    fecha_fin: props.filtros?.fecha_fin ?? '',
    // Selección múltiple para comparar varios vehículos (array de ids numéricos).
    id_vehiculo: (props.filtros?.id_vehiculo ?? []).map((id) => Number(id)),
    id_tipo_combustible: props.filtros?.id_tipo_combustible ?? '',
    id_area: props.filtros?.id_area ?? '',
})

// Opciones del multiselect de vehículos ({ value, label }).
const vehiculoOptions = computed(() =>
    props.vehiculos.map((v) => ({
        value: v.id,
        label: `${v.codigo} — ${v.nro_placa ?? ''}${v.marca ? ' — ' + v.marca : ''}`,
    })),
)

let debounceTimer = null
watch(filtros, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('operacion-diaria.reporte.uso.index'), {
            // Sin "|| undefined" en las fechas: si el usuario limpia el rango
            // debe viajar como '' explícito, o el backend reaplicaría "Este mes".
            fecha_inicio: val.fecha_inicio,
            fecha_fin: val.fecha_fin,
            id_vehiculo: val.id_vehiculo.length ? val.id_vehiculo : undefined,
            id_tipo_combustible: val.id_tipo_combustible || undefined,
            id_area: val.id_area || undefined,
        }, { preserveState: true, replace: true })
    }, 500)
}, { deep: true })

// El tipo de combustible y el área acotan qué vehículos son elegibles. Si el
// nuevo listado ya no incluye alguno de los elegidos para comparar, se descarta
// para no dejar una selección inválida filtrando el reporte.
watch(() => props.vehiculos, (nuevos) => {
    const idsValidos = new Set(nuevos.map((v) => v.id))
    const filtrados = filtros.value.id_vehiculo.filter((id) => idsValidos.has(id))
    if (filtrados.length !== filtros.value.id_vehiculo.length) {
        filtros.value.id_vehiculo = filtrados
    }
})

function clearFilters() {
    filtros.value = {
        fecha_inicio: '',
        fecha_fin: '',
        id_vehiculo: [],
        id_tipo_combustible: '',
        id_area: '',
    }
}

function generarPDF() {
    if (!filtros.value.fecha_inicio || !filtros.value.fecha_fin) {
        alert('Por favor, selecciona un rango de fechas')
        return
    }

    window.open(route('operacion-diaria.reporte.uso.pdf', {
        fecha_inicio: filtros.value.fecha_inicio,
        fecha_fin: filtros.value.fecha_fin,
        id_vehiculo: filtros.value.id_vehiculo.length ? filtros.value.id_vehiculo : undefined,
        id_tipo_combustible: filtros.value.id_tipo_combustible || undefined,
        id_area: filtros.value.id_area || undefined,
    }), '_blank')
}

/* ------------------------------------------------------------------ */
/*  Formato                                                            */
/* ------------------------------------------------------------------ */
const num = (v) => Number(v ?? 0)
const nf = (v, d = 2) => num(v).toLocaleString('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: d })
const horas = (v) => `${nf(v)} h`
const fechaCorta = (v) => {
    if (!v) return '—'
    const d = new Date(String(v).replace(' ', 'T'))
    return isNaN(d) ? '—' : d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const vehiculosResumen = computed(() => props.datosResumen?.vehiculos ?? [])
const totales = computed(() => props.datosResumen?.totales ?? {})
const hayDatos = computed(() => vehiculosResumen.value.length > 0)

// Promedio de horas por operación a nivel global (se calcula en el cliente a
// partir de los totales que ya vienen agregados de la BD, no fila por fila).
const promedioHorasGlobal = computed(() => {
    const ops = num(totales.value.total_operaciones)
    return ops > 0 ? num(totales.value.total_horas) / ops : 0
})

/* ------------------------------------------------------------------ */
/*  Gráficos comparativos                                              */
/* ------------------------------------------------------------------ */
const { paleta } = useTemaGraficos()

const grafBar = ref(null)
const grafDonutCombustible = ref(null)
const grafDonutTurno = ref(null)

const TOPE_VEHICULOS = 15

const metricaVehiculo = ref('total_horas')
const METRICAS_VEHICULO = {
    total_horas: { titulo: 'Horas Trabajadas por Vehículo', serie: 'Horas', fmt: (v) => `${nf(v)} h` },
    total_recorrido: { titulo: 'Recorrido por Vehículo (km / h de horómetro)', serie: 'Recorrido', fmt: (v) => nf(v) },
    total_operaciones: { titulo: 'Operaciones Registradas por Vehículo', serie: 'Operaciones', fmt: (v) => `${nf(v, 0)}` },
    dias_operados: { titulo: 'Días con Operación por Vehículo', serie: 'Días', fmt: (v) => `${nf(v, 0)}` },
    promedio_horas: { titulo: 'Promedio de Horas por Operación', serie: 'Horas/operación', fmt: (v) => `${nf(v)} h` },
}

const vehiculosGrafico = computed(() => {
    const m = metricaVehiculo.value
    const ordenados = [...vehiculosResumen.value].sort((a, b) => num(b[m]) - num(a[m]))
    // Al comparar una selección explícita se muestran todos los vehículos; sin
    // selección se limita a los 15 con mayor valor para que siga siendo legible.
    return filtros.value.id_vehiculo.length ? ordenados : ordenados.slice(0, TOPE_VEHICULOS)
})

const barSeries = computed(() => [{
    name: METRICAS_VEHICULO[metricaVehiculo.value].serie,
    data: vehiculosGrafico.value.map((v) => Math.round(num(v[metricaVehiculo.value]) * 100) / 100),
}])

const barOptions = computed(() => {
    const meta = METRICAS_VEHICULO[metricaVehiculo.value]
    const horizontal = vehiculosGrafico.value.length > 6
    const unidades = vehiculosGrafico.value.map((v) => v.unidad_recorrido)

    return {
        chart: { type: 'bar', toolbar: { show: false } },
        colors: [paleta.value[0]],
        plotOptions: { bar: { horizontal, borderRadius: 4, columnWidth: '55%', barHeight: '65%' } },
        dataLabels: {
            enabled: vehiculosGrafico.value.length <= 12,
            formatter: (v, opts) => metricaVehiculo.value === 'total_recorrido'
                ? `${nf(v)} ${unidades[opts.dataPointIndex] ?? ''}`
                : meta.fmt(v),
            offsetY: horizontal ? 0 : -18,
            style: { fontSize: '10px' },
        },
        xaxis: { categories: vehiculosGrafico.value.map((v) => v.codigo || v.nro_placa || '—') },
        yaxis: { title: { text: horizontal ? '' : meta.serie } },
        tooltip: {
            y: {
                formatter: (v, opts) => metricaVehiculo.value === 'total_recorrido'
                    ? `${nf(v)} ${unidades[opts.dataPointIndex] ?? ''}`
                    : meta.fmt(v),
            },
        },
        grid: { strokeDashArray: 4 },
        legend: { show: false },
    }
})

// Donut: horas trabajadas repartidas por tipo de combustible del vehículo.
const horasPorCombustible = computed(() => {
    const acumulado = {}
    for (const v of vehiculosResumen.value) {
        const tipo = v.tipo_combustible || 'Sin tipo'
        acumulado[tipo] = (acumulado[tipo] ?? 0) + num(v.total_horas)
    }
    return {
        labels: Object.keys(acumulado),
        series: Object.values(acumulado).map((x) => Math.round(x * 100) / 100),
    }
})

const mostrarDonutCombustible = computed(() => horasPorCombustible.value.labels.length > 1)

const donutCombustibleOptions = computed(() => ({
    chart: { type: 'donut', toolbar: { show: false } },
    labels: horasPorCombustible.value.labels,
    colors: paleta.value,
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (val) => `${Number(val).toFixed(1)}%` },
    plotOptions: { pie: { donut: { size: '62%' } } },
    tooltip: { y: { formatter: (v) => horas(v) } },
    stroke: { width: 2 },
}))

// Donut: operaciones por turno (día / noche), directo de los totales de la BD.
const turnoSeries = computed(() => [
    num(totales.value.operaciones_dia),
    num(totales.value.operaciones_noche),
])
const mostrarDonutTurno = computed(() => turnoSeries.value.some((x) => x > 0))
const donutTurnoOptions = computed(() => ({
    chart: { type: 'donut', toolbar: { show: false } },
    labels: ['Día', 'Noche'],
    colors: [paleta.value[2] ?? '#f7b924', paleta.value[5] ?? '#6c757d'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (val) => `${Number(val).toFixed(1)}%` },
    plotOptions: { pie: { donut: { size: '62%' } } },
    tooltip: { y: { formatter: (v) => `${nf(v, 0)} operaciones` } },
    stroke: { width: 2 },
}))
</script>

<template>

    <Head title="Reporte de Operación Diaria" />
    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item active">Reportes</li>
                    <li class="breadcrumb-item active">Operación Diaria</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Reporte de Uso de Vehículos — Operación Diaria</h1>
            <p class="text-muted fs-13 mb-0">
                Horas trabajadas y recorrido (kilometraje u horómetro) por vehículo y tipo de combustible.
            </p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card custom-card mb-4">
        <div class="card-header">
            <div class="card-title">Filtros de Búsqueda</div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-sm-6 col-lg-4">
                    <DateRangeFilter v-model:fecha-desde="filtros.fecha_inicio" v-model:fecha-hasta="filtros.fecha_fin"
                        label="Rango de fechas" default-range="Este mes" />
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label">Tipo de Combustible</label>
                    <select v-model="filtros.id_tipo_combustible" class="form-select">
                        <option value="">Todos</option>
                        <option v-for="t in tiposCombustible" :key="t.id" :value="t.id">{{ t.tipo_combustible }}
                        </option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-4">
                    <label class="form-label">Área</label>
                    <select v-model="filtros.id_area" class="form-select">
                        <option value="">Todas</option>
                        <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.nombre_area }}</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Vehículos <span class="text-muted fw-normal">(seleccione 1 o más para
                            comparar; vacío = todos)</span></label>
                    <Multiselect v-model="filtros.id_vehiculo" mode="tags" :close-on-select="false" :searchable="true"
                        :options="vehiculoOptions" placeholder="Todos los vehículos" no-options-text="No hay vehículos"
                        no-results-text="Sin resultados" />
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-wave" @click="clearFilters">
                        <i class="ri-refresh-line me-1"></i> Limpiar
                    </button>
                    <button v-can="'operacion-diaria.reporte.uso.pdf'" type="button" class="btn btn-primary btn-wave"
                        @click="generarPDF">
                        <i class="ri-file-pdf-line me-1"></i> PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Resumen -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Vehículos Operados</p>
                            <h3 class="fw-bold mb-0">{{ nf(totales.total_vehiculos, 0) }}</h3>
                        </div>
                        <div class="avatar avatar-lg bg-warning-transparent">
                            <i class="ri-truck-line fs-24 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Operaciones</p>
                            <h3 class="fw-bold mb-0">{{ nf(totales.total_operaciones, 0) }}</h3>
                            <small class="text-muted">
                                {{ nf(totales.operaciones_dia, 0) }} día · {{ nf(totales.operaciones_noche, 0) }} noche
                            </small>
                        </div>
                        <div class="avatar avatar-lg bg-info-transparent">
                            <i class="ri-calendar-check-line fs-24 text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Horas Trabajadas</p>
                            <h3 class="fw-bold mb-0">{{ nf(totales.total_horas) }} h</h3>
                            <small class="text-muted">Prom. {{ nf(promedioHorasGlobal) }} h / operación</small>
                        </div>
                        <div class="avatar avatar-lg bg-primary-transparent">
                            <i class="ri-time-line fs-24 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Recorrido Total</p>
                            <h3 class="fw-bold mb-0">{{ nf(totales.total_km) }} km</h3>
                            <small class="text-muted">+ {{ nf(totales.total_horometro) }} h de horómetro</small>
                        </div>
                        <div class="avatar avatar-lg bg-success-transparent">
                            <i class="ri-route-line fs-24 text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráficos comparativos -->
    <div v-if="hayDatos" class="row g-3 mb-4">
        <div class="col-12">
            <div class="card custom-card h-100">
                <div class="card-header justify-content-between flex-wrap gap-2">
                    <div class="card-title">{{ METRICAS_VEHICULO[metricaVehiculo].titulo }}</div>
                    <div class="d-flex align-items-center gap-2">
                        <select v-model="metricaVehiculo" class="form-select form-select-sm" style="width: auto;">
                            <option value="total_horas">Horas trabajadas</option>
                            <option value="total_recorrido">Recorrido</option>
                            <option value="total_operaciones">Operaciones</option>
                            <option value="dias_operados">Días operados</option>
                            <option value="promedio_horas">Prom. horas / operación</option>
                        </select>
                        <BotonDescargarGrafico :grafico="grafBar" :nombre="`operacion-diaria-${metricaVehiculo}`"
                            :titulo="METRICAS_VEHICULO[metricaVehiculo].titulo"
                            subtitulo="Reporte de Operación Diaria" />
                    </div>
                </div>
                <div class="card-body">
                    <Apexchart ref="grafBar" type="bar" height="360" :options="barOptions" :series="barSeries" />
                    <p v-if="!filtros.id_vehiculo.length && vehiculosResumen.length > TOPE_VEHICULOS"
                        class="text-muted fs-12 mb-0 mt-2">
                        Mostrando los {{ TOPE_VEHICULOS }} vehículos con mayor valor. La tabla lista todos.
                    </p>
                </div>
            </div>
        </div>
        <div v-if="mostrarDonutCombustible" class="col-xl-4">
            <div class="card custom-card h-100">
                <div class="card-header justify-content-between">
                    <div class="card-title">Horas por Tipo de Combustible</div>
                    <BotonDescargarGrafico :grafico="grafDonutCombustible" nombre="operacion-diaria-por-combustible"
                        titulo="Horas por Tipo de Combustible" subtitulo="Reporte de Operación Diaria" />
                </div>
                <div class="card-body d-flex justify-content-center">
                    <div style="width: 100%; max-width: 320px;">
                        <Apexchart ref="grafDonutCombustible" type="donut" height="320"
                            :options="donutCombustibleOptions" :series="horasPorCombustible.series" />
                    </div>
                </div>
            </div>
        </div>

        <div  v-if="hayDatos && mostrarDonutTurno" class="col-xl-4">
            <div class="card custom-card h-100">
                <div class="card-header justify-content-between">
                    <div class="card-title">Operaciones por Turno</div>
                    <BotonDescargarGrafico :grafico="grafDonutTurno" nombre="operacion-diaria-por-turno"
                        titulo="Operaciones por Turno" subtitulo="Reporte de Operación Diaria" />
                </div>
                <div class="card-body d-flex justify-content-center">
                    <div style="width: 100%; max-width: 300px;">
                        <Apexchart ref="grafDonutTurno" type="donut" height="300" :options="donutTurnoOptions"
                            :series="turnoSeries" />
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Tabla de Detalle por Vehículo -->
    <div class="card custom-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title">
                Detalle por Vehículo
                <span class="badge bg-primary-transparent text-primary ms-2">{{ vehiculosResumen.length }}
                    vehículos</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Código</th>
                            <th>Placa</th>
                            <th>Marca</th>
                            <th>Combustible</th>
                            <th>Medición</th>
                            <th class="text-center">Operaciones</th>
                            <th class="text-center">Días</th>
                            <th class="text-end">Horas Trab.</th>
                            <th class="text-end">Prom. h/op</th>
                            <th class="text-end">Recorrido</th>
                            <th class="text-center">Última Op.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!hayDatos">
                            <td colspan="12" class="text-center py-4 text-muted">
                                <i class="ri-inbox-line fs-3 d-block mb-2"></i>
                                No hay operaciones diarias en el rango seleccionado
                            </td>
                        </tr>
                        <tr v-for="(v, idx) in vehiculosResumen" :key="v.id_vehiculo">
                            <td>{{ idx + 1 }}</td>
                            <td><span class="badge bg-secondary-transparent text-secondary">{{ v.codigo }}</span></td>
                            <td class="fw-medium">{{ v.nro_placa ?? "" }}</td>
                            <td>{{ v.marca }}</td>
                            <td><span class="badge bg-warning-transparent text-warning">{{ v.tipo_combustible }}</span>
                            </td>
                            <td>
                                <span class="badge" :class="v.tipo_medicion === 'kilometraje'
                                    ? 'bg-info-transparent text-info' : 'bg-primary-transparent text-primary'">
                                    {{ v.tipo_medicion === 'kilometraje' ? 'Kilometraje' : 'Horómetro' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info-transparent text-info">{{ v.total_operaciones }}</span>
                                <small class="text-muted ms-1">{{ v.operaciones_dia }}d / {{ v.operaciones_noche
                                    }}n</small>
                            </td>
                            <td class="text-center">{{ v.dias_operados }}</td>
                            <td class="text-end fw-medium">{{ nf(v.total_horas) }} h</td>
                            <td class="text-end">{{ nf(v.promedio_horas) }} h</td>
                            <td class="text-end">{{ nf(v.total_recorrido) }} {{ v.unidad_recorrido }}</td>
                            <td class="text-center">{{ fechaCorta(v.ultima_operacion) }}</td>
                        </tr>
                        <!-- Fila de totales -->
                        <tr v-if="hayDatos" class="table-active fw-bold">
                            <td colspan="6">TOTALES</td>
                            <td class="text-center">{{ totales.total_operaciones }}</td>
                            <td class="text-center">—</td>
                            <td class="text-end">{{ nf(totales.total_horas) }} h</td>
                            <td class="text-end">{{ nf(promedioHorasGlobal) }} h</td>
                            <td class="text-end">{{ nf(totales.total_km) }} km · {{ nf(totales.total_horometro) }} h
                            </td>
                            <td class="text-center">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
