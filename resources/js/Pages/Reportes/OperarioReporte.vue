<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Multiselect from '@vueform/multiselect'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import DateRangeFilter from '@/Components/DateRangeFilter.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    // [{ id, label }] todos los operarios (activos e inactivos)
    operarios: Array,
    // { id, nombre_completo, ci, cargo, estado_conductor } o null
    operario: Object,
    // { operaciones, actividades, cargas, totales } o null sin operario
    datos: Object,
    filtros: Object,
})

const filtros = ref({
    fecha_inicio: props.filtros?.fecha_inicio ?? '',
    fecha_fin: props.filtros?.fecha_fin ?? '',
    id_conductor: props.filtros?.id_conductor ?? '',
})

// Copias: Multiselect reescribe `label` de la opción seleccionada.
const operarioOptions = computed(() => props.operarios.map((o) => ({ ...o })))

let debounceTimer = null
watch(filtros, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('operacion-diaria.reporte.operario.index'), {
            fecha_inicio: val.fecha_inicio,
            fecha_fin: val.fecha_fin,
            id_conductor: val.id_conductor || undefined,
        }, { preserveState: true, replace: true })
    }, 500)
}, { deep: true })

/* ------------------------------------------------------------------ */
/*  Formato                                                            */
/* ------------------------------------------------------------------ */
const n = (v, d = 2) => (v === null || v === undefined || v === '')
    ? '—'
    : Number(v).toLocaleString('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: d })
const bs = (v) => (v === null || v === undefined) ? '—' : `Bs. ${Number(v).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
const fechaHora = (v) => {
    if (! v) return '—'
    const d = new Date(String(v).replace(' ', 'T'))
    return isNaN(d)
        ? v
        : d.toLocaleString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
}

const operaciones = computed(() => props.datos?.operaciones ?? [])
const actividades = computed(() => props.datos?.actividades ?? [])
const cargas = computed(() => props.datos?.cargas ?? [])
const totales = computed(() => props.datos?.totales ?? {
    operaciones: 0, horas_trabajadas: 0, dias_trabajados: 0, vehiculos: 0,
    actividades: 0, cargas: 0, litros: 0, costo: 0,
})

const lugarActividad = (a) => a.lugar || [a.origen, a.destino].filter(Boolean).join(' → ') || '—'
const cantidadActividad = (cantidad, unidad) =>
    cantidad === null || cantidad === undefined ? '—' : `${n(cantidad)}${unidad ? ' ' + unidad : ''}`

const estadoOperacionBadge = (estado) => ({
    VERIFICADO: 'bg-success-transparent text-success',
    OBSERVADO: 'bg-danger-transparent text-danger',
    FINALIZADO: 'bg-info-transparent text-info',
    EN_PROGRESO: 'bg-warning-transparent text-warning',
}[estado] ?? 'bg-secondary-transparent text-secondary')

const esAnulada = (c) => c.estado_carga === 'ANULADO'
</script>

<template>
    <Head title="Reporte por Operario" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                    <li class="breadcrumb-item active">Reportes</li>
                    <li class="breadcrumb-item active">Reporte por Operario</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                Actividades y Cargas de Combustible por Operario
                <span v-if="operario" class="text-primary">— {{ operario.nombre_completo }}</span>
            </h1>
            <p class="text-muted fs-13 mb-0">
                Las operaciones diarias del operario con las actividades de cada jornada, el resumen de
                actividades y las cargas de combustible a su nombre.
            </p>
        </div>
        <Link v-if="operario" v-can="'conductores.ver'" :href="route('conductores.show', operario.id)"
            class="btn btn-outline-secondary btn-wave">
            <i class="ri-user-3-line me-1"></i> Ver perfil
        </Link>
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
                <div class="col-lg-8">
                    <label class="form-label">Operario <span class="text-danger">*</span></label>
                    <Multiselect
                        v-model="filtros.id_conductor"
                        :options="operarioOptions"
                        value-prop="id"
                        label="label"
                        :searchable="true"
                        :filter-results="true"
                        placeholder="— Seleccionar un operario —"
                        no-options-text="Sin operarios"
                        no-results-text="Sin resultados"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Estado vacío: sin operario -->
    <div v-if="! operario" class="card custom-card">
        <div class="card-body text-center py-5 text-muted">
            <i class="ri-user-search-line fs-3 d-block mb-2"></i>
            Selecciona un operario y un rango de fechas para ver su reporte.
        </div>
    </div>

    <template v-else>
        <!-- Tarjetas -->
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3">
                <div class="card custom-card h-100"><div class="card-body py-2">
                    <p class="text-muted mb-1">Operario</p>
                    <h6 class="fw-bold mb-0">{{ operario.nombre_completo }}</h6>
                    <small class="text-muted">
                        CI: {{ operario.ci ?? '—' }} · {{ operario.cargo ?? 'Sin cargo' }}
                        <span v-if="operario.estado_conductor !== 'ACTIVO'" class="badge bg-warning-transparent text-warning ms-1">
                            {{ operario.estado_conductor }}
                        </span>
                    </small>
                </div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card custom-card h-100"><div class="card-body py-2">
                    <p class="text-muted mb-1">Jornadas</p>
                    <h6 class="fw-bold mb-0">{{ totales.operaciones }} operaciones</h6>
                    <small class="text-muted">{{ totales.dias_trabajados }} día(s) · {{ totales.vehiculos }} vehículo(s)</small>
                </div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card custom-card h-100"><div class="card-body py-2">
                    <p class="text-muted mb-1">Horas trabajadas</p>
                    <h6 class="fw-bold mb-0">{{ n(totales.horas_trabajadas) }} h</h6>
                    <small class="text-muted">{{ totales.actividades }} actividad(es) registradas</small>
                </div></div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card custom-card h-100"><div class="card-body py-2">
                    <p class="text-muted mb-1">Combustible cargado</p>
                    <h6 class="fw-bold mb-0">{{ n(totales.litros) }} L</h6>
                    <small class="text-muted">{{ totales.cargas }} carga(s) · {{ bs(totales.costo) }}</small>
                </div></div>
            </div>
        </div>

        <!-- Operaciones con sus actividades -->
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">
                    Operaciones diarias y actividades
                    <span class="badge bg-primary-transparent text-primary ms-2">{{ operaciones.length }}</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div v-if="! operaciones.length" class="text-center py-5 text-muted">
                    <i class="ri-inbox-line fs-3 d-block mb-2"></i>
                    El operario no tiene operaciones diarias en el rango seleccionado.
                </div>
                <div v-else class="table-responsive">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr class="text-uppercase small">
                                <th class="text-nowrap">Inicio</th>
                                <th class="text-center">N°</th>
                                <th class="text-nowrap">Vehículo</th>
                                <th class="text-center">Turno</th>
                                <th class="text-end text-nowrap">Lectura ini. / fin</th>
                                <th class="text-end">Horas</th>
                                <th>Actividades</th>
                                <th class="text-center">Estado</th>
                                <th v-can="'operacion-diaria.ver'" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="op in operaciones" :key="op.id">
                                <td class="text-nowrap">{{ fechaHora(op.fecha_inicio) }}</td>
                                <td class="text-center">{{ op.nro_operacion }}</td>
                                <td class="text-nowrap">{{ op.vehiculo }}</td>
                                <td class="text-center">{{ op.turno }}</td>
                                <td class="text-end text-nowrap">
                                    {{ n(op.lectura_inicio) }} / {{ n(op.lectura_fin) }}
                                    <small class="text-muted">{{ op.tipo_medicion === 'kilometraje' ? 'km' : 'h' }}</small>
                                </td>
                                <td class="text-end fw-medium">{{ n(op.horas_trabajadas) }}</td>
                                <td style="min-width: 260px;">
                                    <ul v-if="op.actividades.length" class="list-unstyled mb-0 small">
                                        <li v-for="(a, i) in op.actividades" :key="i" class="py-1"
                                            :class="{ 'border-top': i > 0 }">
                                            <span class="fw-medium">{{ a.actividad }}</span>
                                            <span class="text-muted"> · {{ lugarActividad(a) }}</span>
                                            <span v-if="a.material" class="text-muted"> · {{ a.material }}</span>
                                            <span class="float-end ms-2">{{ cantidadActividad(a.cantidad, a.unidad_medida) }}</span>
                                        </li>
                                    </ul>
                                    <span v-else class="text-muted small">Sin actividades</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge" :class="estadoOperacionBadge(op.estado)">{{ op.estado }}</span>
                                </td>
                                <td v-can="'operacion-diaria.ver'" class="text-center">
                                    <Link :href="route('operacion-diaria.show', op.id)"
                                        class="btn btn-sm btn-icon btn-primary-light" title="Ver operación">
                                        <i class="ri-eye-line"></i>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="table-active fw-bold">
                                <td colspan="5" class="text-end">TOTAL HORAS</td>
                                <td class="text-end">{{ n(totales.horas_trabajadas) }}</td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-0">
            <!-- Resumen de actividades -->
            <div class="col-xl-5">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <div class="card-title">Resumen de actividades</div>
                    </div>
                    <div class="card-body p-0">
                        <div v-if="! actividades.length" class="text-center py-5 text-muted">
                            Sin actividades registradas en el rango.
                        </div>
                        <div v-else class="table-responsive">
                            <table class="table table-sm table-hover text-nowrap mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Actividad</th>
                                        <th class="text-center">Veces</th>
                                        <th class="text-center">Jornadas</th>
                                        <th class="text-end">Cantidad</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(a, i) in actividades" :key="i">
                                        <td class="fw-medium text-wrap">{{ a.actividad }}</td>
                                        <td class="text-center">{{ a.veces }}</td>
                                        <td class="text-center">{{ a.jornadas }}</td>
                                        <td class="text-end">{{ cantidadActividad(a.cantidad_total, a.unidad_medida) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cargas de combustible -->
            <div class="col-xl-7">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <div class="card-title">
                            Cargas de combustible
                            <span class="badge bg-warning-transparent text-warning ms-2">{{ cargas.length }}</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div v-if="! cargas.length" class="text-center py-5 text-muted">
                            El operario no tiene cargas de combustible en el rango seleccionado.
                        </div>
                        <div v-else class="table-responsive">
                            <table class="table table-sm table-hover text-nowrap mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>N°</th>
                                        <th>Vehículo</th>
                                        <th class="text-end">Litros</th>
                                        <th class="text-end">Costo</th>
                                        <th class="text-end">Lectura</th>
                                        <th>Estación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in cargas" :key="c.id" :class="{ 'text-muted text-decoration-line-through': esAnulada(c) }">
                                        <td>{{ fechaHora(c.fecha_carga) }}</td>
                                        <td>
                                            {{ c.nro }}
                                            <span v-if="esAnulada(c)" class="badge bg-danger-transparent text-danger ms-1">Anulada</span>
                                        </td>
                                        <td>{{ c.vehiculo }}</td>
                                        <td class="text-end">{{ n(c.litros) }}</td>
                                        <td class="text-end">{{ bs(c.costo) }}</td>
                                        <td class="text-end">
                                            {{ n(c.lectura) }}
                                            <small v-if="c.lectura !== null" class="text-muted">{{ c.tipo_medicion === 'kilometraje' ? 'km' : 'h' }}</small>
                                        </td>
                                        <td class="text-wrap">{{ c.grifo ?? '—' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="table-active fw-bold">
                                        <td colspan="3" class="text-end">TOTAL (sin anuladas)</td>
                                        <td class="text-end">{{ n(totales.litros) }}</td>
                                        <td class="text-end">{{ bs(totales.costo) }}</td>
                                        <td colspan="2"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</template>
