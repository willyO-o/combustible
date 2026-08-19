<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import Multiselect from '@vueform/multiselect'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculos: Array,
    resultado: Array,
    filtros: Object,
})

const filtros = ref({
    fecha_inicio: props.filtros?.fecha_inicio?.substring(0, 10) ?? '',
    fecha_fin: props.filtros?.fecha_fin?.substring(0, 10) ?? '',
    id_vehiculo: (props.filtros?.id_vehiculo ?? []).map((id) => Number(id)),
})

const vehiculoOptions = computed(() =>
    props.vehiculos.map((v) => ({
        value: v.id,
        label: `${v.codigo} — ${v.nro_placa}${v.marca ? ' — ' + v.marca : ''}`,
    })),
)

let debounceTimer = null
watch(filtros, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('cargas-combustible.reporte.rendimiento'), {
            fecha_inicio: val.fecha_inicio || undefined,
            fecha_fin: val.fecha_fin || undefined,
            id_vehiculo: val.id_vehiculo.length ? val.id_vehiculo : undefined,
        }, { preserveState: true, replace: true })
    }, 500)
}, { deep: true })

function clearFilters() {
    filtros.value = { fecha_inicio: '', fecha_fin: '', id_vehiculo: [] }
}

function generarPDF() {
    if (!filtros.value.fecha_inicio || !filtros.value.fecha_fin) {
        alert('Por favor, selecciona un rango de fechas')
        return
    }

    const url = route('cargas-combustible.reporte.rendimiento.pdf', {
        fecha_inicio: filtros.value.fecha_inicio,
        fecha_fin: filtros.value.fecha_fin,
        id_vehiculo: filtros.value.id_vehiculo.length ? filtros.value.id_vehiculo : undefined,
    })

    window.open(url, '_blank')
}

// El backend devuelve los agregados como strings decimales (p.ej. "18.70").
const num = (v) => Number(v ?? 0)

// Un vehículo sin al menos 2 lecturas consecutivas de medición dentro del
// rango produce recorrido/rendimiento en 0: no es "rendimiento cero", es
// que no hay datos suficientes para calcularlo. Lo distinguimos en la UI
// para no dar a entender que el vehículo rindió 0 km/L o 0 L/h.
const sinDatosSuficientes = (r) => num(r.total_recorrido) === 0

const unidadLabel = (tipoMedicion) => (tipoMedicion === 'horometro' ? 'L/h' : 'km/L')
const tipoMedicionLabel = (tipoMedicion) => (tipoMedicion === 'horometro' ? 'Horómetro' : 'Kilometraje')

const totalVehiculos = computed(() => props.resultado.length)
const totalLitros = computed(() => props.resultado.reduce((acc, r) => acc + num(r.total_litros), 0))
const totalCargas = computed(() => props.resultado.reduce((acc, r) => acc + num(r.total_cargas), 0))

// Los vehículos por kilometraje (km/L) y por horómetro (L/h) no son
// comparables entre sí, así que se agrupan y se grafican por separado.
const gruposPorMedicion = computed(() => {
    const grupos = {}
    for (const r of props.resultado) {
        const key = r.tipo_medicion ?? 'kilometraje'
        if (!grupos[key]) grupos[key] = []
        grupos[key].push(r)
    }
    return grupos
})

function chartOptions(tipoMedicion) {
    return {
        chart: { type: 'bar', toolbar: { show: false } },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '45%', dataLabels: { position: 'top' } } },
        dataLabels: {
            enabled: true,
            offsetY: -18,
            formatter: (val) => `${val} ${unidadLabel(tipoMedicion)}`,
            style: { fontSize: '11px' },
        },
        xaxis: { categories: gruposPorMedicion.value[tipoMedicion]?.filter((r) => !sinDatosSuficientes(r)).map((r) => `${r.codigo}\n${r.nro_placa}`) ?? [] },
        yaxis: { title: { text: `Rendimiento (${unidadLabel(tipoMedicion)})` } },
        colors: ['#187daa'],
        tooltip: { y: { formatter: (val) => `${val} ${unidadLabel(tipoMedicion)}` } },
        grid: { strokeDashArray: 4 },
    }
}

function chartSeries(tipoMedicion) {
    const datos = (gruposPorMedicion.value[tipoMedicion] ?? []).filter((r) => !sinDatosSuficientes(r))
    return [{ name: 'Rendimiento promedio', data: datos.map((r) => num(r.rendimiento_promedio)) }]
}
</script>

