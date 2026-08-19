<script setup>
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import InputError from '@/Components/InputError.vue'
import Multiselect from '@vueform/multiselect'

const props = defineProps({
    tipo:               Object, // null en creación
    tiposMantenimiento: Array,
    gruposVehiculo:     Array,
})

const isEditing = computed(() => !!props.tipo)

const form = useForm({
    ...(isEditing.value ? { _method: 'PUT' } : {}),
    tipo_vehiculo:        props.tipo?.tipo_vehiculo ?? '',
    estado_tipo_vehiculo: props.tipo?.estado_tipo_vehiculo ?? 'ACTIVO',
    id_grupo_vehiculo:    props.tipo?.id_grupo_vehiculo ?? '',
    intervalos: (props.tipo?.intervalos ?? []).map((i) => ({
        id_tipo_mantenimiento: i.id_tipo_mantenimiento,
        tipo_medicion:         i.tipo_medicion,
        frecuencia:            i.frecuencia,
    })),
})

function agregarIntervalo() {
    form.intervalos.push({ id_tipo_mantenimiento: null, tipo_medicion: 'kilometraje', frecuencia: '' })
}

function eliminarIntervalo(idx) {
    form.intervalos.splice(idx, 1)
}

// Para cada fila, sólo se pueden elegir tipos de mantenimiento que no estén
// ya asignados en otra fila (sólo un intervalo por tipo de mantenimiento).
function opcionesDisponibles(idx) {
    const elegidosEnOtrasFilas = form.intervalos
        .filter((_, i) => i !== idx)
        .map((i) => i.id_tipo_mantenimiento)
    return props.tiposMantenimiento.filter((t) => !elegidosEnOtrasFilas.includes(t.id))
}

