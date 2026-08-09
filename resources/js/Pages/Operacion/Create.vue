<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

import Multiselect from '@vueform/multiselect'
import '@vueform/multiselect/themes/default.css'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import Modal from '@/Components/Modal.vue'; // Ajusta la ruta según tu proyecto

import axios from 'axios'

import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'


const estaAbiertoModal = ref(false) // Estado para controlar la visibilidad del modal;

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('md') // Devuelve true si la pantalla es menor a 768px


const props = defineProps({
    conductor: Object,   // { id, label } del conductor asignado al vehículo (si hay uno)
    vehiculosAsignados: Array, // [{ id, label }] vehículos asignados al conductor (si hay uno)
})

const vehiculosAsignadosOpt = ref(props.vehiculosAsignados || [])





/* ------------------------------------------------------------------ */
/*  Form                                                               */
/* ------------------------------------------------------------------ */
const today = new Date().toISOString().slice(0, 16) // yyyy-MM-ddTHH:mm

const turnoDefault = function () {
    const hour = new Date().getHours()

    if (hour >= 6 && hour < 18) {
        return 'DIA'
    } else {
        return 'NOCHE'
    }
}

const form = useForm({
    id_vehiculo: null,
    turno: turnoDefault(),
    fecha_inicio: today,
    fecha_fin: today,
    kilometraje_inicio: '',
    kilometraje_fin: '',
    horometro_inicio: '',
    horometro_fin: '',
    horas_trabajadas: '',
    estado: 'FINALIZADO',
    observaciones: '',
    notificar_observaciones: false,
    actividades_realizadas: []
})

/* ------------------------------------------------------------------ */
/*  Auto-relleno al seleccionar vehículo                               */
/* ------------------------------------------------------------------ */
const loadingVehiculo = ref(false)

const formActividad = useForm({
    actividad: '',
    id_actividad: null,
    lugar: '',
    origen: '',
    destino: '',
    cantidad: '',
    unidad_medida: '',
    hora_inicio: '',
    hora_fin: '',
    detalle: '',
})

const horaMinTurno = computed(() => {
    if (form.turno === 'DIA') {
        return '06:00'
    } else if (form.turno === 'NOCHE') {
        return '18:00'
    }
    return ''
})

const horaMaxTurno = computed(() => {
    if (form.turno === 'DIA') {
        return '17:59'
    } else if (form.turno === 'NOCHE') {
        return '05:59'
    }
    return ''
})

const tipoMedicion = ref('') // 'kilometraje' o 'horometro'

watch(() => form.id_vehiculo, async (val) => {


    tipoMedicion.value = ''

    if (!val) return

    const vehiculoSelected = vehiculosAsignadosOpt.value.find(v => v.id === val)

    if (vehiculoSelected) {
        tipoMedicion.value = vehiculoSelected.meta.tipo_medicion
    }




})





function quitarActividad(idx) {
    form.actividades_realizadas.splice(idx, 1)
}




/* ------------------------------------------------------------------ */
/*  Envío                                                              */
/* ------------------------------------------------------------------ */
function submit() {
    form
        .transform((data) => {
            const out = {
                ...data,
                id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
            }
            return out
        })
        .post(route('operacion-diaria.store'), { forceFormData: true })
}

const agregarActividad = () => {


    formActividad.post(route('operacion-diaria.agregar-actividad'), {
        onSuccess: () => {
            // Agregar la actividad al arreglo de actividades_realizadas
            form.actividades_realizadas.push({ ...formActividad });

            // Limpiar el formulario de actividad
            formActividad.actividad = '';
            formActividad.id_actividad = null;
            formActividad.lugar = '';
            formActividad.origen = '';
            formActividad.destino = '';
            formActividad.cantidad = '';
            formActividad.unidad_medida = '';
            formActividad.hora_inicio = '';
            formActividad.hora_fin = '';
            formActividad.detalle = '';

            // Cerrar el modal
            estaAbiertoModal.value = false;
        },
        onError: () => {
            // Manejar errores si es necesario
        }
    });

}