<template>
    <Head title="Reporte de Rendimiento" />
    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item"><Link :href="route('cargas-combustible.reporte.index')">Reportes</Link></li>
                    <li class="breadcrumb-item active">Rendimiento de Combustible</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">Reporte de Rendimiento de Combustible</h1>
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
                <div class="col-lg-3">
                    <label class="form-label">Vehículos (para comparar, seleccione 1 o más)</label>
                    <Multiselect
                        v-model="filtros.id_vehiculo"
                        mode="tags"
                        :close-on-select="false"
                        :searchable="true"
                        :options="vehiculoOptions"
                        placeholder="Todos los vehículos"
                        no-options-text="No hay vehículos"
                        no-results-text="Sin resultados"
                    />
                </div>
                <div class="col-lg-3 d-flex align-items-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-wave flex-grow-1" @click="clearFilters">
                        <i class="ri-refresh-line me-1"></i> Limpiar
                    </button>
                    <button type="button" class="btn btn-primary btn-wave flex-grow-1" @click="generarPDF">
                        <i class="ri-file-pdf-line me-1"></i> PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjetas de Resumen -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Vehículos Comparados</p>
                            <h3 class="fw-bold mb-0">{{ totalVehiculos }}</h3>
                        </div>
                        <div class="avatar avatar-lg bg-warning-transparent">
                            <i class="ri-car-line fs-24 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Total de Litros</p>
                            <h3 class="fw-bold mb-0">{{ totalLitros.toLocaleString('es-ES', { maximumFractionDigits: 2 }) }} L</h3>
                        </div>
                        <div class="avatar avatar-lg bg-info-transparent">
                            <i class="ri-water-flash-line fs-24 text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted mb-1">Total de Cargas</p>
                            <h3 class="fw-bold mb-0">{{ totalCargas }}</h3>
                        </div>
                        <div class="avatar avatar-lg bg-primary-transparent">
                            <i class="ri-gas-station-line fs-24 text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estado vacío -->
    <div v-if="resultado.length === 0" class="card custom-card mb-4">
        <div class="card-body text-center py-5 text-muted">
            <i class="ri-bar-chart-2-line fs-3 d-block mb-2"></i>
            No hay datos de rendimiento para el rango y los vehículos seleccionados.
        </div>
    </div>

    <!-- Gráficos comparativos (uno por tipo de medición: no son comparables entre sí) -->
    <div v-else class="row g-3 mb-4">
        <div v-for="(grupo, tipoMedicion) in gruposPorMedicion" :key="tipoMedicion" class="col-xl-6">
            <div class="card custom-card h-100">
                <div class="card-header">
                    <div class="card-title">
                        Comparativa de Rendimiento — {{ tipoMedicionLabel(tipoMedicion) }}
                        <span class="badge bg-secondary-transparent text-secondary ms-1">{{ unidadLabel(tipoMedicion) }}</span>
                    </div>
                </div>
                <div class="card-body">
                    <Apexchart type="bar" height="300" :options="chartOptions(tipoMedicion)" :series="chartSeries(tipoMedicion)" />
                    <p v-if="grupo.some(sinDatosSuficientes)" class="text-muted fs-12 mb-0 mt-2">
                        <i class="ri-information-line me-1"></i>
                        Los vehículos sin datos suficientes no se incluyen en el gráfico (ver tabla).
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Detalle -->
    <div class="card custom-card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div class="card-title">
                Detalle por Vehículo
                <span class="badge bg-primary-transparent text-primary ms-2">{{ resultado.length }} vehículos</span>
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
                            <th>Tipo de Medición</th>
                            <th class="text-center">Cargas</th>
                            <th class="text-end">Litros</th>
                            <th class="text-end">Recorrido / Horas</th>
                            <th class="text-end">Rendimiento</th>
                            <th class="text-center">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="resultado.length === 0">
                            <td colspan="9" class="text-center py-4 text-muted">
                                <i class="ri-oil-line fs-3 d-block mb-2"></i>
                                No se encontraron registros de rendimiento
                            </td>
                        </tr>
                        <tr v-for="(r, idx) in resultado" :key="r.id_vehiculo">
                            <td>{{ idx + 1 }}</td>
                            <td><span class="badge bg-secondary-transparent text-secondary">{{ r.codigo }}</span></td>
                            <td class="fw-medium">{{ r.nro_placa }}</td>
                            <td>
                                <span class="badge" :class="r.tipo_medicion === 'horometro' ? 'bg-purple-transparent text-purple' : 'bg-info-transparent text-info'">
                                    {{ tipoMedicionLabel(r.tipo_medicion) }}
                                </span>
                            </td>
                            <td class="text-center">{{ r.total_cargas }}</td>
                            <td class="text-end">{{ num(r.total_litros).toLocaleString('es-ES', { maximumFractionDigits: 2 }) }} L</td>
                            <td class="text-end">{{ num(r.total_recorrido).toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}</td>
                            <td class="text-end">
                                <span v-if="sinDatosSuficientes(r)" class="badge bg-secondary-transparent text-secondary" title="No hay al menos 2 lecturas consecutivas de medición en el rango seleccionado">
                                    Sin datos suficientes
                                </span>
                                <span v-else class="fw-medium">{{ num(r.rendimiento_promedio).toLocaleString('es-ES', { maximumFractionDigits: 2 }) }} {{ r.unidad_medida }}</span>
                            </td>
                            <td class="text-center">
                                <Link
                                    :href="route('cargas-combustible.reporte.rendimiento.detalle', {
                                        id_vehiculo: r.id_vehiculo,
                                        fecha_inicio: filtros.fecha_inicio,
                                        fecha_fin: filtros.fecha_fin,
                                    })"
                                    class="btn btn-sm btn-icon btn-info-light"
                                    title="Ver detalle de cargas"
                                >
                                    <i class="ri-eye-line"></i>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
