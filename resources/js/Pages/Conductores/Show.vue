<script setup>
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'

const props = defineProps({
    conductor: Object,
    historialAsignaciones: Array,
})



const fotoPreview = ref(
    props.conductor.foto ? `/storage/${props.conductor.foto}` : null,
)

console.log(props.historialAsignaciones);


</script>

<template>

    <Head title="Editar Conductor" />

    <Maindashboard>
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
                        {{ conductor.nombres }} {{ conductor.paterno ?? '' }} {{ conductor.materno ?? '' }}
                    </span>
                </h1>
            </div>
            <Link :href="route('conductores.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <div class="row g-4">
            <!-- Foto -->
            <div class="col-xl-3">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <div class="card-title">Foto del Conductor</div>
                    </div>
                    <div class="card-body d-flex flex-column align-items-center justify-content-center gap-3">
                        <div class="border rounded-3 overflow-hidden"
                            style="width:150px;height:150px;background:#f8f9fa;">
                            <img v-if="fotoPreview" :src="fotoPreview" alt="Foto del conductor" class="w-100 h-100"
                                style="object-fit:cover;" />
                            <div v-else class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                <i class="ri-user-3-line" style="font-size:4rem;"></i>
                            </div>
                        </div>
                        <div class="w-100 text-center">
                            <p class="fs-14 fw-medium mb-2"> {{ conductor.nombres }} {{ conductor.paterno ?? '' }}
                                {{ conductor.materno ?? '' }} </p>
                            <label class="form-label fw-medium">Cambiar </label>

                            <p class="fs-14 fw-medium mb-2">
                                C.I.: <b> {{ conductor.ci }} </b> </p>

                            <small class="text-muted d-none">Dejar vacío para conservar la foto actual.</small>
                        </div>
                        <div class="text-muted">

                            <div class="mb-2 d-flex align-items-center gap-1 flex-wrap"> <span
                                    class="avatar avatar-sm avatar-rounded text-default">
                                    <i class="ri-calendar-line align-middle fs-15"></i>
                                </span>
                                <span class="fw-medium text-default">F. Nacimiento: </span>
                                {{ conductor.f_nacimiento_formatted }} /
                                {{ conductor.edad }} años
                            </div>
                            <div class="mb-2 d-flex align-items-center gap-1 flex-wrap"> <span
                                    class="avatar avatar-sm avatar-rounded text-default">
                                    <i class="ri-phone-line align-middle fs-15"></i>
                                </span>
                                <span class="fw-medium text-default">Celular: </span>
                                <a :href="`tel:${conductor.celular}`" class="text-decoration-none">
                                    {{ conductor.celular ?? 'N/A' }}
                                </a>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-9">
                <div class="card custom-card overflow-hidden">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            <h6 class="mb-0">Historial de Asignaciones</h6>
                        </div>
                        <a href="javascript:void(0);" class="text-muted fs-12 text-decoration-underline d-none">View
                            All<i class="ti ti-arrow-narrow-right"></i></a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li v-for="(vehiculo, index) in historialAsignaciones" :key="index" class="list-group-item">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="lh-1">
                                        <span class="avatar avatar-lg bg-light border border-dashed p-1">
                                            <img :src="vehiculo.fotografia" :alt="vehiculo.nro_placa">
                                        </span>
                                    </div>
                                    <div class="flex-fill">
                                        <span class="fw-semibold mb-1 d-block">
                                            {{ vehiculo.nro_placa }}
                                        </span>
                                        <div class="d-flex align-items-center gap-2 fw-medium">
                                            <div class="fs-12 text-muted">{{ vehiculo.marca }}</div>
                                            <div class="vr"></div>
                                            <span :class="vehiculo.stockClass + ' fs-12'">
                                                {{ vehiculo.anio }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex-fill">
                                        <span class="mb-1 d-block">
                                            {{ vehiculo.pivot.detalle }}
                                        </span>

                                    </div>
                                    <div class="flex-fill">
                                        <span class="d-block fw-semibold">{{
                                            vehiculo.pivot.fecha_asignacion }}</span>
                                        <span class="fs-12 d-block text-muted">Fecha de
                                            asignación</span>

                                    </div>
                                    <div class="text-end">
                                        <span class="d-block fw-semibold">{{
                                            vehiculo.pivot.fecha_culminacion }}</span>
                                        <span class="fs-12 d-block text-muted">Fecha de
                                            culminación</span>
                                    </div>
                                </div>
                            </li>

                            <li v-if="historialAsignaciones.length === 0"
                                class="list-group-item p-5 text-center text-muted">
                                No hay historial de asignaciones para este conductor.

                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

        <!-- Botones -->

    </Maindashboard>
</template>
