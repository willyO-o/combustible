<script setup>
import { computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import Multiselect from '@vueform/multiselect'

const props = defineProps({
    // [{ id, label, meta }] vehículos que el usuario puede elegir: ya viene
    // filtrado por rol desde el backend (conductor "puro" -> sus vehículos
    // asignados; jefe-area -> los de sus áreas a cargo, incluso si además es
    // conductor, ese rol prevalece; cualquier otro rol -> todos los activos).
    // Ver SolicitudMantenimientoController::vehiculosDisponibles().
    vehiculos: Array,
    // false para un conductor "puro" (la solicitud siempre es sobre sí
    // mismo, sin ambigüedad); true para jefe-area/administrador/etc., que
    // deben elegir entre los conductores realmente asignados al vehículo.
    mostrarSelectorConductor: Boolean,
})

// El combo nativo se reemplaza por un Multiselect con filtrado local (sin
// búsqueda remota, ver SearchSelect.vue) para no volverse impracticable
// cuando hay muchos vehículos registrados. props.vehiculos ya llega en
// formato {id, label, meta} desde el backend.

// El conductor sólo se pide cuando mostrarSelectorConductor es true; si no,
// el modelo lo asigna automáticamente a partir del usuario autenticado (ver
// CreateSolicitudMantenimientoAction). La fecha/hora de la solicitud
// también se asigna en el servidor, así que tampoco se pide aquí.
const form = useForm({
    id_vehiculo:          '',
    id_conductor:         '',
    tipo_mantenimiento:   'PREVENTIVO',
    descripcion_problema: '',
    kilometraje_actual:   '',
    observacion:          '',
})

// Conductores activos/provisionales del vehículo seleccionado (sólo viene
// poblado desde el backend cuando mostrarSelectorConductor es true).
const conductoresDelVehiculo = computed(() => {
    const vehiculoSeleccionado = props.vehiculos.find((v) => v.id === form.id_vehiculo)
    return vehiculoSeleccionado?.meta?.conductoresAsignados ?? []
})

watch(() => form.id_vehiculo, (val) => {
    if (!props.mostrarSelectorConductor) {
        return
    }

    if (!val) {
        form.id_conductor = ''
        return
    }

    const vehiculoSeleccionado = props.vehiculos.find((v) => v.id === val)
    const conductores = vehiculoSeleccionado?.meta?.conductoresAsignados ?? []

    // Si el conductor ya elegido no pertenece al nuevo vehículo, se limpia;
    // si sólo queda una opción, se autoselecciona.
    if (!conductores.some((c) => c.id === form.id_conductor)) {
        form.id_conductor = conductores.length === 1 ? conductores[0].id : ''
    }
})

function submit() {
    form.post(route('mantenimiento.solicitudes.store'))
}
</script>

<template>
    <Head title="Nueva Solicitud de Mantenimiento" />
        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><Link :href="route('dashboard')">Inicio</Link></li>
                        <li class="breadcrumb-item">
                            <Link :href="route('mantenimiento.solicitudes.index')">Solicitudes Mantenimiento</Link>
                        </li>
                        <li class="breadcrumb-item active">Nueva Solicitud</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Registrar Solicitud de Mantenimiento</h1>
            </div>
            <Link :href="route('mantenimiento.solicitudes.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <form @submit.prevent="submit">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="ri-alarm-warning-line me-2 text-warning"></i>
                        Paso 1 – Registro de Solicitud / Alarma de Mantenimiento
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Vehículo -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Vehículo <span class="text-danger">*</span>
                            </label>
                            <Multiselect v-model="form.id_vehiculo" :options="vehiculos" value-prop="id" label="label"
                                :searchable="true" :filter-results="true" placeholder="Buscar vehículo..."
                                no-options-text="Sin vehículos activos" no-results-text="Sin resultados"
                                :class="{ 'is-invalid-multiselect': form.errors.id_vehiculo }" />
                            <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">
                                {{ form.errors.id_vehiculo }}
                            </div>
                        </div>

                        <!-- Conductor: sólo se muestra a quien no es conductor "puro"
                             (jefe-area/administrador/etc.), ya que un vehículo puede
                             tener varios conductores asignados. -->
                        <div v-if="mostrarSelectorConductor" class="col-md-6">
                            <label class="form-label fw-medium">
                                Conductor <span class="text-danger">*</span>
                            </label>
                            <Multiselect v-model="form.id_conductor" :options="conductoresDelVehiculo"
                                value-prop="id" label="label" :searchable="true" :filter-results="true"
                                :disabled="!form.id_vehiculo" placeholder="Selecciona un conductor..."
                                no-options-text="Este vehículo no tiene conductores asignados"
                                no-results-text="Sin resultados"
                                :class="{ 'is-invalid-multiselect': form.errors.id_conductor }" />
                            <div v-if="form.errors.id_conductor" class="text-danger small mt-1">
                                {{ form.errors.id_conductor }}
                            </div>
                        </div>

                        <!-- Tipo -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">
                                Tipo de Mantenimiento <span class="text-danger">*</span>
                            </label>
                            <select v-model="form.tipo_mantenimiento" class="form-select"
                                :class="{ 'is-invalid': form.errors.tipo_mantenimiento }">
                                <option value="PREVENTIVO">Preventivo</option>
                                <option value="CORRECTIVO">Correctivo</option>
                            </select>
                            <div v-if="form.errors.tipo_mantenimiento" class="invalid-feedback">
                                {{ form.errors.tipo_mantenimiento }}
                            </div>
                        </div>

                        <!-- Km actual -->
                        <div class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Kilometraje Actual</label>
                            <div class="input-group">
                                <input v-model="form.kilometraje_actual" type="number" min="0"
                                    class="form-control" :class="{ 'is-invalid': form.errors.kilometraje_actual }"
                                    placeholder="Ej: 85000" />
                                <span class="input-group-text">km</span>
                                <div v-if="form.errors.kilometraje_actual" class="invalid-feedback">
                                    {{ form.errors.kilometraje_actual }}
                                </div>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="col-12">
                            <label class="form-label fw-medium">
                                Descripción del Problema / Mantenimiento Preventivo
                                <span class="text-danger">*</span>
                            </label>
                            <textarea v-model="form.descripcion_problema" rows="4"
                                class="form-control" :class="{ 'is-invalid': form.errors.descripcion_problema }"
                                placeholder="Describa la falla detectada o el mantenimiento preventivo requerido...">
                            </textarea>
                            <div v-if="form.errors.descripcion_problema" class="invalid-feedback">
                                {{ form.errors.descripcion_problema }}
                            </div>
                        </div>

                        <!-- Observación -->
                        <div class="col-12">
                            <label class="form-label fw-medium">Observación adicional</label>
                            <textarea v-model="form.observacion" rows="2"
                                class="form-control" :class="{ 'is-invalid': form.errors.observacion }"
                                placeholder="Observaciones opcionales...">
                            </textarea>
                            <div v-if="form.errors.observacion" class="invalid-feedback">
                                {{ form.errors.observacion }}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <Link :href="route('mantenimiento.solicitudes.index')" class="btn btn-outline-secondary btn-wave">
                        Cancelar
                    </Link>
                    <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                        <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="ri-send-plane-line me-1"></i>
                        Enviar Solicitud
                    </button>
                </div>
            </div>
        </form>
</template>
