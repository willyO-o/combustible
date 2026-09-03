<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculo: Object,
    historialAsignaciones: Array,
    alertasMantenimiento: { type: Array, default: () => [] },
})

const conductorActual = computed(() => props.vehiculo.conductor_asignado ?? null)
const areaActual = computed(() => props.vehiculo.areas_asignadas?.[0] ?? null)

const estadoVehiculoBadge = (estado) => {
    const map = {
        ACTIVO: 'bg-success-transparent text-success',
        RETIRADO: 'bg-warning-transparent text-warning',
        VENDIDO: 'bg-secondary-transparent text-secondary',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

const estadoAsignacionBadge = (estado) => {
    const map = {
        ACTIVO: 'bg-success-transparent text-success',
        PROVISIONAL: 'bg-info-transparent text-info',
        REASIGNADO: 'bg-warning-transparent text-warning',
        INACTIVO: 'bg-secondary-transparent text-secondary',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

const estadoAreaBadge = (estado) => {
    const map = {
        ACTIVO: 'bg-success-transparent text-success',
        PROVISIONAL: 'bg-info-transparent text-info',
        REASIGNADO: 'bg-warning-transparent text-warning',
        CULMINADO: 'bg-secondary-transparent text-secondary',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

const tipoMedicionLabel = (tipo) => (tipo === 'horometro' ? 'Horómetro' : 'Kilometraje')

const unidadMedicion = (tipo) => (tipo === 'horometro' ? 'h' : 'km')

const numero = (valor) =>
    valor === null || valor === undefined
        ? '—'
        : new Intl.NumberFormat('es-BO', { maximumFractionDigits: 2 }).format(valor)

const medida = (valor, tipo) => (valor === null || valor === undefined ? '—' : `${numero(valor)} ${unidadMedicion(tipo)}`)

const estadoAlertaMeta = {
    VENCIDO: { clase: 'bg-danger-transparent text-danger', texto: 'Vencido' },
    PROXIMO: { clase: 'bg-warning-transparent text-warning', texto: 'Próximo' },
    AL_DIA: { clase: 'bg-success-transparent text-success', texto: 'Al día' },
    SIN_DATOS: { clase: 'bg-secondary-transparent text-secondary', texto: 'Sin datos' },
}

const ordenAlertas = { VENCIDO: 0, PROXIMO: 1, AL_DIA: 2, SIN_DATOS: 3 }

const alertasOrdenadas = computed(() =>
    [...props.alertasMantenimiento].sort(
        (a, b) =>
            (ordenAlertas[a.estado] ?? 9) - (ordenAlertas[b.estado] ?? 9) ||
            a.tipo_mantenimiento.localeCompare(b.tipo_mantenimiento),
    ),
)

const resumenAlertas = computed(() => ({
    vencidos: props.alertasMantenimiento.filter((a) => a.estado === 'VENCIDO').length,
    proximos: props.alertasMantenimiento.filter((a) => a.estado === 'PROXIMO').length,
}))

// Texto de la columna "Falta / Excedido": restante positivo = falta para el
// objetivo; negativo = ya se pasó del objetivo sugerido.
const restanteTexto = (alerta) => {
    if (alerta.restante === null || alerta.restante === undefined) {
        return '—'
    }
    if (alerta.restante < 0) {
        return `Excedido ${medida(Math.abs(alerta.restante), alerta.tipo_medicion)}`
    }
    return `Falta ${medida(alerta.restante, alerta.tipo_medicion)}`
}
</script>

<template>

    <Head title="Ver detalles del Vehículo" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item">
                        <Link :href="route('vehiculos.index')">Vehículos</Link>
                    </li>
                    <li class="breadcrumb-item active">Ver detalles</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                Ver detalles del Vehículo:
                <span class="text-primary">
                    {{ vehiculo.marca }} {{ vehiculo.modelo ?? '' }} {{ vehiculo.nro_placa ?? '' }}
                </span>
            </h1>
        </div>
        <Link :href="route('vehiculos.index')" class="btn btn-outline-secondary btn-wave">
            <i class="ri-arrow-left-line me-1"></i> Volver
        </Link>
    </div>

    <div class="row g-4 mb-4">
        <!-- Datos del Vehículo -->
        <div class="col-xl-4">
            <div class="card custom-card h-100">
                <div class="card-header justify-content-between">
                    <div class="card-title">Datos del Vehículo</div>
                    <span class="badge" :class="estadoVehiculoBadge(vehiculo.estado_vehiculo)">
                        {{ vehiculo.estado_vehiculo }}
                    </span>
                </div>
                <div class="card-body d-flex flex-column align-items-center gap-3">
                    <div class="border rounded-3 overflow-hidden"
                        style="width:150px;height:150px;background:#f8f9fa;">
                        <img v-if="vehiculo.url_fotografia" :src="vehiculo.url_fotografia"
                            :alt="vehiculo.nro_placa" class="w-100 h-100" style="object-fit:cover;" />
                        <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                            <i class="ri-car-line" style="font-size:4rem;"></i>
                        </div>
                    </div>

                    <div class="w-100">
                        <p class="fs-14 fw-medium mb-3 text-center">
                            {{ vehiculo.marca }} {{ vehiculo.modelo ?? '' }} ({{ vehiculo.anio ?? 'S/A' }})
                        </p>

                        <ul class="list-unstyled mb-0 w-100">
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                                <span class="text-muted fs-13">Código</span>
                                <span class="fw-medium">{{ vehiculo.codigo ?? 'N/A' }}</span>
                            </li>
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                                <span class="text-muted fs-13">Placa</span>
                                <span class="fw-medium">{{ vehiculo.nro_placa ?? 'N/A' }}</span>
                            </li>
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                                <span class="text-muted fs-13">Tipo de Vehículo</span>
                                <span class="fw-medium">{{ vehiculo.tipo_vehiculo?.tipo_vehiculo ?? 'N/A' }}</span>
                            </li>
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                                <span class="text-muted fs-13">Tipo de Combustible</span>
                                <span class="badge bg-info-transparent text-info">
                                    {{ vehiculo.tipo_combustible?.tipo_combustible ?? 'N/A' }}
                                </span>
                            </li>
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1">
                                <span class="text-muted fs-13">Medición</span>
                                <span class="fw-medium">{{ tipoMedicionLabel(vehiculo.tipo_medicion) }}</span>
                            </li>
                        </ul>

                        <div v-if="vehiculo.detalles" class="mt-3">
                            <span class="text-muted fs-13 d-block mb-1">Detalles</span>
                            <p class="fs-13 mb-0">{{ vehiculo.detalles }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conductor / Operario Actual -->
        <div class="col-xl-4">
            <div class="card custom-card h-100">
                <div class="card-header justify-content-between">
                    <div class="card-title">Conductor / Operario Actual</div>
                    <span v-if="conductorActual" class="badge bg-success-transparent text-success">Asignado</span>
                    <span v-else class="badge bg-secondary-transparent text-secondary">Sin asignar</span>
                </div>
                <div v-if="conductorActual" class="card-body d-flex flex-column align-items-center gap-3">
                    <div class="border rounded-3 overflow-hidden"
                        style="width:150px;height:150px;background:#f8f9fa;">
                        <img v-if="conductorActual.persona?.foto_url" :src="conductorActual.persona.foto_url"
                            :alt="conductorActual.persona?.nombre_completo" class="w-100 h-100"
                            style="object-fit:cover;" />
                        <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                            <i class="ri-user-3-line" style="font-size:4rem;"></i>
                        </div>
                    </div>

                    <div class="w-100">
                        <p class="fs-14 fw-medium mb-3 text-center">
                            {{ conductorActual.persona?.nombre_completo }}
                        </p>

                        <ul class="list-unstyled mb-0 w-100">
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                                <span class="text-muted fs-13">C.I.</span>
                                <span class="fw-medium">{{ conductorActual.persona?.ci ?? 'N/A' }}</span>
                            </li>
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1">
                                <span class="text-muted fs-13">Celular</span>
                                <a :href="`tel:${conductorActual.persona?.celular}`" class="text-decoration-none fw-medium">
                                    {{ conductorActual.persona?.celular ?? 'N/A' }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div v-else class="card-body text-center text-muted py-5">
                    <i class="ri-user-unfollow-line fs-3 d-block mb-2"></i>
                    Este vehículo no tiene un conductor asignado actualmente.
                </div>
            </div>
        </div>

        <!-- Área Actual -->
        <div class="col-xl-4">
            <div class="card custom-card h-100">
                <div class="card-header justify-content-between">
                    <div class="card-title">Área Actual</div>
                    <span v-if="areaActual" class="badge" :class="estadoAreaBadge(areaActual.pivot.estado_asignacion)">
                        {{ areaActual.pivot.estado_asignacion }}
                    </span>
                    <span v-else class="badge bg-secondary-transparent text-secondary">Sin área</span>
                </div>
                <div v-if="areaActual" class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="avatar avatar-md bg-primary-transparent text-primary">
                            <i class="ri-building-line fs-18"></i>
                        </span>
                        <span class="fs-15 fw-semibold">{{ areaActual.nombre_area }}</span>
                    </div>

                    <ul class="list-unstyled mb-0 w-100">
                        <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                            <span class="text-muted fs-13">Fecha de Asignación</span>
                            <span class="fw-medium">{{ areaActual.pivot.fecha_asignacion ?? 'N/A' }}</span>
                        </li>
                        <li class="d-flex align-items-center justify-content-between gap-2 py-1">
                            <span class="text-muted fs-13">Fecha de Culminación</span>
                            <span class="fw-medium">{{ areaActual.pivot.fecha_culminacion ?? 'Indefinida' }}</span>
                        </li>
                    </ul>

                    <div v-if="areaActual.pivot.motivo_asignacion" class="mt-3">
                        <span class="text-muted fs-13 d-block mb-1">Motivo</span>
                        <p class="fs-13 mb-0">{{ areaActual.pivot.motivo_asignacion }}</p>
                    </div>
                </div>
                <div v-else class="card-body text-center text-muted py-5">
                    <i class="ri-building-line fs-3 d-block mb-2"></i>
                    Este vehículo no tiene un área asignada actualmente.
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas de Mantenimiento -->
    <div class="card custom-card overflow-hidden mb-4">
        <div class="card-header justify-content-between flex-wrap gap-2">
            <div class="card-title">
                <h6 class="mb-0">Alertas de Mantenimiento</h6>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span v-if="resumenAlertas.vencidos" class="badge bg-danger-transparent text-danger">
                    {{ resumenAlertas.vencidos }} vencido(s)
                </span>
                <span v-if="resumenAlertas.proximos" class="badge bg-warning-transparent text-warning">
                    {{ resumenAlertas.proximos }} próximo(s)
                </span>
                <span class="badge bg-primary-transparent text-primary">
                    {{ alertasMantenimiento.length }} intervalo(s)
                </span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mantenimiento</th>
                            <th>Medición</th>
                            <th>Frecuencia</th>
                            <th>Último realizado</th>
                            <th>Lectura actual</th>
                            <th>Próximo sugerido</th>
                            <th>Falta / Excedido</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="alerta in alertasOrdenadas" :key="alerta.id_tipo_mantenimiento">
                            <td class="fw-semibold">{{ alerta.tipo_mantenimiento }}</td>
                            <td>{{ tipoMedicionLabel(alerta.tipo_medicion) }}</td>
                            <td>{{ medida(alerta.frecuencia, alerta.tipo_medicion) }}</td>
                            <td>{{ medida(alerta.ultimo_mantenimiento, alerta.tipo_medicion) }}</td>
                            <td>{{ medida(alerta.lectura_actual, alerta.tipo_medicion) }}</td>
                            <td>{{ medida(alerta.proximo_objetivo, alerta.tipo_medicion) }}</td>
                            <td>{{ restanteTexto(alerta) }}</td>
                            <td>
                                <span class="badge" :class="estadoAlertaMeta[alerta.estado]?.clase">
                                    {{ estadoAlertaMeta[alerta.estado]?.texto ?? alerta.estado }}
                                </span>
                            </td>
                        </tr>

                        <tr v-if="alertasMantenimiento.length === 0">
                            <td colspan="8" class="p-5 text-center text-muted">
                                Este tipo de vehículo no tiene intervalos de mantenimiento configurados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Historial de Asignaciones de Conductor -->
    <div class="card custom-card overflow-hidden">
        <div class="card-header justify-content-between">
            <div class="card-title">
                <h6 class="mb-0">Historial de Asignaciones de Conductor</h6>
            </div>
            <span class="badge bg-primary-transparent text-primary">{{ historialAsignaciones.length }} registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Conductor</th>
                            <th>C.I.</th>
                            <th>Celular</th>
                            <th>Tipo de Asignación</th>
                            <th>Fecha de Asignación</th>
                            <th>Fecha de Culminación</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(asignacion, index) in historialAsignaciones" :key="asignacion.id">
                            <td>{{ index + 1 }}</td>
                            <td>
                                <span class="fw-semibold">
                                    <i class="ri-user-line me-1"></i>
                                    {{ asignacion.conductor?.nombre_completo ?? 'N/A' }}
                                </span>
                            </td>
                            <td>{{ asignacion.conductor?.ci ?? 'N/A' }}</td>
                            <td>{{ asignacion.conductor?.celular ?? 'N/A' }}</td>
                            <td>
                                <span class="badge" :class="estadoAsignacionBadge(asignacion.estado_asignacion)">
                                    {{ asignacion.estado_asignacion }}
                                </span>
                            </td>
                            <td>{{ asignacion.fecha_asignacion ?? 'N/A' }}</td>
                            <td>{{ asignacion.fecha_culminacion ?? 'Indefinida' }}</td>
                            <td class="text-wrap" style="max-width: 220px;">{{ asignacion.detalle ?? '—' }}</td>
                        </tr>

                        <tr v-if="historialAsignaciones.length === 0">
                            <td colspan="8" class="p-5 text-center text-muted">
                                No hay historial de asignaciones para este vehículo.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</template>
