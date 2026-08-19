<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculos: Array,
    detalle: Array,
    filtros: Object,
})

const filtros = ref({
    fecha_inicio: props.filtros?.fecha_inicio?.substring(0, 10) ?? '',
    fecha_fin: props.filtros?.fecha_fin?.substring(0, 10) ?? '',
    id_vehiculo: props.filtros?.id_vehiculo ?? '',
})

let debounceTimer = null
watch(filtros, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('cargas-combustible.reporte.rendimiento.detalle'), {
            fecha_inicio: val.fecha_inicio || undefined,
            fecha_fin: val.fecha_fin || undefined,
            id_vehiculo: val.id_vehiculo || undefined,
        }, { preserveState: true, replace: true })
    }, 400)
}, { deep: true })

const vehiculoSeleccionado = computed(() =>
    props.vehiculos.find((v) => v.id === Number(filtros.value.id_vehiculo)) ?? null,
)

function generarPDF() {
    if (!filtros.value.id_vehiculo) {
        alert('Por favor, selecciona un vehículo')
        return
    }
    if (!filtros.value.fecha_inicio || !filtros.value.fecha_fin) {
        alert('Por favor, selecciona un rango de fechas')
        return
    }

    const url = route('cargas-combustible.reporte.rendimiento.detalle.pdf', {
        fecha_inicio: filtros.value.fecha_inicio,
        fecha_fin: filtros.value.fecha_fin,
        id_vehiculo: filtros.value.id_vehiculo,
    })

    window.open(url, '_blank')
}

// El backend devuelve los valores como strings decimales (p.ej. "18.70").
const num = (v) => Number(v ?? 0)
const unidadLabel = (tipoMedicion) => (tipoMedicion === 'horometro' ? 'L/h' : 'km/L')
const tipoMedicionLabel = (tipoMedicion) => (tipoMedicion === 'horometro' ? 'Horómetro' : 'Kilometraje')

const totalCargas = computed(() => props.detalle.length)
const totalLitros = computed(() => props.detalle.reduce((acc, d) => acc + num(d.litros), 0))
const totalRecorrido = computed(() => props.detalle.reduce((acc, d) => acc + num(d.recorrido), 0))

// Mismo criterio que el backend: km/L = recorrido/litros, L/h = litros/recorrido.
const rendimientoPromedio = computed(() => {
    if (!vehiculoSeleccionado.value || totalLitros.value === 0) return 0
    return vehiculoSeleccionado.value.tipo_medicion === 'horometro'
        ? (totalRecorrido.value > 0 ? totalLitros.value / totalRecorrido.value : 0)
        : (totalLitros.value > 0 ? totalRecorrido.value / totalLitros.value : 0)
})

