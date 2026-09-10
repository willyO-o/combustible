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
    vehiculo: Object,
    datos: Object,
    filtros: Object,
})

const filtros = ref({
    fecha_inicio: props.filtros?.fecha_inicio ?? '',
    fecha_fin: props.filtros?.fecha_fin ?? '',
    id_vehiculo: props.filtros?.id_vehiculo ?? '',
})

// Un solo vehículo a la vez (esta bitácora es por vehículo).
const vehiculoOptions = computed(() =>
    props.vehiculos.map((v) => ({
        id: v.id,
        label: `${v.codigo} — ${v.nro_placa ?? 'Sin placa'}${v.marca ? ' — ' + v.marca : ''}`,
    })),
)

let debounceTimer = null
watch(filtros, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('operacion-diaria.reporte.detalle.index'), {
            fecha_inicio: val.fecha_inicio,
            fecha_fin: val.fecha_fin,
            id_vehiculo: val.id_vehiculo || undefined,
        }, { preserveState: true, replace: true })
    }, 500)
}, { deep: true })

function generarPDF() {
    if (! filtros.value.id_vehiculo) {
        alert('Selecciona un vehículo')
        return
    }
    if (! filtros.value.fecha_inicio || ! filtros.value.fecha_fin) {
        alert('Selecciona un rango de fechas')
        return
    }

    window.open(route('operacion-diaria.reporte.detalle.pdf', {
        fecha_inicio: filtros.value.fecha_inicio,
        fecha_fin: filtros.value.fecha_fin,
        id_vehiculo: filtros.value.id_vehiculo,
    }), '_blank')
}

/**
 * Misma bitácora que generarPDF(), en Excel. El .xlsx viaja como adjunto, así
 * que se navega a la URL (el navegador descarga sin abrir una pestaña vacía).
 */
function generarExcel() {
    if (! filtros.value.id_vehiculo) {
        alert('Selecciona un vehículo')
        return
    }
    if (! filtros.value.fecha_inicio || ! filtros.value.fecha_fin) {
        alert('Selecciona un rango de fechas')
        return
    }

    window.location.href = route('operacion-diaria.reporte.detalle.excel', {
        fecha_inicio: filtros.value.fecha_inicio,
        fecha_fin: filtros.value.fecha_fin,
        id_vehiculo: filtros.value.id_vehiculo,
    })
}

