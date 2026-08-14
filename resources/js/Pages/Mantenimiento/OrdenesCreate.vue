<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })

const props = defineProps({
    vehiculos: Array,
    tiposMantenimiento: Array,
    talleres: Array,
    solicitudesPendientes: Array,
    solicitudPreseleccionada: Object,
})

// Si llegó preseleccionada una solicitud, pre-rellenar el formulario
const form = useForm({
    id_vehiculo:                  props.solicitudPreseleccionada?.id_vehiculo ?? '',
    id_tipo_mantenimiento:        '',
    id_solicitud_mantenimiento:   props.solicitudPreseleccionada?.id ?? '',
    id_taller:                    '',
    tipo_mantenimiento:           props.solicitudPreseleccionada?.tipo_mantenimiento ?? 'PREVENTIVO',
    tipo_orden:                   'INTERNO',
    descripcion_trabajo_ordenado: props.solicitudPreseleccionada?.descripcion_problema ?? '',
    kilometraje_programado:       '',
    fecha_programada:             '',
    frecuencia_km:                '',
    frecuencia_mes:               '',
    observacion:                  '',
})

// Al seleccionar solicitud origen, copiar datos
function onSolicitudChange() {
    const s = props.solicitudesPendientes.find(x => x.id == form.id_solicitud_mantenimiento)
    if (s) {
        form.id_vehiculo            = s.id_vehiculo
        form.tipo_mantenimiento     = s.tipo_mantenimiento
        form.descripcion_trabajo_ordenado = s.descripcion_problema
    }
}

const requiereTaller = computed(() => form.tipo_orden === 'EXTERNO')

function submit() {
    form.post(route('mantenimiento.ordenes.store'))
}
</script>

