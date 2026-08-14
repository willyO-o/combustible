<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import { confirm , showToast} from '@/Utils/alertUtil'

const props = defineProps({
    operacion: Object,
    flash: Object,
})
const estadoBadge = (estado) => {
    const map = {
        PENDIENTE: 'bg-info-transparent text-info',
        EN_PROGRESO: 'bg-warning-transparent text-warning',
        FINALIZADO: 'bg-secondary-transparent text-secondary',
        VERIFICADO: 'bg-success-transparent text-success',
        OBSERVADO: 'bg-danger-transparent text-danger'
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}
const activeTab = ref('actividades')


const verificarOperacion =  async () => {

    const result = await confirm( '¿Está seguro de marcar como revisada esta operación? Esta acción no se puede deshacer.','Confirmar Revisión', 'Sí, marcar como revisada',);

    if(!result) return;

    router.post(route('operacion-diaria.verificar'), { id_operacion: props.operacion.id }, {
        onSuccess: () => {
            // Aquí puedes agregar cualquier acción adicional después de la verificación
            showToast(props.flash.success ?? 'Operación marcada como revisada.', 'success');
        },
        onError: (errors) => {
            console.error(errors);
        }
    });



}

</script>

<template>

    <Head title="Detalle de Operación" />

        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">
                            <i class="ri-home-line me-1"></i> Inicio
                        </Link>
                    </li>
                    <li class="breadcrumb-item">
                        <Link :href="route('operacion-diaria.index')">Operaciones Diarias</Link>
                    </li>
                    <li class="breadcrumb-item active">Detalle de Operación</li>
                </ol>
            </nav>
        </div>

        <!-- Header Principal -->
        <div class="card custom-card mb-4 border-0 shadow-sm bg-info-transparent rounded">
            <div class="card-body p-2 ">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 align-items-center">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="avatar avatar-lg  bg-primary-transparent">
                            <i class="ri-truck-line fs-36 text-primary"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h5 class="mb-1 fw-bold">
                                    Operación Diaria <span class="text-primary">#{{ operacion.nro }}</span>
                                </h5>
                                <span :class="['badge', estadoBadge(operacion.estado), 'rounded-pill']">{{
                                    operacion.estado }}</span>

                            </div>

                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <small class="text-muted">
                                    <i class="ri-calendar-line me-1"></i>
                                    {{ operacion.fecha_inicio }} - {{ operacion.fecha_fin }}
                                </small>
                                <small class="text-muted">
                                    <i
                                        :class="operacion.turno === 'DIA' ? 'ri-sun-line me-1 text-warning' : 'ri-moon-line me-1'"></i>
                                    Turno: {{ operacion.turno }}
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="vr bg-info mx-2"></div>
                        <button v-can="'operacion-diaria.verificar'" @click="verificarOperacion" v-if="operacion.estado != 'VERIFICADO'" class="btn btn-primary btn-wave">
                            <i class="ri-verified-badge-fill me-2"></i> Verificar Operación
                        </button>
                    </div>

                </div>
            </div>
        </div>

        <!-- Cards de Información -->
        <div class="row g-3 mb-2">
            <!-- Conductor -->
            <div class="col-md-6 col-lg-4">
                <div class="card custom-card ">
                    <div class="card-body p-2 d-flex align-items-center gap-3">
                        <div class="avatar avatar-lg bg-primary-transparent rounded-pill">
                            <i class="ri-user-3-line text-primary fs-36  "></i>
                        </div>
                        <div>
                            <span class="text-muted fw-medium">Operador</span>
                            <h6 class="mb-1 fw-bold">{{ operacion.conductor.persona.nombre_completo }}</h6>
                            <small class="text-muted">C.I. {{ operacion.conductor.persona.ci }}</small>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Vehículo -->
            <div class="col-md-6 col-lg-4">
                <div class="card custom-card">
                    <div class="card-body p-2 d-flex align-items-center gap-3">
                        <div class="avatar avatar-lg bg-primary-transparent rounded-pill">
                            <i class="ri-truck-line text-primary fs-36"></i>
                        </div>
                        <div>
                            <span class="text-muted fw-medium">Vehículo / Maquinaria</span>

                            <h6 class="mb-1 fw-bold">{{ operacion.vehiculo.codigo }}</h6>
                            <small class="text-muted">{{ operacion.vehiculo.nro_placa }}</small>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Área -->
            <div class="col-md-6 col-lg-4">
                <div class="card custom-card">
                    <div class="card-body p-2 d-flex align-items-center gap-3">
                        <div class="avatar avatar-lg bg-primary-transparent rounded-pill">
                            <i class="ri-building-line text-primary fs-36"></i>
                        </div>
                        <div>
                            <span class="text-muted fw-medium d-block">Área</span>
                            <h6 class="mb-1 fw-bold">{{ operacion.area.nombre_area }}</h6>
                            <!-- <small class="text-muted">{{ operacion.area.nombre_area }}</small> -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Indicadores de Lecturas -->
        <div class="row g-3 mb-4 bg-info-transparent p-1 rounded">
            <div v-if="operacion.horometro_inicio" class=" col-md-6 col-lg-4 col-xl-3-4 mt-0">
                <div class=" custom-card text-center">

                    <div class="d-flex align-items-center justify-content-center gap-2 p-0">
                        <div class="vr"></div>

                        <div class="mb-2">
                            <i class="ri-speed-up-line text-primary fs-36"></i>
                        </div>
                        <div>
                            <small class="text-muted fw-medium d-block mb-1">Horómetro Inicio</small>
                            <h5 class="mb-0 fw-bold text-primary">{{ operacion.horometro_inicio }} h</h5>
                        </div>

                    </div>
                </div>
            </div>
            <div v-if="operacion.horometro_inicio" class=" col-md-6 col-lg-4 col-xl-3-4 mt-0 border-start">
                <div class=" custom-card text-center">
                    <div class="d-flex align-items-center justify-content-center gap-2 p-0">

                        <div class="vr"></div>

                        <div class="mb-2">
                            <i class="ri-speed-up-line text-primary fs-36"></i>
                        </div>
                        <div>
                            <small class="text-muted fw-medium d-block mb-1">Horómetro Fin</small>
                            <h5 class="mb-0 fw-bold text-primary">{{ operacion.horometro_fin }} h</h5>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="operacion.kilometraje_inicio" class=" col-md-6 col-lg-4 col-xl-3-4 mt-0 ">
                <div class=" custom-card text-center">
                    <div class="d-flex align-items-center justify-content-center gap-2 p-0">

                        <div class="mb-2">
                            <i class="ri-road-map-line text-primary fs-36"></i>
                        </div>
                        <div>
                            <small class="text-muted fw-medium d-block mb-1">Kilometraje Inicio</small>
                            <h5 class="mb-0 fw-bold text-primary">{{ operacion.kilometraje_inicio }} km</h5>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="operacion.kilometraje_fin" class=" col-md-6 col-lg-4 col-xl-3-4 mt-0 border-start">
                <div class=" custom-card text-center">
                    <div class="d-flex align-items-center justify-content-center gap-2 p-0">

                        <div class="mb-2">
                            <i class="ri-route-line text-primary fs-36"></i>
                        </div>
                        <div>
                            <small class="text-muted fw-medium d-block mb-1">Kilometraje Fin</small>
                            <h5 class="mb-0 fw-bold text-primary">{{ operacion.kilometraje_fin }} km</h5>
                        </div>
                    </div>
                </div>
            </div>

            <div class=" col-md-6 col-lg-4 col-xl-3-4 mt-0 border-start">
                <div class=" custom-card text-center">
                    <div class="d-flex align-items-center justify-content-center gap-2 p-0">

                        <div class="mb-2">
                            <i class="ri-time-line text-primary fs-36"></i>
                        </div>
                        <div>
                            <small class="text-muted fw-medium d-block mb-1">Horas Trabajadas</small>
                            <h5 class="mb-0 fw-bold text-primary">{{ operacion.horas_trabajadas }} h</h5>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenido Principal con Sidebar -->
        <div class="row g-4">
            <!-- Lado Izquierdo -->
            <div class="col-lg-8">
                <!-- Tabs -->
                <div class="card custom-card mb-4">
                    <div class="card-header border-bottom-0 p-0">
                        <ul class="nav nav-tabs nav-fill gap-2  border-bottom" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" :class="{ 'active': activeTab === 'actividades' }"
                                    @click="activeTab = 'actividades'" type="button">
                                    <i class="ri-checkbox-circle-line me-2"></i>
                                    Actividades Realizadas
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" :class="{ 'active': activeTab === 'generales' }"
                                    @click="activeTab = 'generales'" type="button">
                                    <i class="ri-information-line me-2"></i> Datos generales
                                </button>
                            </li>

                        </ul>
                    </div>

                    <!-- Tab: Actividades Realizadas -->
                    <div v-if="activeTab === 'actividades'" class="card-body">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="mb-0 fw-bold">DETALLE JORNADA DIARIA DE TRABAJO</h6>

                            <small class="text-muted">
                                <i class="ri-checkbox-circle-line me-1"></i>
                                <strong>{{ operacion.actividades_realizadas.length }} actividades registradas</strong>
                            </small>
                        </div>

                        <!-- Tabla Responsiva -->
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-center">#</th>
                                        <th>Actividad</th>
                                        <th v-if="operacion.actividades_realizadas.some(act => act.pivot.origen)">Origen
                                        </th>
                                        <th v-if="operacion.actividades_realizadas.some(act => act.pivot.destino)">
                                            Destino</th>
                                        <th v-if="operacion.actividades_realizadas.some(act => act.pivot.lugar)"> Lugar
                                        </th>
                                        <th class="text-center">Cantidad</th>
                                        <th>Unidad</th>
                                        <th>Horario</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(act, index) in operacion.actividades_realizadas" :key="act.id">
                                        <td class="text-center fw-bold">{{ index + 1 }}</td>
                                        <td>
                                            {{ act.nombre_actividad }}
                                        </td>
                                        <td v-if="act.pivot.origen">{{ act.pivot.origen }}</td>
                                        <td v-if="act.pivot.destino">{{ act.pivot.destino }}</td>
                                        <td v-if="act.pivot.lugar">{{ act.pivot.lugar }}</td>
                                        <td class="text-center">{{ act.pivot.cantidad }}</td>
                                        <td>{{ act.pivot.unidad_medida }}</td>
                                        <td>{{ act.pivot.hora_inicio }} - {{ act.pivot.hora_fin }}</td>

                                    </tr>

                                </tbody>
                            </table>
                        </div>

                        <!-- Resumen -->
                        <div class="border-top mt-3 pt-3">
                            <div class="row g-2">
                                <div v-if="operacion.kilometraje_fin && operacion.kilometraje_inicio" class="col-auto">
                                    <small class="text-muted">
                                        <i class="ri-checkbox-circle-line me-1"></i>
                                        <strong>{{ operacion.kilometraje_fin - operacion.kilometraje_inicio }}
                                            km recorridos</strong>
                                    </small>
                                </div>
                                <div v-if="operacion.horometro_fin && operacion.horometro_inicio" class="col-auto">
                                    <small class="text-muted">
                                        <i class="ri-checkbox-circle-line me-1"></i>
                                        <strong>{{ operacion.horometro_fin - operacion.horometro_inicio }}
                                            h recorridas</strong>
                                    </small>
                                </div>
                                <div class="col-auto d-none">
                                    <small class="text-muted">•</small>
                                </div>
                                <div class="col-auto d-none">
                                    <small class="text-muted">
                                        <i class="ri-square-meter-line me-1"></i>
                                        1,200 m² impliados
                                    </small>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Tab: Datos Generales -->
                    <div v-if="activeTab === 'generales'" class="card-body">
                        <h6 class="mb-3 fw-bold">Información General</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Estado de la Operación</label>
                                <p class="mb-0">{{ operacion.estado }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Fecha de Registro</label>
                                <p class="mb-0">{{ operacion.created_at }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Hora Inicio</label>
                                <p class="mb-0">{{ operacion.fecha_inicio }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-medium">Hora Fin</label>
                                <p class="mb-0">{{ operacion.fecha_fin }}</p>
                            </div>
                        </div>
                    </div>



                </div>
            </div>

            <!-- Lado Derecho - Sidebar -->
            <div class="col-lg-4">
                <!-- Observaciones -->
                <div class="card bg-warning-transparent custom-card mb-4 rounded border-1 border-warning">
                    <div class="card-header d-none d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 fw-bold">
                        </h6>
                        <button class="btn btn-sm btn-link p-0 " title="Editar">
                            <i class="ri-edit-line"></i>
                        </button>
                    </div>
                    <div class="card-body p-3">
                        <i class="ri-survey-line me-2 fs-5 text-warning"></i> Observaciones

                        <div class="alert alert-light border-1 border-secondary mb-0 mt-2" role="alert">
                            <small class="d-block">
                                {{ operacion.observaciones || 'No hay observaciones registradas.' }}
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Línea de Tiempo -->
                <div class="card custom-card">
                    <div class="card-header">
                        <h6 class="mb-0 fw-bold">
                            <i class="ri-time-line me-2"></i> Línea de tiempo
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <!-- Item 1 -->
                            <div class="timeline-item mb-3">
                                <div class="d-flex gap-3">
                                    <div class="timeline-indicator">
                                        <span class="badge bg-info rounded-circle"
                                            style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                            <i class="ri-check-line text-white"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <small class="fw-bold d-block mb-1">Operación iniciada</small>
                                        <small class="text-muted d-block">{{ operacion.fecha_inicio }} - {{ operacion.conductor.persona.nombre_completo }}</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Item 2 -->
                            <div class="timeline-item mb-3">
                                <div class="d-flex gap-3">
                                    <div class="timeline-indicator">
                                        <span class="badge bg-primary rounded-circle"
                                            style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                            <i class="ri-checkbox-circle-line text-white"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <small class="fw-bold d-block mb-1">Actividades registradas</small>
                                        <small class="text-muted d-block">
                                            {{ operacion.fecha_fin }} -
                                            {{ operacion.actividades_realizadas.length }} actividades</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Item 3 -->
                            <div  v-if="operacion.estado === 'FINALIZADO'" class="timeline-item mb-3">
                                <div class="d-flex gap-3">
                                    <div class="timeline-indicator">
                                        <span class="badge bg-warning rounded-circle"
                                            style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                            <i class="ri-time-line text-white"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <small class="fw-bold d-block mb-1">Operación Finalizada</small>
                                        <small class="text-muted d-block">Pendiente de revisión</small>
                                    </div>
                                </div>
                            </div>

                            <div  v-if="operacion.estado === 'VERIFICADO'" class="timeline-item">
                                <div class="d-flex gap-3">
                                    <div class="timeline-indicator">
                                        <span class="badge bg-success rounded-circle"
                                            style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                            <i class="ri-check-line text-white"></i>
                                        </span>
                                    </div>
                                    <div>
                                        <small class="fw-bold d-block mb-1">Operación Revisada</small>
                                        <small class="text-muted d-block"> Por: {{ operacion.verificador?.nombre_completo }}</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de Acción -->
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <Link :href="route('operacion-diaria.index')" class="btn btn-outline-secondary btn-wave">
                        <i class="ri-arrow-left-line me-2"></i> Volver al listado
                    </Link>
                    <div class="d-flex gap-2 flex-wrap">

                        <a v-can="'operacion-diaria.informe'" :href="route('operacion-diaria.reporte.pdf', operacion.id)" target="_blank" class="btn btn-danger btn-wave">
                            <i class="ri-file-pdf-line me-2"></i> Exportar PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
</template>

<style scoped>
.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
}

.nav-tabs .nav-link.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
    background-color: transparent;
}

.timeline {
    position: relative;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 32px;
    bottom: 0;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item {
    position: relative;
}

.timeline-item:last-child .timeline::before {
    display: none;
}

/* Responsivo */
@media (max-width: 768px) {
    .row.g-3 {
        --bs-gutter-y: 1rem;
    }

    .nav-tabs {
        flex-wrap: wrap;
    }

    .table {
        font-size: 0.875rem;
    }

    .table th,
    .table td {
        padding: 0.5rem;
    }
}

/* Utility para cards de indicadores */
.col-xl-2-4 {
    flex: 0 0 calc(20% - var(--bs-gutter-x));
}

@media (max-width: 1200px) {
    .col-xl-2-4 {
        flex: 0 0 calc(33.333% - var(--bs-gutter-x));
    }
}

@media (max-width: 768px) {
    .col-xl-2-4 {
        flex: 0 0 calc(50% - var(--bs-gutter-x));
    }
}

@media (max-width: 576px) {
    .col-xl-2-4 {
        flex: 0 0 calc(50% - var(--bs-gutter-x));
    }
}
</style>