/* ------------------------------------------------------------------ */
/*  Formato                                                            */
/* ------------------------------------------------------------------ */
const n = (v, d = 2) => (v === null || v === undefined || v === '')
    ? '—'
    : Number(v).toLocaleString('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: d })
const bs = (v) => (v === null || v === undefined) ? '—' : `Bs. ${Number(v).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const fechaCorta = (v) => {
    if (! v) return '—'
    const d = new Date(String(v).replace(' ', 'T') + 'T00:00:00')
    return isNaN(d) ? v : d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

const esKm = computed(() => props.vehiculo?.tipo_medicion === 'kilometraje')
const lecturaLabel = computed(() => (esKm.value ? 'Kilometraje' : 'Horómetro'))

const colsMant = computed(() => props.datos?.columnas?.mantenimiento ?? [])
const colsMat = computed(() => props.datos?.columnas?.material ?? [])
const filas = computed(() => props.datos?.filas ?? [])
const totales = computed(() => props.datos?.totales ?? {
    operaciones: 0, horas_trabajadas: 0, litros: 0, costo: 0, litros_por_hora: 0,
})
const hayFilas = computed(() => filas.value.length > 0)

const valorMantenimiento = (fila, col) => {
    const m = fila.mantenimientos?.[col.id]
    if (! m) return '—'
    if (col.tipo_valor === 'booleano') {
        return m.realizado === 'SI' ? 'Sí' : (m.realizado === 'NO' ? 'No' : '—')
    }
    return m.valor === null || m.valor === undefined ? '—' : n(m.valor)
}

const totalMantenimiento = (col) => col.tipo_valor === 'booleano' ? `${col.total} sí` : n(col.total)

/* ------------------------------------------------------------------ */
/*  Material trasladado (sólo vehículos por kilometraje)               */
/* ------------------------------------------------------------------ */
const { paleta } = useTemaGraficos()
const grafMaterial = ref(null)

// Métrica del gráfico/porcentajes: cantidad total movida o nº de traslados
// registrados (unit-agnostic, útil cuando los materiales usan distinta unidad).
const metricaMaterial = ref('total')

const hayMaterial = computed(() => esKm.value && colsMat.value.length > 0)

const materialItems = computed(() =>
    [...colsMat.value].sort((a, b) => Number(b[metricaMaterial.value] ?? 0) - Number(a[metricaMaterial.value] ?? 0)),
)

const a2Decimales = (v) => Math.round((Number(v) || 0) * 100) / 100

const materialSeries = computed(() => materialItems.value.map((m) => a2Decimales(m[metricaMaterial.value])))

const totalCantidadMaterial = computed(() => colsMat.value.reduce((s, m) => s + Number(m.total ?? 0), 0))
const totalMovimientosMaterial = computed(() => colsMat.value.reduce((s, m) => s + Number(m.movimientos ?? 0), 0))
const totalMetricaMaterial = computed(() =>
    metricaMaterial.value === 'total' ? totalCantidadMaterial.value : totalMovimientosMaterial.value,
)

// Si todos los materiales comparten unidad, se muestra junto al total.
const unidadMaterialUnica = computed(() => {
    const unidades = new Set(colsMat.value.map((m) => m.unidad_medida).filter(Boolean))
    return unidades.size === 1 ? [...unidades][0] : null
})

const porcentajeMaterial = (valor) => {
    const t = totalMetricaMaterial.value
    return t > 0 ? `${((Number(valor) || 0) / t * 100).toFixed(1)}%` : '—'
}

const materialDonutOptions = computed(() => ({
    chart: { type: 'donut', toolbar: { show: false } },
    labels: materialItems.value.map((m) => m.nombre),
    colors: paleta.value,
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (val) => `${Number(val).toFixed(1)}%` },
    plotOptions: { pie: { donut: { size: '62%' } } },
    tooltip: {
        y: {
            formatter: (v) => metricaMaterial.value === 'total'
                ? `${n(v)}${unidadMaterialUnica.value ? ' ' + unidadMaterialUnica.value : ''}`
                : `${n(v, 0)} traslados`,
        },
    },
    stroke: { width: 2 },
}))
</script>

<template>
    <Head title="Bitácora por Vehículo — Operación Diaria" />

        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item active">Reportes</li>
                        <li class="breadcrumb-item active">Bitácora por Vehículo</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    Detalle de Horas Trabajadas y Consumo de Combustible
                    <span v-if="vehiculo" class="text-primary">— {{ vehiculo.codigo }} {{ vehiculo.marca }}</span>
                </h1>
                <p class="text-muted fs-13 mb-0">
                    Una fila por operación diaria del vehículo, con su operador, el combustible cargado ese día,
                    los controles de mantenimiento y (si mide por kilometraje) el material trasladado.
                </p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card custom-card mb-4">
            <div class="card-header"><div class="card-title">Filtros de Búsqueda</div></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-4">
                        <DateRangeFilter v-model:fecha-desde="filtros.fecha_inicio"
                            v-model:fecha-hasta="filtros.fecha_fin" label="Rango de fechas" default-range="Este mes" />
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label">Vehículo <span class="text-danger">*</span></label>
                        <Multiselect
                            v-model="filtros.id_vehiculo"
                            :options="vehiculoOptions"
                            value-prop="id"
                            label="label"
                            :searchable="true"
                            :filter-results="true"
                            placeholder="— Seleccionar un vehículo —"
                            no-options-text="Sin vehículos"
                            no-results-text="Sin resultados"
                        />
                    </div>
                    <div class="col-lg-2 d-flex align-items-end gap-2">
                        <button v-can="'operacion-diaria.reporte.detalle.pdf'" type="button"
                            class="btn btn-primary btn-wave flex-grow-1" :disabled="! filtros.id_vehiculo" @click="generarPDF">
                            <i class="ri-file-pdf-line me-1"></i> PDF
                        </button>
                        <button v-can="'operacion-diaria.reporte.detalle.excel'" type="button"
                            class="btn btn-success btn-wave flex-grow-1" :disabled="! filtros.id_vehiculo" @click="generarExcel">
                            <i class="ri-file-excel-2-line me-1"></i> Excel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado vacío: sin vehículo -->
        <div v-if="! vehiculo" class="card custom-card">
            <div class="card-body text-center py-5 text-muted">
                <i class="ri-truck-line fs-3 d-block mb-2"></i>
                Selecciona un vehículo y un rango de fechas para ver su bitácora.
            </div>
        </div>

        <template v-else>
            <!-- Cabecera del vehículo -->
            <div class="row g-3 mb-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="card custom-card"><div class="card-body py-2">
                        <p class="text-muted mb-1">Vehículo</p>
                        <h6 class="fw-bold mb-0">{{ vehiculo.codigo }} — {{ vehiculo.nro_placa ?? 'Sin placa' }}</h6>
                        <small class="text-muted">{{ vehiculo.marca }} {{ vehiculo.modelo }} {{ vehiculo.anio }}</small>
                    </div></div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card custom-card"><div class="card-body py-2">
                        <p class="text-muted mb-1">Medición / Combustible</p>
                        <h6 class="fw-bold mb-0 text-capitalize">{{ vehiculo.tipo_medicion }}</h6>
                        <small class="text-muted">{{ vehiculo.tipo_combustible?.tipo_combustible ?? '—' }}</small>
                    </div></div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card custom-card"><div class="card-body py-2">
                        <p class="text-muted mb-1">Operaciones en el rango</p>
                        <h6 class="fw-bold mb-0">{{ totales.operaciones }}</h6>
                    </div></div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card custom-card"><div class="card-body py-2">
                        <p class="text-muted mb-1">Consumo por hora</p>
                        <h6 class="fw-bold mb-0">{{ n(totales.litros_por_hora) }} L/h</h6>
                    </div></div>
                </div>
            </div>

            <!-- Sin filas -->
            <div v-if="! hayFilas" class="card custom-card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="ri-inbox-line fs-3 d-block mb-2"></i>
                    Este vehículo no tiene operaciones diarias registradas en el rango seleccionado.
                </div>
            </div>

            <template v-else>
                <!-- Tabla principal -->
                <div class="card custom-card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div class="card-title">
                            Detalle diario
                            <span class="badge bg-primary-transparent text-primary ms-2">{{ filas.length }} operaciones</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm text-nowrap mb-0 bitacora-tabla align-middle">
                                <thead>
                                    <tr class="text-center grp-row">
                                        <th colspan="6" class="grp bg-primary-transparent text-primary">TRABAJO DE EQUIPO</th>
                                        <th colspan="4" class="grp bg-warning-transparent text-warning">CONSUMO Y COSTO DE COMBUSTIBLE</th>
                                        <th v-if="colsMant.length" :colspan="colsMant.length" class="grp bg-info-transparent text-info">MANTENIMIENTO</th>
                                        <th v-if="colsMat.length" :colspan="colsMat.length" class="grp bg-success-transparent text-success">MATERIAL TRASLADADO</th>
                                        <th rowspan="2" class="grp bg-secondary-transparent text-secondary align-middle">OBSERVACIONES</th>
                                    </tr>
                                    <tr class="text-uppercase small">
                                        <th>Fecha</th>
                                        <th>Operador</th>
                                        <th class="text-center">N° Parte</th>
                                        <th class="text-end">{{ lecturaLabel }} inicial</th>
                                        <th class="text-end">{{ lecturaLabel }} final</th>
                                        <th class="text-end">Total horas</th>
                                        <th class="text-end">Litros diésel</th>
                                        <th class="text-end">C / Litro</th>
                                        <th class="text-end">Costo Bs.</th>
                                        <th class="text-end">{{ lecturaLabel }} de carga</th>
                                        <th v-for="col in colsMant" :key="'mh' + col.id" class="text-end">
                                            {{ col.nombre }}<span v-if="col.unidad_medida" class="text-muted"> ({{ col.unidad_medida }})</span>
                                        </th>
                                        <th v-for="col in colsMat" :key="'xh' + col.id" class="text-end">{{ col.nombre }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="fila in filas" :key="fila.id">
                                        <td>{{ fechaCorta(fila.fecha) }}</td>
                                        <td>{{ fila.operador || '—' }}</td>
                                        <td class="text-center">{{ fila.nro_parte }}</td>
                                        <td class="text-end">{{ n(fila.lectura_inicio) }}</td>
                                        <td class="text-end">{{ n(fila.lectura_fin) }}</td>
                                        <td class="text-end fw-medium">{{ n(fila.horas_trabajadas) }}</td>
                                        <td class="text-end">{{ fila.carga ? n(fila.carga.litros) : '—' }}</td>
                                        <td class="text-end">{{ fila.carga ? n(fila.carga.precio_unitario) : '—' }}</td>
                                        <td class="text-end">{{ fila.carga ? bs(fila.carga.costo) : '—' }}</td>
                                        <td class="text-end">{{ fila.carga ? n(fila.carga.lectura_carga) : '—' }}</td>
                                        <td v-for="col in colsMant" :key="'mv' + col.id" class="text-end">
                                            {{ valorMantenimiento(fila, col) }}
                                        </td>
                                        <td v-for="col in colsMat" :key="'xv' + col.id" class="text-end">
                                            {{ fila.materiales?.[col.id] != null ? n(fila.materiales[col.id]) : '—' }}
                                        </td>
                                        <td class="text-wrap" style="min-width: 160px;">{{ fila.observaciones || '—' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active fw-bold">
                                        <td colspan="5" class="text-end">TOTALES</td>
                                        <td class="text-end">{{ n(totales.horas_trabajadas) }}</td>
                                        <td class="text-end">{{ n(totales.litros) }}</td>
                                        <td></td>
                                        <td class="text-end">{{ bs(totales.costo) }}</td>
                                        <td></td>
                                        <td v-for="col in colsMant" :key="'mt' + col.id" class="text-end">{{ totalMantenimiento(col) }}</td>
                                        <td v-for="col in colsMat" :key="'xt' + col.id" class="text-end">{{ n(col.total) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Material trasladado (sólo vehículos por kilometraje) -->
                <div v-if="hayMaterial" class="card custom-card mt-3">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="card-title">
                            Material Trasladado
                            <span class="badge bg-success-transparent text-success ms-2">{{ colsMat.length }} tipo(s)</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <select v-model="metricaMaterial" class="form-select form-select-sm" style="width: auto;">
                                <option value="total">Por cantidad</option>
                                <option value="movimientos">Por n° de traslados</option>
                            </select>
                            <BotonDescargarGrafico :grafico="grafMaterial" nombre="bitacora-material-trasladado"
                                titulo="Material Trasladado" subtitulo="Bitácora por Vehículo" />
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-lg-5">
                                <div style="width: 100%; max-width: 320px; margin: 0 auto;">
                                    <Apexchart ref="grafMaterial" type="donut" height="300"
                                        :options="materialDonutOptions" :series="materialSeries" />
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover text-nowrap mb-0 align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Material</th>
                                                <th class="text-end">Cantidad</th>
                                                <th class="text-center">Traslados</th>
                                                <th class="text-end">%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="m in materialItems" :key="m.id">
                                                <td class="fw-medium">{{ m.nombre }}</td>
                                                <td class="text-end">
                                                    {{ n(m.total) }}<span v-if="m.unidad_medida" class="text-muted"> {{ m.unidad_medida }}</span>
                                                </td>
                                                <td class="text-center">{{ m.movimientos }}</td>
                                                <td class="text-end">{{ porcentajeMaterial(m[metricaMaterial]) }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-active fw-bold">
                                                <td>TOTAL</td>
                                                <td class="text-end">
                                                    {{ n(totalCantidadMaterial) }}<span v-if="unidadMaterialUnica" class="text-muted"> {{ unidadMaterialUnica }}</span>
                                                </td>
                                                <td class="text-center">{{ totalMovimientosMaterial }}</td>
                                                <td class="text-end">100%</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <p class="text-muted fs-12 mb-0 mt-2">
                                    "Traslados" = registros de traslado de material (actividad realizada con material asignado).
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen -->
                <div class="card custom-card mt-3">
                    <div class="card-header"><div class="card-title">Resumen</div></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6 col-xl-3">
                                <p class="text-muted mb-1">Total horas trabajadas</p>
                                <h4 class="fw-bold mb-0">{{ n(totales.horas_trabajadas) }} <small class="text-muted fs-14">horas</small></h4>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <p class="text-muted mb-1">Consumo de combustible</p>
                                <h4 class="fw-bold mb-0">{{ n(totales.litros) }} <small class="text-muted fs-14">litros</small></h4>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <p class="text-muted mb-1">Costo de combustible</p>
                                <h4 class="fw-bold mb-0">{{ bs(totales.costo) }}</h4>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <p class="text-muted mb-1">Consumo por hora de trabajo</p>
                                <h4 class="fw-bold mb-0">{{ n(totales.litros_por_hora) }} <small class="text-muted fs-14">L / hora</small></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </template>
</template>
