<script setup>
import { computed, watch } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import Multiselect from '@vueform/multiselect'

const props = defineProps({
    // [{ id, label, meta }] vehículos que el usuario puede elegir (ver
    // SolicitudMantenimientoController::create()): conductor "puro" -> sus
    // vehículos asignados; cualquier otro usuario (aunque también sea
    // conductor) -> TODOS los activos, con los propios primero (meta.propio).
    vehiculos: Array,
    // [{ id, label, meta }] todos los operarios activos (vacío para el
    // conductor puro, que no elige).
    operarios: { type: Array, default: () => [] },
    // false para un conductor "puro" (la solicitud siempre es sobre sí
    // mismo, sin ambigüedad); true para el resto, que elige el conductor.
    mostrarSelectorConductor: Boolean,
})

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
    horometro_actual:     '',
    observacion:          '',
})

// Copias: Multiselect reescribe `label` de la opción seleccionada (ver
// .ai/rules/js.md).
const vehiculosOpt = computed(() => props.vehiculos.map(vehiculo => ({ ...vehiculo })))
const vehiculoSeleccionado = computed(() => props.vehiculos.find((v) => v.id === form.id_vehiculo) ?? null)
const tipoMedicion = computed(() => vehiculoSeleccionado.value?.meta?.tipo_medicion ?? 'kilometraje')

// Operarios asignados (titular + provisionales) al vehículo elegido: se
// sugieren primero y marcados en la lista de todos los operarios activos.
const conductoresDelVehiculo = computed(() => vehiculoSeleccionado.value?.meta?.conductores_asignados ?? [])
const operariosOpt = computed(() => {
    const asignados = new Set(conductoresDelVehiculo.value)

    return props.operarios
        .map(operario => ({ ...operario, asignado: asignados.has(operario.id) }))
        .sort((a, b) => Number(b.asignado) - Number(a.asignado))
})

watch(() => form.id_vehiculo, () => {
    // La lectura depende del tipo de medición del vehículo.
    form.kilometraje_actual = ''
    form.horometro_actual = ''

    if (!props.mostrarSelectorConductor) {
        return
    }

    // Se sugiere el titular del vehículo (o su único asignado); si no tiene,
    // queda vacío para elegirlo de la lista completa.
    const idsOperarios = new Set(props.operarios.map(o => o.id))
    const titular = vehiculoSeleccionado.value?.meta?.id_conductor
    const asignados = conductoresDelVehiculo.value
    form.id_conductor = idsOperarios.has(titular)
        ? titular
        : (asignados.length === 1 && idsOperarios.has(asignados[0]) ? asignados[0] : '')
    form.clearErrors('id_conductor')
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
                            <Multiselect v-model="form.id_vehiculo" :options="vehiculosOpt" value-prop="id" label="label"
                                :searchable="true" :filter-results="true" placeholder="Buscar vehículo..."
                                no-options-text="Sin vehículos activos" no-results-text="Sin resultados"
                                :class="{ 'is-invalid-multiselect': form.errors.id_vehiculo }">
                                <template #option="{ option }">
                                    <span>{{ option.label }}</span>
                                    <span v-if="mostrarSelectorConductor && option.meta?.propio"
                                        class="badge bg-primary-transparent text-primary fw-normal ms-2">Asignado</span>
                                </template>
                            </Multiselect>
                            <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">
                                {{ form.errors.id_vehiculo }}
                            </div>
                        </div>

                        <!-- Conductor: sólo se muestra a quien no es conductor "puro";
                             cualquier operario activo, con los asignados al vehículo primero. -->
                        <div v-if="mostrarSelectorConductor" class="col-md-6">
                            <label class="form-label fw-medium">
                                Conductor <span class="text-danger">*</span>
                            </label>
                            <Multiselect v-model="form.id_conductor" :options="operariosOpt"
                                value-prop="id" label="label" :searchable="true" :filter-results="true"
                                placeholder="Buscar por CI, nombre o apellido..."
                                no-options-text="No hay conductores activos registrados"
                                no-results-text="Sin resultados"
                                @change="form.clearErrors('id_conductor')"
                                :class="{ 'is-invalid-multiselect': form.errors.id_conductor }">
                                <template #option="{ option }">
                                    <span>{{ option.label }}</span>
                                    <span v-if="option.asignado"
                                        class="badge bg-primary-transparent text-primary fw-normal ms-2">Asignado al vehículo</span>
                                </template>
                            </Multiselect>
                            <div v-if="form.errors.id_conductor" class="text-danger small mt-1">
                                {{ form.errors.id_conductor }}
                            </div>
                            <small v-else-if="form.id_vehiculo && conductoresDelVehiculo.length === 0" class="text-muted">
                                Este vehículo no tiene operario asignado: elige el conductor manualmente.
                            </small>
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

                        <!-- Lectura actual: kilometraje u horómetro según el vehículo -->
                        <div v-if="tipoMedicion === 'horometro'" class="col-sm-6 col-xl-4">
                            <label class="form-label fw-medium">Horómetro Actual</label>
                            <div class="input-group">
                                <input v-model="form.horometro_actual" type="number" min="0"
                                    class="form-control" :class="{ 'is-invalid': form.errors.horometro_actual }"
                                    placeholder="Ej: 1200" />
                                <span class="input-group-text">h</span>
                                <div v-if="form.errors.horometro_actual" class="invalid-feedback">
                                    {{ form.errors.horometro_actual }}
                                </div>
                            </div>
                        </div>
                        <div v-else class="col-sm-6 col-xl-4">
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