onMounted(() => {
    // Si hay un conductor asignado desde el servidor, auto-seleccionarlo

    if (props.conductor) {

        if (vehiculosAsignadosOpt.value.length > 0) {
            form.id_vehiculo = vehiculosAsignadosOpt.value[0].id
        }
    }

})
</script>

<template>

    <Head title="Nueva Carga de Combustible" />
    <Maindashboard>
        <!-- Breadcrumb -->
        <div v-if="!isMobile"
            class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
            <div>
                <nav>
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item">
                            <Link :href="route('dashboard')">Inicio</Link>
                        </li>
                        <li class="breadcrumb-item">
                            <Link :href="route('cargas.index')">Cargas</Link>
                        </li>
                        <li class="breadcrumb-item active">Nueva</li>
                    </ol>
                </nav>
                <h1 class="page-title fw-medium fs-18 mb-0">Registrar Actividades</h1>
            </div>
            <Link :href="route('cargas.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>
        <div v-else class="  mb-2 mt-1">
            <h1 class="page-title text-center fw-medium fs-18 mb-0">Registrar Actividades</h1>

        </div>

        <form @submit.prevent="submit">
            <div class="row g-4">

                <!-- ====== VEHÍCULO ====== -->
                <div class="col-xl-6">
                    <div class=" custom-card h-100" :class="{ 'card': !isMobile, }">

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-12 justify-content-center d-flex flex-column gap-2">
                                    <label class="fw-medium mb-0">
                                        Turno
                                    </label>
                                    <div class="btn-group gap-4" role="group"
                                        aria-label="Basic radio toggle button group">

                                        <!-- Opción Día -->
                                        <input type="radio" class="btn-check" name="btnradio" id="btnradio1"
                                            autocomplete="off" value="DIA" v-model="form.turno">
                                        <label class="btn btn-outline-primary btn-wave rounded-pill" for="btnradio1">
                                            <i class="ri-sun-line text-warning"></i>
                                            Día
                                        </label>

                                        <!-- Opción Noche -->
                                        <input type="radio" class="btn-check" name="btnradio" id="btnradio2"
                                            autocomplete="off" value="NOCHE" v-model="form.turno">
                                        <label class="btn btn-outline-primary btn-wave rounded-pill" for="btnradio2">
                                            <i class="ri-moon-line text-dark"></i>
                                            Noche
                                        </label>

                                    </div>




                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Vehículo <span class="text-danger">*</span>
                                    </label>
                                    <Multiselect v-if="props.conductor" v-model="form.id_vehiculo"
                                        :options="vehiculosAsignadosOpt" value-prop="id" label="label"
                                        placeholder="Seleccionar vehículo" />

                                    <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">{{
                                        form.errors.id_vehiculo }}</div>
                                    <div v-if="loadingVehiculo" class="text-muted small mt-1">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Cargando datos del
                                        vehículo...
                                    </div>
                                </div>

                                <!-- Tipo Combustible (auto-llenado) -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Fecha y hora inicio <span class="text-danger">*</span>

                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                        <input v-model="form.fecha_inicio" type="datetime-local" class="form-control"
                                            :class="{ 'is-invalid': form.errors.fecha_inicio }" />

                                        <div v-if="form.errors.fecha_inicio" class="invalid-feedback">{{
                                            form.errors.fecha_inicio }}</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Fecha y hora fin <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-calendar"></i></span>
                                        <input v-model="form.fecha_fin" type="datetime-local" class="form-control"
                                            :class="{ 'is-invalid': form.errors.fecha_fin }" />

                                        <div v-if="form.errors.fecha_fin" class="invalid-feedback">{{
                                            form.errors.fecha_fin }}</div>
                                    </div>
                                </div>

                                <div v-if="tipoMedicion === 'kilometraje'" class="col-sm-6">
                                    <label class="form-label fw-medium">Kilometraje inicial</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-speedometer2"></i></span>
                                        <input v-model="form.kilometraje_inicio" type="text" class="form-control"
                                            v-decimal="1" :class="{ 'is-invalid': form.errors.kilometraje_inicio }"
                                            placeholder="0" step="0.1" />
                                        <span class="input-group-text">km</span>
                                        <div v-if="form.errors.kilometraje_inicio" class="invalid-feedback">{{
                                            form.errors.kilometraje_inicio }}</div>
                                    </div>

                                </div>
                                <div v-if="tipoMedicion === 'kilometraje'" class="col-sm-6">
                                    <label class="form-label fw-medium">Kilometraje final</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-speedometer2"></i></span>

                                        <input v-model="form.kilometraje_fin" type="text" class="form-control"
                                            v-decimal="1" :class="{ 'is-invalid': form.errors.kilometraje_fin }"
                                            placeholder="0" step="0.1" />
                                        <span class="input-group-text">km</span>
                                        <div v-if="form.errors.kilometraje_fin" class="invalid-feedback">{{
                                            form.errors.kilometraje_fin }}</div>
                                    </div>

                                </div>

                                <div v-if="tipoMedicion === 'horometro'" class="col-sm-6">
                                    <label class="form-label fw-medium">Horometro</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>

                                        <input v-model="form.horometro_inicio" type="text" class="form-control"
                                            v-decimal="1" :class="{ 'is-invalid': form.errors.horometro_inicio }"
                                            placeholder="0" step="0.1" />
                                        <span class="input-group-text">h</span>
                                        <div v-if="form.errors.horometro_inicio" class="invalid-feedback">{{
                                            form.errors.horometro_inicio
                                        }}</div>
                                    </div>

                                </div>

                                <div v-if="tipoMedicion === 'horometro'" class="col-sm-6">
                                    <label class="form-label fw-medium">Horometro</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>

                                        <input v-model="form.horometro_fin" type="text" class="form-control"
                                            v-decimal="1" :class="{ 'is-invalid': form.errors.horometro_fin }"
                                            placeholder="0" step="0.1" />
                                        <span class="input-group-text">h</span>
                                        <div v-if="form.errors.horometro_fin" class="invalid-feedback">{{
                                            form.errors.horometro_fin
                                        }}</div>
                                    </div>

                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Horas trabajadas <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-clock"></i></span>
                                        <input v-model="form.horas_trabajadas" type="text" class="form-control"
                                            v-decimal="1" :class="{ 'is-invalid': form.errors.horas_trabajadas }"
                                            placeholder="0" step="0.1" />
                                        <span class="input-group-text">h</span>
                                        <div v-if="form.errors.horas_trabajadas" class="invalid-feedback">{{
                                            form.errors.horas_trabajadas }}</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Observaciones <span class="text-danger">*</span>
                                    </label>
                                    <textarea v-model="form.observaciones" class="form-control" rows="4"
                                        :class="{ 'is-invalid': form.errors.observaciones }"
                                        placeholder="Observaciones"></textarea>
                                    <div v-if="form.errors.observaciones" class="invalid-feedback">{{
                                        form.errors.observaciones }}</div>
                                </div>

                                <div class="col-12">


                                    <div class="form-check form-check-lg d-flex align-items-center">
                                        <input class="form-check-input" type="checkbox" value="" id="checkebox-lg"
                                            v-model="form.notificar_observaciones" />
                                        <label class="form-check-label" for="checkebox-lg">
                                            Notificar observaciones al encargado </label>
                                    </div>

                                    <div v-if="form.errors.notificar_observaciones" class="invalid-feedback">{{
                                        form.errors.notificar_observaciones }}</div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>





                <!-- ======  ====== -->
                <div class="col-xl-6">
                    <div class=" custom-card h-100" :class="{ 'card': !isMobile, }">
                        <div
                            class="card-header d-flex align-items-center justify-content-between  bd-blue-200   p-2  rounded">
                            <div class="card-title"><i class="ri-settings-3-line me-2"></i>
                                Actividades realizadas
                            </div>

                        </div>
                        <div class="card-body pt-2">

                            <div v-for="(r, idx) in form.actividades_realizadas" :key="idx"
                                class="border rounded-3 p-3 mb-3 bg-white rounded-3 shadow-sm ">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-medium  text-muted">
                                        {{ idx + 1 }} . {{ r.actividad }}
                                    </span>
                                    <button type="button" class="btn btn-sm btn-icon btn-danger-light"
                                        @click="quitarActividad(idx)">
                                        <i class="ri-close-line"></i>
                                    </button>
                                </div>
                                <div class="row g-2 ps-4 p-2">
                                    <div class="col-sm-6 small d-flex align-items-center  gap-3 my-0">
                                        <label class="form-label form-label-sm fw-medium mb-0">
                                            <i class="ri-map-pin-line text-info"></i>

                                            Origen:
                                        </label>
                                        <p class="mb-0">
                                            {{ r.origen }}
                                        </p>
                                    </div>
                                    <div class="col-sm-6 small d-flex align-items-center  gap-3 my-0">
                                        <label class="form-label form-label-sm fw-medium mb-0">
                                            <i class="ri-map-pin-line text-info"></i>

                                            Destino:
                                        </label>
                                        <p class="mb-0">
                                            {{ r.destino }}
                                        </p>
                                    </div>

                                    <div class="col-sm-6 small d-flex align-items-center  gap-3 my-0">
                                        <label class="form-label form-label-sm fw-medium mb-0">
                                            <i class="ri-map-pin-line text-info"></i>

                                            Cantidad:
                                        </label>
                                        <p class="mb-0">
                                            {{ r.cantidad }} {{ r.unidad_medida }}
                                        </p>
                                    </div>

                                    <div class="col-sm-6 small d-flex align-items-center  gap-3 my-0">
                                        <label class="form-label form-label-sm fw-medium mb-0">
                                            <i class="ri-map-pin-line text-info"></i>

                                            Horario:
                                        </label>
                                        <p class="mb-0">
                                            {{ r.hora_inicio }} - {{ r.hora_fin }}
                                        </p>
                                    </div>

                                </div>
                            </div>

                            <div class="text-center px-5 py-2 border w-100 border-info mt-4 mb-4  rounded-pill btn-wave btn btn-info-light"
                                @click="estaAbiertoModal = true">
                                <i class="ri-add-line  "></i>
                                <small>Añadir actividad</small>
                            </div>

                        </div>
                    </div>
                </div>

            </div>

            <!-- Botones -->
            <div class="d-flex justify-content-md-end justify-content-between gap-2 mt-4">
                <Link :href="route('cargas.index')" class="btn btn-outline-secondary btn-wave">Cancelar</Link>
                <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="ri-save-line me-1"></i>
                    {{ form.processing ? 'Guardando...' : 'Registrar' }}
                </button>
            </div>
        </form>



        <Modal :show="estaAbiertoModal" maxWidth="md" @close="estaAbiertoModal = false">

            <form action="" @submit.prevent="agregarActividad">
                <div class="p-6">
                    <h2 class="text-lg text-center font-medium text-gray-600">Detalles de actividad</h2>

                    <p class="mt-2 text-sm text-gray-600 text-sm text-center">
                        Aquí puedes colocar todo el contenido que desees mostrar dentro del modal.
                    </p>

                    <div class="row g-3">



                        <div class="col-12">
                            <label class="form-label fw-medium">
                                Actividad <span class="text-danger">*</span>
                            </label>
                            <textarea v-model="formActividad.actividad" type="text" class="form-control" rows="4"
                                :class="{ 'is-invalid': formActividad.errors.actividad }"
                                placeholder="Descripción de la actividad"></textarea>

                            <div v-if="formActividad.errors.actividad" class="text-danger small mt-1">{{
                                formActividad.errors.actividad }}</div>

                        </div>

                        <div v-if="tipoMedicion == 'kilometraje'" class="col-12">
                            <label class="form-label fw-medium">
                                Origen <span class="text-danger">*</span>
                            </label>
                            <input v-model="formActividad.origen" type="text" class="form-control"
                                :class="{ 'is-invalid': formActividad.errors.origen }" placeholder="Lugar de origen" />

                            <div v-if="formActividad.errors.origen" class="text-danger small mt-1">{{
                                formActividad.errors.origen }}</div>

                        </div>

                        <div v-if="tipoMedicion == 'kilometraje'" class="col-12">
                            <label class="form-label fw-medium">
                                Destino <span class="text-danger">*</span>
                            </label>
                            <input v-model="formActividad.destino" type="text" class="form-control"
                                :class="{ 'is-invalid': formActividad.errors.destino }"
                                placeholder="Lugar de destino" />

                            <div v-if="formActividad.errors.destino" class="text-danger small mt-1">{{
                                formActividad.errors.destino }}</div>

                        </div>

                        <div v-if="tipoMedicion == 'horometro'" class="col-12">
                            <label class="form-label fw-medium">
                                Lugar <span class="text-danger">*</span>
                            </label>
                            <input v-model="formActividad.lugar" type="text" class="form-control"
                                :class="{ 'is-invalid': formActividad.errors.lugar }"
                                placeholder="Lugar de la actividad" />

                            <div v-if="formActividad.errors.lugar" class="text-danger small mt-1">{{
                                formActividad.errors.lugar }}</div>

                        </div>
                        <div class="col-4">
                            <label class="form-label fw-medium">
                                Cantidad <span class="text-danger">*</span>
                            </label>
                            <input v-model="formActividad.cantidad" type="text" class="form-control" v-entero="1"
                                :class="{ 'is-invalid': formActividad.errors.cantidad }" placeholder="Cantidad" />

                            <div v-if="formActividad.errors.cantidad" class="text-danger small mt-1">{{
                                formActividad.errors.cantidad }}</div>

                        </div>
                        <div class="col-8">
                            <label class="form-label fw-medium">
                                Unidad de medida <span class="text-danger">*</span>
                            </label>
                            <input v-model="formActividad.unidad_medida" type="text" class="form-control"
                                :class="{ 'is-invalid': formActividad.errors.unidad_medida }"
                                placeholder="Viajes, Cargas, etc. " />

                            <div v-if="formActividad.errors.unidad_medida" class="text-danger small mt-1">{{
                                formActividad.errors.unidad_medida }}</div>

                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">
                                Hora Inicio <span class="text-danger">*</span>
                            </label>
                            <input v-model="formActividad.hora_inicio" type="time" class="form-control" max="17:59"
                                :class="{ 'is-invalid': formActividad.errors.hora_inicio }" placeholder="HH:MM" />

                            <div v-if="formActividad.errors.hora_inicio" class="text-danger small mt-1">{{
                                formActividad.errors.hora_inicio }}</div>

                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">
                                Hora Fin <span class="text-danger">*</span>
                            </label>
                            <input v-model="formActividad.hora_fin" type="time" class="form-control" :max="horaMaxTurno"
                                :class="{ 'is-invalid': formActividad.errors.hora_fin }" placeholder="HH:MM" />
                            <div v-if="formActividad.errors.hora_fin" class="text-danger small mt-1">{{
                                formActividad.errors.hora_fin }}</div>

                        </div>


                    </div>

                    <!-- Botón para cerrar el modal desde adentro -->
                    <div class="mt-6 flex justify-between">
                        <button @click="estaAbiertoModal = false" class="btn btn-secondary" type="button">
                            Cancelar
                        </button>

                        <button class="btn btn-primary">
                            Agregar Actividad
                        </button>
                    </div>


                </div>
            </form>
        </Modal>
    </Maindashboard>
</template>

<style>
.is-invalid-multiselect .multiselect-wrapper {
    border-color: #dc3545 !important;
}

/*
.multiselect {
    --ms-font-size: 0.875rem;
    --ms-border-color: #dee2e6;
    --ms-border-color-active: #86b7fe;
    --ms-ring-color: rgba(13, 110, 253, .25);
    --ms-ring-width: 0.25rem;
    --ms-radius: 0.375rem;
    --ms-py: 0.375rem;
    --ms-px: 0.75rem;
    --ms-option-bg-selected: #0d6efd;
    --ms-option-bg-selected-pointed: #0a58ca;
    --ms-option-color-selected: #fff;
    --ms-option-color-selected-pointed: #fff;
} */
</style>