function submit() {
    if (isEditing.value) {
        form.post(route('tipos-vehiculo.update', props.tipo.id))
    } else {
        form.post(route('tipos-vehiculo.store'))
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Editar Tipo de Vehículo' : 'Nuevo Tipo de Vehículo'" />

        <!-- Breadcrumb -->
        <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item">
                            <Link :href="route('tipos-vehiculo.index')">Tipos de Vehículo</Link>
                        </li>
                        <li class="breadcrumb-item active">{{ isEditing ? 'Editar' : 'Nuevo' }}</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">
                    <template v-if="isEditing">
                        Editar: <span class="text-primary">{{ tipo.tipo_vehiculo }}</span>
                    </template>
                    <template v-else>Registrar Tipo de Vehículo</template>
                </h1>
            </div>
            <Link :href="route('tipos-vehiculo.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-8 col-lg-10">
                <form @submit.prevent="submit">
                    <div class="card custom-card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="ri-car-line me-2"></i>Datos del Tipo de Vehículo
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">

                                <!-- Tipo de vehículo -->
                                <div class="col-12">
                                    <label for="tipo_vehiculo" class="form-label fw-medium">
                                        Tipo de Vehículo
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input
                                        id="tipo_vehiculo"
                                        v-model="form.tipo_vehiculo"
                                        type="text"
                                        class="form-control"
                                        :class="{ 'is-invalid': form.errors.tipo_vehiculo }"
                                        placeholder="Ej: Camioneta, Bus, Motocicleta..."
                                        maxlength="150"
                                        autofocus
                                    />
                                    <InputError :message="form.errors.tipo_vehiculo" class="mt-1" />
                                </div>

                                <!-- Grupo de Vehículo -->
                                <div class="col-12">
                                    <label for="id_grupo_vehiculo" class="form-label fw-medium">
                                        Grupo de Vehículo
                                        <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        id="id_grupo_vehiculo"
                                        v-model="form.id_grupo_vehiculo"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors.id_grupo_vehiculo }"
                                    >
                                        <option value="">— Seleccionar —</option>
                                        <option v-for="g in gruposVehiculo" :key="g.id" :value="g.id">
                                            {{ g.grupo_vehiculo }}
                                        </option>
                                    </select>
                                    <InputError :message="form.errors.id_grupo_vehiculo" class="mt-1" />
                                </div>

                                <!-- Estado -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Estado
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="d-flex gap-4 mt-1">
                                        <div class="form-check">
                                            <input
                                                id="estado_activo"
                                                v-model="form.estado_tipo_vehiculo"
                                                class="form-check-input"
                                                type="radio"
                                                value="ACTIVO"
                                            />
                                            <label for="estado_activo" class="form-check-label">
                                                <span class="badge bg-success-transparent text-success">ACTIVO</span>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input
                                                id="estado_inactivo"
                                                v-model="form.estado_tipo_vehiculo"
                                                class="form-check-input"
                                                type="radio"
                                                value="INACTIVO"
                                            />
                                            <label for="estado_inactivo" class="form-check-label">
                                                <span class="badge bg-danger-transparent text-danger">INACTIVO</span>
                                            </label>
                                        </div>
                                    </div>
                                    <InputError :message="form.errors.estado_tipo_vehiculo" class="mt-1" />
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Intervalos de mantenimiento -->
                    <div class="card custom-card mt-4">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div class="card-title">
                                <i class="ri-tools-line me-2"></i>Intervalos de Mantenimiento
                            </div>
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary btn-wave"
                                :disabled="form.intervalos.length >= tiposMantenimiento.length"
                                @click="agregarIntervalo"
                            >
                                <i class="ri-add-line me-1"></i> Agregar Intervalo
                            </button>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Referencia para futuras alertas de mantenimiento. Un tipo de mantenimiento sólo
                                puede tener un intervalo por cada tipo de vehículo.
                            </p>

                            <div v-if="form.intervalos.length === 0" class="text-center text-muted py-4">
                                <i class="ri-tools-line fs-3 d-block mb-2"></i>
                                No se configuraron intervalos de mantenimiento.
                            </div>

                            <div
                                v-for="(intervalo, idx) in form.intervalos"
                                :key="idx"
                                class="row g-2 align-items-start mb-3 pb-3"
                                :class="{ 'border-bottom': idx < form.intervalos.length - 1 }"
                            >
                                <!-- Tipo de mantenimiento -->
                                <div class="col-sm-6 col-lg-5">
                                    <label class="form-label small">
                                        Tipo de Mantenimiento <span class="text-danger">*</span>
                                    </label>
                                    <Multiselect
                                        v-model="intervalo.id_tipo_mantenimiento"
                                        :options="opcionesDisponibles(idx)"
                                        value-prop="id"
                                        label="tipo_mantenimiento"
                                        :searchable="true"
                                        placeholder="Buscar tipo de mantenimiento..."
                                        no-options-text="No hay tipos disponibles"
                                        no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors[`intervalos.${idx}.id_tipo_mantenimiento`] }"
                                    />
                                    <InputError :message="form.errors[`intervalos.${idx}.id_tipo_mantenimiento`]" class="mt-1" />
                                </div>

                                <!-- Tipo de medición -->
                                <div class="col-sm-3 col-lg-3">
                                    <label class="form-label small">
                                        Medición <span class="text-danger">*</span>
                                    </label>
                                    <select
                                        v-model="intervalo.tipo_medicion"
                                        class="form-select"
                                        :class="{ 'is-invalid': form.errors[`intervalos.${idx}.tipo_medicion`] }"
                                    >
                                        <option value="kilometraje">Kilometraje</option>
                                        <option value="horometro">Horómetro</option>
                                    </select>
                                    <InputError :message="form.errors[`intervalos.${idx}.tipo_medicion`]" class="mt-1" />
                                </div>

                                <!-- Frecuencia -->
                                <div class="col-sm-2 col-lg-3">
                                    <label class="form-label small">
                                        Frecuencia <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input
                                            v-model="intervalo.frecuencia"
                                            type="number"
                                            min="1"
                                            class="form-control"
                                            :class="{ 'is-invalid': form.errors[`intervalos.${idx}.frecuencia`] }"
                                            :placeholder="intervalo.tipo_medicion === 'horometro' ? 'Ej: 250' : 'Ej: 5000'"
                                        />
                                        <span class="input-group-text">
                                            {{ intervalo.tipo_medicion === 'horometro' ? 'h' : 'km' }}
                                        </span>
                                    </div>
                                    <InputError :message="form.errors[`intervalos.${idx}.frecuencia`]" class="mt-1" />
                                </div>

                                <!-- Eliminar -->
                                <div class="col-sm-1 col-lg-1 d-flex align-items-end justify-content-end">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-icon btn-danger-light mb-1"
                                        title="Eliminar intervalo"
                                        @click="eliminarIntervalo(idx)"
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Indicador de cambios -->
                    <div v-if="isEditing && form.isDirty" class="alert alert-warning py-2 mt-3 mb-0 d-flex align-items-center gap-2">
                        <i class="ri-error-warning-line"></i>
                        <small>Tienes cambios sin guardar.</small>
                    </div>

                    <!-- Botones -->
                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <Link
                            :href="route('tipos-vehiculo.index')"
                            class="btn btn-outline-secondary btn-wave"
                        >
                            Cancelar
                        </Link>
                        <button
                            type="submit"
                            class="btn btn-primary btn-wave"
                            :disabled="form.processing || (isEditing && !form.isDirty)"
                        >
                            <span
                                v-if="form.processing"
                                class="spinner-border spinner-border-sm me-1"
                                role="status"
                            ></span>
                            <i v-else class="ri-save-line me-1"></i>
                            <template v-if="isEditing">{{ form.processing ? 'Actualizando...' : 'Actualizar' }}</template>
                            <template v-else>{{ form.processing ? 'Guardando...' : 'Guardar' }}</template>
                        </button>
                    </div>
                </form>
            </div>
        </div>
</template>
