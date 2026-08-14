<script setup>
import { Head, Link } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    solicitud: Object,
})

const estadoBadge = (estado) => {
    const map = {
        PENDIENTE: 'bg-warning-transparent text-warning',
        APROBADA:  'bg-success-transparent text-success',
        RECHAZADA: 'bg-danger-transparent text-danger',
        ANULADA:   'bg-secondary-transparent text-secondary',
    }
    return map[estado] ?? 'bg-light text-dark'
}

const tipoBadge = (tipo) => {
    return tipo === 'PREVENTIVO'
        ? 'bg-info-transparent text-info'
        : 'bg-danger-transparent text-danger'
}
</script>

<template>
    <Head title="Detalle Solicitud de Mantenimiento" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.solicitudes.index')">Solicitudes Mantenimiento</Link>
                        </li>
                        <li class="breadcrumb-item active">Detalle #{{ solicitud.id }}</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Solicitud de Mantenimiento #{{ solicitud.id }}</h1>
            </div>
            <div class="d-flex gap-2">
                <Link :href="route('mantenimiento.solicitudes.index')" class="btn btn-outline-secondary btn-wave">
                    <i class="ri-arrow-left-line me-1"></i> Volver
                </Link>
                <Link v-if="solicitud.estado === 'PENDIENTE' && !solicitud.plan_mantenimiento"
                    :href="route('mantenimiento.ordenes.create', { solicitud: solicitud.id })"
                    class="btn btn-success btn-wave">
                    <i class="ri-file-list-3-line me-1"></i> Generar Orden de Trabajo
                </Link>
            </div>
        </div>

        <div class="row g-4">
            <!-- Datos de la solicitud -->
            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ri-alarm-warning-line me-2 text-warning"></i>
                            Datos de la Solicitud
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Tipo de Mantenimiento</label>
                                <p>
                                    <span class="badge fs-12" :class="tipoBadge(solicitud.tipo_mantenimiento)">
                                        {{ solicitud.tipo_mantenimiento }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Estado</label>
                                <p>
                                    <span class="badge fs-12" :class="estadoBadge(solicitud.estado)">
                                        {{ solicitud.estado }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Fecha de Solicitud</label>
                                <p class="fw-medium">{{ solicitud.fecha_solicitud?.substring(0, 16).replace('T', ' ') ?? '—' }}</p>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label text-muted mb-0">Kilometraje al Momento</label>
                                <p class="fw-medium">
                                    {{ solicitud.kilometraje_actual != null ? solicitud.kilometraje_actual.toLocaleString() + ' km' : '—' }}
                                </p>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted mb-0">Descripción del Problema / Mantenimiento</label>
                                <p class="fw-medium" style="white-space: pre-wrap;">{{ solicitud.descripcion_problema }}</p>
                            </div>
                            <div v-if="solicitud.observacion" class="col-12">
                                <label class="form-label text-muted mb-0">Observación</label>
                                <p style="white-space: pre-wrap;">{{ solicitud.observacion }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Datos del vehículo y conductor -->
            <div class="col-xl-4">
                <div class="card custom-card mb-3">
                    <div class="card-header"><div class="card-title"><i class="ri-car-line me-2"></i>Vehículo</div></div>
                    <div class="card-body">
                        <p class="mb-1 fw-bold fs-16">{{ solicitud.vehiculo?.nro_placa ?? '—' }}</p>
                        <p class="mb-1 text-muted">{{ solicitud.vehiculo?.marca ?? '' }}</p>
                    </div>
                </div>

                <div class="card custom-card mb-3">
                    <div class="card-header"><div class="card-title"><i class="ri-user-line me-2"></i>Conductor</div></div>
                    <div class="card-body">
                        <p v-if="solicitud.conductor" class="mb-1 fw-medium">
                            {{ solicitud.conductor.nombres }} {{ solicitud.conductor.paterno ?? '' }}
                        </p>
                        <p v-else class="text-muted">No especificado</p>
                    </div>
                </div>

                <div class="card custom-card">
                    <div class="card-header"><div class="card-title"><i class="ri-shield-user-line me-2"></i>Registrado por</div></div>
                    <div class="card-body">
                        <p class="mb-1 fw-medium">{{ solicitud.usuario_registra?.name ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <!-- Orden de trabajo generada -->
            <div v-if="solicitud.plan_mantenimiento" class="col-12">
                <div class="card custom-card border border-success">
                    <div class="card-header bg-success-transparent">
                        <div class="card-title text-success">
                            <i class="ri-checkbox-circle-line me-2"></i>
                            Orden de Trabajo Generada
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="mb-1">
                            Orden N° <strong>{{ solicitud.plan_mantenimiento.id }}</strong> —
                            Estado:
                            <span class="badge bg-primary-transparent text-primary">
                                {{ solicitud.plan_mantenimiento.estado_plan }}
                            </span>
                        </p>
                        <Link :href="route('mantenimiento.ordenes.show', solicitud.plan_mantenimiento.id)"
                            class="btn btn-sm btn-outline-primary btn-wave mt-2">
                            <i class="ri-eye-line me-1"></i> Ver Orden
                        </Link>
                    </div>
                </div>
            </div>
        </div>
</template>
