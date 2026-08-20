<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    conductor: Object,
    historialAsignaciones: Array,
})

const persona = computed(() => props.conductor.persona ?? {})
const vehiculosAsignados = computed(() => props.conductor.asignaciones_activas ?? [])

const estadoConductorBadge = (estado) => {
    const map = {
        ACTIVO: 'bg-success-transparent text-success',
        INACTIVO: 'bg-warning-transparent text-warning',
        RETIRADO: 'bg-danger-transparent text-danger',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

const estadoPersonaBadge = (estado) => {
    const map = {
        ACTIVO: 'bg-success-transparent text-success',
        INACTIVO: 'bg-warning-transparent text-warning',
        RETIRADO: 'bg-danger-transparent text-danger',
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

const documentos = computed(() => props.conductor.documentos ?? [])

const estadoDocumentoBadge = (estado) => {
    const map = {
        VIGENTE: 'bg-success-transparent text-success',
        VENCIDO: 'bg-danger-transparent text-danger',
        OBSERVADO: 'bg-warning-transparent text-warning',
    }
    return map[estado] ?? 'bg-secondary-transparent text-secondary'
}

const tipoDocumentoLabel = (tipo) => {
    const map = {
        LICENCIA_DE_CONDUCIR: 'Licencia de Conducir',
        CI: 'Carnet de Identidad',
        CERTIFICADO_MEDICO: 'Certificado Médico',
        OTRO: 'Otro',
    }
    return map[tipo] ?? tipo
}
</script>

<template>

    <Head title="Ver detalles del Conductor" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item">
                        <Link :href="route('conductores.index')">Conductores</Link>
                    </li>
                    <li class="breadcrumb-item active">Ver detalles</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                Ver detalles del Conductor:
                <span class="text-primary">
                    {{ persona.nombre_completo }}
                </span>
            </h1>
        </div>
        <Link :href="route('conductores.index')" class="btn btn-outline-secondary btn-wave">
            <i class="ri-arrow-left-line me-1"></i> Volver
        </Link>
    </div>

    <div class="row g-4 mb-4">
        <!-- Datos Personales -->
        <div class="col-xl-4">
            <div class="card custom-card h-100">
                <div class="card-header justify-content-between">
                    <div class="card-title">Datos Personales</div>
                    <span class="badge" :class="estadoConductorBadge(conductor.estado_conductor)">
                        {{ conductor.estado_conductor }}
                    </span>
                </div>
                <div class="card-body d-flex flex-column align-items-center gap-3">
                    <div class="border rounded-3 overflow-hidden"
                        style="width:150px;height:150px;background:#f8f9fa;">
                        <img v-if="persona.foto_url" :src="persona.foto_url" :alt="persona.nombre_completo"
                            class="w-100 h-100" style="object-fit:cover;" />
                        <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                            <i class="ri-user-3-line" style="font-size:4rem;"></i>
                        </div>
                    </div>

                    <div class="w-100">
                        <p class="fs-14 fw-medium mb-3 text-center">
                            {{ persona.nombre_completo }}
                        </p>

                        <ul class="list-unstyled mb-0 w-100">
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                                <span class="text-muted fs-13">C.I.</span>
                                <span class="fw-medium">{{ persona.ci ?? 'N/A' }}</span>
                            </li>
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                                <span class="text-muted fs-13">Celular</span>
                                <a :href="`tel:${persona.celular}`" class="text-decoration-none fw-medium">
                                    {{ persona.celular ?? 'N/A' }}
                                </a>
                            </li>
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                                <span class="text-muted fs-13">F. Nacimiento</span>
                                <span class="fw-medium">
                                    {{ persona.f_nacimiento_formatted ?? 'N/A' }}
                                    <template v-if="persona.edad">({{ persona.edad }} años)</template>
                                </span>
                            </li>
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1 border-bottom">
                                <span class="text-muted fs-13">Dirección</span>
                                <span class="fw-medium text-end">{{ persona.direccion ?? 'N/A' }}</span>
                            </li>
                            <li class="d-flex align-items-center justify-content-between gap-2 py-1">
                                <span class="text-muted fs-13">Estado (Persona)</span>
                                <span class="badge" :class="estadoPersonaBadge(persona.estado_persona)">
                                    {{ persona.estado_persona ?? 'N/A' }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vehículos Asignados Actualmente -->
        <div class="col-xl-8">
            <div class="card custom-card h-100">
                <div class="card-header justify-content-between">
                    <div class="card-title">Vehículos Asignados Actualmente</div>
                    <span class="badge bg-primary-transparent text-primary">{{ vehiculosAsignados.length }}</span>
                </div>
                <div v-if="vehiculosAsignados.length > 0" class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li v-for="vehiculo in vehiculosAsignados" :key="vehiculo.id" class="list-group-item">
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="border rounded-3 overflow-hidden flex-shrink-0"
                                    style="width:64px;height:64px;background:#f8f9fa;">
                                    <img v-if="vehiculo.url_fotografia" :src="vehiculo.url_fotografia"
                                        :alt="vehiculo.nro_placa" class="w-100 h-100" style="object-fit:cover;" />
                                    <div v-else
                                        class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                        <i class="ri-car-line fs-20"></i>
                                    </div>
                                </div>

                                <div class="flex-fill" style="min-width:180px;">
                                    <span class="fw-semibold d-block">
                                        {{ vehiculo.codigo }} — {{ vehiculo.nro_placa }}
                                    </span>
                                    <span class="fs-12 text-muted">
                                        {{ vehiculo.marca }} {{ vehiculo.modelo ?? '' }} ({{ vehiculo.anio ?? 'S/A' }})
                                    </span>
                                    <div class="d-flex gap-1 mt-1 flex-wrap">
                                        <span v-if="vehiculo.tipo_vehiculo?.tipo_vehiculo"
                                            class="badge bg-secondary-transparent text-secondary">
                                            {{ vehiculo.tipo_vehiculo.tipo_vehiculo }}
                                        </span>
                                        <span v-if="vehiculo.tipo_combustible?.tipo_combustible"
                                            class="badge bg-info-transparent text-info">
                                            {{ vehiculo.tipo_combustible.tipo_combustible }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex-fill" style="min-width:150px;">
                                    <span class="badge" :class="estadoAsignacionBadge(vehiculo.pivot.estado_asignacion)">
                                        {{ vehiculo.pivot.estado_asignacion }}
                                    </span>
                                    <span v-if="vehiculo.pivot.detalle" class="fs-12 d-block text-muted mt-1">
                                        {{ vehiculo.pivot.detalle }}
                                    </span>
                                </div>

                                <div class="text-end">
                                    <span class="d-block fw-semibold fs-13">{{ vehiculo.pivot.fecha_asignacion }}</span>
                                    <span class="fs-12 d-block text-muted">Desde</span>
                                    <template v-if="vehiculo.pivot.fecha_culminacion">
                                        <span class="d-block fw-semibold fs-13 mt-1">{{ vehiculo.pivot.fecha_culminacion }}</span>
                                        <span class="fs-12 d-block text-muted">Hasta</span>
                                    </template>
                                </div>

                                <Link :href="route('vehiculos.show', vehiculo.id)"
                                    class="btn btn-sm btn-icon btn-primary-light" title="Ver vehículo">
                                    <i class="ri-eye-line"></i>
                                </Link>
                            </div>
                        </li>
                    </ul>
                </div>
                <div v-else class="card-body text-center text-muted py-5">
                    <i class="ri-car-line fs-3 d-block mb-2"></i>
                    Este conductor no tiene vehículos asignados actualmente.
                </div>
            </div>
        </div>
    </div>

    <!-- Documentos del Conductor -->
    <div class="card custom-card overflow-hidden mb-4">
        <div class="card-header justify-content-between">
            <div class="card-title">
                <h6 class="mb-0">Documentos del Conductor</h6>
            </div>
            <span class="badge bg-primary-transparent text-primary">{{ documentos.length }} registros</span>
        </div>
        <div v-if="documentos.length > 0" class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tipo</th>
                            <th>Número</th>
                            <th>Categoría</th>
                            <th>Emisión</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                            <th>Observación</th>
                            <th>Archivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="documento in documentos" :key="documento.id">
                            <td>{{ tipoDocumentoLabel(documento.tipo_documento) }}</td>
                            <td>{{ documento.numero_documento ?? 'N/A' }}</td>
                            <td>{{ documento.categoria ?? 'N/A' }}</td>
                            <td>{{ documento.fecha_emision ?? 'N/A' }}</td>
                            <td>{{ documento.fecha_vencimiento ?? 'N/A' }}</td>
                            <td>
                                <span class="badge" :class="estadoDocumentoBadge(documento.estado_documento)">
                                    {{ documento.estado_documento }}
                                </span>
                            </td>
                            <td class="text-wrap" style="max-width: 220px;">{{ documento.observacion ?? '—' }}</td>
                            <td>
                                <a v-if="documento.archivo_url" :href="documento.archivo_url" target="_blank"
                                    class="btn btn-sm btn-icon btn-primary-light" title="Ver archivo">
                                    <i class="ri-file-line"></i>
                                </a>
                                <span v-else class="text-muted">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div v-else class="card-body text-center text-muted py-5">
            <i class="ri-file-list-3-line fs-3 d-block mb-2"></i>
            Este conductor no tiene documentos registrados.
        </div>
    </div>

    <!-- Historial de Asignaciones de Vehículo -->
    <div class="card custom-card overflow-hidden">
        <div class="card-header justify-content-between">
            <div class="card-title">
                <h6 class="mb-0">Historial de Asignaciones de Vehículo</h6>
            </div>
            <span class="badge bg-primary-transparent text-primary">{{ historialAsignaciones.length }} registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover text-nowrap mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Vehículo</th>
                            <th>Tipo</th>
                            <th>Combustible</th>
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
                                    <i class="ri-car-line me-1"></i>
                                    {{ asignacion.vehiculo?.codigo }} — {{ asignacion.vehiculo?.nro_placa ?? 'N/A' }}
                                </span>
                                <span class="fs-12 d-block text-muted">
                                    {{ asignacion.vehiculo?.marca }} {{ asignacion.vehiculo?.modelo ?? '' }}
                                </span>
                            </td>
                            <td>{{ asignacion.vehiculo?.tipo_vehiculo ?? 'N/A' }}</td>
                            <td>{{ asignacion.vehiculo?.tipo_combustible ?? 'N/A' }}</td>
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
                                No hay historial de asignaciones para este conductor.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</template>