<template>
    <Head title="Nueva Orden de Mantenimiento" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.ordenes.index')">Órdenes Mantenimiento</Link>
                        </li>
                        <li class="breadcrumb-item active">Nueva Orden</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Generar Orden de Trabajo</h1>
            </div>
            <Link :href="route('mantenimiento.ordenes.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <!-- Solicitud origen (si la hay) -->
        <div v-if="solicitudPreseleccionada" class="alert alert-info d-flex align-items-start gap-2 mb-4">
            <i class="ri-information-line fs-20 mt-1"></i>
            <div>
                <strong>Solicitud origen #{{ solicitudPreseleccionada.id }}</strong> —
                {{ solicitudPreseleccionada.tipo_mantenimiento }} —
                Vehículo: {{ solicitudPreseleccionada.vehiculo?.nro_placa }}
                <br />
                <span class="text-muted">{{ solicitudPreseleccionada.descripcion_problema }}</span>
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="ri-file-list-3-line me-2 text-primary"></i>
                        Paso 2 – Orden de Trabajo Interno / Externo
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Solicitud origen -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Solicitud de Origen (opcional)</label>
                            <select v-model="form.id_solicitud_mantenimiento" class="form-select"
                                @change="onSolicitudChange">
                                <option value="">— Sin solicitud previa —</option>
                                <option v-for="s in solicitudesPendientes" :key="s.id" :value="s.id">
                                    #{{ s.id }} – {{ s.vehiculo?.nro_placa }} – {{ s.tipo_mantenimiento }}
                                </option>
                            </select>
                        </div>

                        <!-- Vehículo -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Vehículo <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.id_vehiculo" class="form-select"
                                :class="{ 'is-invalid': form.errors.id_vehiculo }">
                                <option value="">— Seleccione —</option>
                                <option v-for="v in vehiculos" :key="v.id" :value="v.id">
                                    {{ v.nro_placa }} – {{ v.marca }}
                                </option>
                            </select>
                            <div v-if="form.errors.id_vehiculo" class="invalid-feedback">
                                {{ form.errors.id_vehiculo }}
                            </div>
                        </div>

                        <!-- Tipo de mantenimiento (catálogo) -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Tipo de Mantenimiento <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.id_tipo_mantenimiento" class="form-select"
                                :class="{ 'is-invalid': form.errors.id_tipo_mantenimiento }">
                                <option value="">— Seleccione —</option>
                                <option v-for="t in tiposMantenimiento" :key="t.id" :value="t.id">
                                    {{ t.tipo_mantenimiento }}
                                </option>
                            </select>
                            <div v-if="form.errors.id_tipo_mantenimiento" class="invalid-feedback">
                                {{ form.errors.id_tipo_mantenimiento }}
                            </div>
                        </div>

                        <!-- Preventivo / Correctivo -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Categoría <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.tipo_mantenimiento" class="form-select"
                                :class="{ 'is-invalid': form.errors.tipo_mantenimiento }">
                                <option value="PREVENTIVO">Preventivo</option>
                                <option value="CORRECTIVO">Correctivo</option>
                            </select>
                        </div>

                        <!-- Interno / Externo -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Tipo de Orden <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.tipo_orden" class="form-select"
                                :class="{ 'is-invalid': form.errors.tipo_orden }">
                                <option value="INTERNO">Interno</option>
                                <option value="EXTERNO">Externo (Taller)</option>
                            </select>
                        </div>

                        <!-- Taller (sólo externo) -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Taller
                                <span v-if="requiereTaller" class="text-danger">*</span>
                                <span v-else class="text-muted">(sólo externo)</span>
                            </label>
                            <select v-model="form.id_taller" class="form-select"
                                :disabled="!requiereTaller"
                                :class="{ 'is-invalid': form.errors.id_taller }">
                                <option value="">— Seleccione taller —</option>
                                <option v-for="t in talleres" :key="t.id" :value="t.id">
                                    {{ t.razon_social }} ({{ t.nit }})
                                </option>
                            </select>
                            <div v-if="form.errors.id_taller" class="invalid-feedback">{{ form.errors.id_taller }}</div>
                        </div>

                        <!-- Descripción del trabajo a realizar -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Descripción del Trabajo a Realizar</label>
                            <textarea v-model="form.descripcion_trabajo_ordenado" rows="3"
                                class="form-control" :class="{ 'is-invalid': form.errors.descripcion_trabajo_ordenado }"
                                placeholder="Detalle el trabajo que se debe realizar..."></textarea>
                            <div v-if="form.errors.descripcion_trabajo_ordenado" class="invalid-feedback">
                                {{ form.errors.descripcion_trabajo_ordenado }}
                            </div>
                        </div>

                        <div class="col-12"><hr class="my-1" /><p class="text-muted mb-0 small">Programación (opcional)</p></div>

                        <!-- Km programado -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Kilometraje Programado</label>
                            <div class="input-group">
                                <input v-model="form.kilometraje_programado" type="number" min="0"
                                    class="form-control" placeholder="Ej: 90000" />
                                <span class="input-group-text">km</span>
                            </div>
                        </div>

                        <!-- Fecha programada -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Fecha Programada</label>
                            <input v-model="form.fecha_programada" type="date" class="form-control" />
                        </div>

                        <!-- Frecuencia km -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Frecuencia (km)</label>
                            <div class="input-group">
                                <input v-model="form.frecuencia_km" type="number" min="0"
                                    class="form-control" placeholder="Ej: 5000" />
                                <span class="input-group-text">km</span>
                            </div>
                        </div>

                        <!-- Frecuencia meses -->
                        <div class="col-sm-6 col-xl-3">
                            <label class="form-label fw-medium">Frecuencia (meses)</label>
                            <div class="input-group">
                                <input v-model="form.frecuencia_mes" type="number" min="0"
                                    class="form-control" placeholder="Ej: 6" />
                                <span class="input-group-text">meses</span>
                            </div>
                        </div>

                        <!-- Observación -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Observación</label>
                            <textarea v-model="form.observacion" rows="2"
                                class="form-control" placeholder="Observaciones adicionales..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <Link :href="route('mantenimiento.ordenes.index')" class="btn btn-outline-secondary btn-wave">
                        Cancelar
                    </Link>
                    <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-file-list-3-line me-1"></i>
                        Generar Orden de Trabajo
                    </button>
                </div>
            </div>
        </form>
</template>
