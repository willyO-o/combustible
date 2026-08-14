<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculos: Array,
    datosResumen: Object,
    filtros: Object,
})

const filtros = ref({
    fecha_inicio: props.filtros?.fecha_inicio ?? '',
    fecha_fin: props.filtros?.fecha_fin ?? '',
    id_vehiculo: props.filtros?.id_vehiculo ?? '',
})

let debounceTimer = null
watch(filtros, (val) => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('cargas-combustible.reporte.index'), {
            fecha_inicio: val.fecha_inicio || undefined,
            fecha_fin: val.fecha_fin || undefined,
            id_vehiculo: val.id_vehiculo || undefined,
        }, { preserveState: true, replace: true })
    }, 500)
}, { deep: true })

function clearFilters() {
    filtros.value = {
        fecha_inicio: '',
        fecha_fin: '',
        id_vehiculo: '',
    }
}

function generarReporte() {
    if (!filtros.value.fecha_inicio || !filtros.value.fecha_fin) {
        alert('Por favor, selecciona un rango de fechas')
        return
    }

    // Genera la URL completa con sus parámetros
    const url = route('cargas-combustible.reporte.pdf', {
        fecha_inicio: filtros.value.fecha_inicio,
        fecha_fin: filtros.value.fecha_fin,
        id_vehiculo: filtros.value.id_vehiculo || null,
    })

    // Abre la URL en una nueva pestaña
    window.open(url, '_blank')
}

const totalVehiculos = computed(() => {
    return props.datosResumen?.vehiculos?.length || 0
})

const costoPorLitro = computed(() => {
    const total = props.datosResumen?.total_litros || 0
    const costo = props.datosResumen?.total_costo || 0
    return total > 0 ? (costo / total).toFixed(2) : 0
})
</script>

<template>
    <Head title="Reporte de Cargas de Combustible" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item active">Reportes</li>
                        <li class="breadcrumb-item active">Cargas de Combustible</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Reporte de Cargas de Combustible</h1>
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
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">Vehículo (Opcional)</label>
                        <select v-model="filtros.id_vehiculo" class="form-select">
                            <option value="">Todos los vehículos</option>
                            <option v-for="v in vehiculos" :key="v.id" :value="v.id">
                                {{ v.nro_placa }} – {{ v.marca }}
                            </option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3 d-flex align-items-end gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-wave flex-grow-1" @click="clearFilters">
                            <i class="ri-refresh-line me-1"></i> Limpiar
                        </button>
                        <button type="button" class="btn btn-primary btn-wave flex-grow-1" @click="generarReporte">
                            <i class="ri-file-pdf-line me-1"></i> PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjetas de Resumen -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Total de Litros</p>
                                <h3 class="fw-bold mb-0">{{ datosResumen?.total_litros?.toLocaleString() }} L</h3>
                            </div>
                            <div class="avatar avatar-lg bg-info-transparent">
                                <i class="ri-water-flash-line fs-24 text-info"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Total Gastado</p>
                                <h3 class="fw-bold mb-0">Bs. {{ datosResumen?.total_costo?.toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}</h3>
                            </div>
                            <div class="avatar avatar-lg bg-success-transparent">
                                <i class="ri-money-dollar-circle-line fs-24 text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Costo por Litro</p>
                                <h3 class="fw-bold mb-0">Bs. {{ costoPorLitro }}</h3>
                            </div>
                            <div class="avatar avatar-lg bg-primary-transparent">
                                <i class="ri-calculator-line fs-24 text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Vehículos</p>
                                <h3 class="fw-bold mb-0">{{ totalVehiculos }}</h3>
                            </div>
                            <div class="avatar avatar-lg bg-warning-transparent">
                                <i class="ri-car-line fs-24 text-warning"></i>
                            </div>
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
                    <span class="badge bg-primary-transparent text-primary ms-2">{{ datosResumen?.vehiculos?.length ?? 0 }} vehículos</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Placa</th>
                                <th>Marca</th>
                                <th>Código</th>
                                <th>Total Litros</th>
                                <th>Total Costo</th>
                                <th>Cantidad Cargas</th>
                                <th>Precio Promedio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!datosResumen?.vehiculos || datosResumen.vehiculos.length === 0">
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="ri-oil-line fs-3 d-block mb-2"></i>
                                    No se encontraron registros de combustible
                                </td>
                            </tr>
                            <tr v-for="(v, idx) in datosResumen?.vehiculos" :key="v.id_vehiculo">
                                <td>{{ idx + 1 }}</td>
                                <td class="fw-medium">{{ v.nro_placa }}</td>
                                <td>{{ v.marca }}</td>
                                <td><span class="badge bg-secondary-transparent text-secondary">{{ v.codigo }}</span></td>
                                <td class="text-end fw-medium">{{ v.total_litros.toLocaleString() }} L</td>
                                <td class="text-end">Bs. {{ v.total_costo.toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}</td>
                                <td class="text-center"><span class="badge bg-info-transparent text-info">{{ v.cantidad_cargas }}</span></td>
                                <td class="text-end">Bs. {{ v.precio_promedio }}</td>
                            </tr>
                            <!-- Fila de totales -->
                            <tr class="table-active fw-bold">
                                <td colspan="4">TOTALES</td>
                                <td class="text-end">{{ datosResumen?.total_litros?.toLocaleString() }} L</td>
                                <td class="text-end">Bs. {{ datosResumen?.total_costo?.toLocaleString('es-ES', { maximumFractionDigits: 2 }) }}</td>
                                <td class="text-center">{{ datosResumen?.cantidad_cargas }}</td>
                                <td class="text-end">Bs. {{ costoPorLitro }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
</template>