function formatFecha(fecha) {
    if (!fecha) return '—'
    return new Date(fecha).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const chartOptions = computed(() => ({
    chart: { type: 'line', toolbar: { show: false } },
    stroke: { curve: 'smooth', width: 3 },
    markers: { size: 5 },
    colors: ['#187daa'],
    xaxis: { categories: props.detalle.map((d) => formatFecha(d.fecha_carga)) },
    yaxis: { title: { text: `Rendimiento (${unidadLabel(vehiculoSeleccionado.value?.tipo_medicion)})` } },
    dataLabels: { enabled: true, formatter: (val) => Number(val).toFixed(2) },
    tooltip: { y: { formatter: (val) => `${Number(val).toFixed(2)} ${unidadLabel(vehiculoSeleccionado.value?.tipo_medicion)}` } },
    grid: { strokeDashArray: 4 },
}))

const chartSeries = computed(() => [
    { name: 'Rendimiento por carga', data: props.detalle.map((d) => num(d.rendimiento)) },
])
</script>

<template>
    <Head title="Detalle de Rendimiento" />
    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item"><Link :href="route('cargas-combustible.reporte.index')">Reportes</Link></li>
                    <li class="breadcrumb-item"><Link :href="route('cargas-combustible.reporte.rendimiento')">Rendimiento de Combustible</Link></li>
                    <li class="breadcrumb-item active">Detalle</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                Detalle de Rendimiento
                <span v-if="vehiculoSeleccionado" class="text-primary">— {{ vehiculoSeleccionado.codigo }} ({{ vehiculoSeleccionado.nro_placa }})</span>
                <span v-if="vehiculoSeleccionado?.tipo_combustible" class="badge bg-info-transparent text-info ms-1">
                    {{ vehiculoSeleccionado.tipo_combustible.tipo_combustible }}
                </span>
            </h1>
        </div>
        <div class="d-flex gap-2">
            <button v-if="filtros.id_vehiculo" type="button" class="btn btn-primary btn-wave" @click="generarPDF">
                <i class="ri-file-pdf-line me-1"></i> PDF
            </button>
            <Link :href="route('cargas-combustible.reporte.rendimiento')" class="btn btn-outline-secondary btn-wave">
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
                    <label class="form-label">Fecha Inicio</label>
                    <input v-model="filtros.fecha_inicio" type="date" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label">Fecha Fin</label>
                    <input v-model="filtros.fecha_fin" type="date" class="form-control">
                </div>
                <div class="col-lg-6">
                    <label class="form-label">Vehículo <span class="text-danger">*</span></label>
                    <select v-model="filtros.id_vehiculo" class="form-select">
                        <option value="">— Seleccionar un vehículo —</option>
                        <option v-for="v in vehiculos" :key="v.id" :value="v.id">
                            {{ v.codigo }} — {{ v.nro_placa }}{{ v.marca ? ' — ' + v.marca : '' }}{{ v.tipo_combustible ? ' · ' + v.tipo_combustible.tipo_combustible : '' }}
                        </option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Estado vacío: sin vehículo seleccionado -->
    <div v-if="!filtros.id_vehiculo" class="card custom-card">
        <div class="card-body text-center py-5 text-muted">
            <i class="ri-car-line fs-3 d-block mb-2"></i>
            Selecciona un vehículo para ver el detalle de sus cargas y rendimiento.
        </div>
    </div>

    <template v-else>
        <!-- Tarjetas de Resumen -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Cargas en el Rango</p>
                        <h3 class="fw-bold mb-0">{{ totalCargas }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Total de Litros</p>
                        <h3 class="fw-bold mb-0">{{ totalLitros.toLocaleString('es-ES', { maximumFractionDigits: 2 }) }} L</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ vehiculoSeleccionado?.tipo_medicion === 'horometro' ? 'Total Horas' : 'Total Recorrido' }}</p>
                        <h3 class="fw-bold mb-0">{{ totalRecorrido.toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <p class="text-muted mb-1">Rendimiento Promedio</p>
                        <h3 class="fw-bold mb-0">
                            {{ rendimientoPromedio.toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}
                            {{ unidadLabel(vehiculoSeleccionado?.tipo_medicion) }}
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sin cargas con datos suficientes -->
        <div v-if="detalle.length === 0" class="card custom-card mb-4">
            <div class="card-body text-center py-5 text-muted">
                <i class="ri-bar-chart-2-line fs-3 d-block mb-2"></i>
                No hay cargas con una medición anterior disponible en este rango: se necesitan al menos 2
                cargas consecutivas con lectura de {{ vehiculoSeleccionado?.tipo_medicion === 'horometro' ? 'horómetro' : 'kilometraje' }} para calcular el rendimiento.
            </div>
        </div>

        <template v-else>
            <!-- Tendencia de rendimiento -->
            <div class="card custom-card mb-4">
                <div class="card-header">
                    <div class="card-title">Tendencia de Rendimiento por Carga</div>
                </div>
                <div class="card-body">
                    <Apexchart type="line" height="300" :options="chartOptions" :series="chartSeries" />
                </div>
            </div>

            <!-- Tabla de Detalle -->
            <div class="card custom-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="card-title">
                        Cargas del Vehículo
                        <span class="badge bg-primary-transparent text-primary ms-2">{{ detalle.length }} registros</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover text-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Fecha de Carga</th>
                                    <th class="text-end">Litros</th>
                                    <th class="text-end">Medición Anterior</th>
                                    <th class="text-end">Medición Actual</th>
                                    <th class="text-end">{{ vehiculoSeleccionado?.tipo_medicion === 'horometro' ? 'Horas' : 'Recorrido' }}</th>
                                    <th class="text-end">Rendimiento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(d, idx) in detalle" :key="d.id_carga">
                                    <td>{{ idx + 1 }}</td>
                                    <td>{{ formatFecha(d.fecha_carga) }}</td>
                                    <td class="text-end">{{ num(d.litros).toLocaleString('es-ES', { maximumFractionDigits: 2 }) }} L</td>
                                    <td class="text-end text-muted">{{ num(d.medicion_anterior).toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}</td>
                                    <td class="text-end">{{ num(d.medicion_actual).toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}</td>
                                    <td class="text-end">{{ num(d.recorrido).toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}</td>
                                    <td class="text-end fw-medium">{{ num(d.rendimiento).toLocaleString('es-ES', { maximumFractionDigits: 2 }) }} {{ unidadLabel(d.tipo_medicion) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </template>
</template>
