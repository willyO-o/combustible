<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

import Multiselect from '@vueform/multiselect'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import Modal from '@/Components/Modal.vue'; // Ajusta la ruta según tu proyecto
import TextareaAutocomplete from '@/Components/TextareaAutocomplete.vue'


import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'

defineOptions({ layout: Maindashboard })


const estaAbiertoModal = ref(false) // Estado para controlar la visibilidad del modal;

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('md') // Devuelve true si la pantalla es menor a 768px


const props = defineProps({
    // [{ id, label, meta }] vehículos que el usuario puede elegir: ya viene
    // filtrado por rol desde el backend (conductor -> sus vehículos
    // asignados; jefe-area -> los de sus áreas a cargo; cualquier otro rol,
    // sin filtro -> todos). Ver OperacionDiariaController::vehiculosDisponibles().
    vehiculosAsignados: Array,
    operacion: Object, // la operación a editar (null en modo creación)
    actividadesSugeridas: Array, // [{ id, nombre_actividad, unidad_medida }] actividades sugeridas según el/las área(s) del usuario
    // [{ id, material }] catálogo de materiales trasladados; sólo se usa en el
    // formulario de actividad cuando el vehículo mide por kilometraje.
    materiales: { type: Array, default: () => [] },
    // [{ id, tipo_mantenimiento, tipo_valor: 'cantidad'|'booleano'|null, unidad_medida }]
    // controles de mantenimiento de ámbito operacion_diaria (activos).
    tiposMantenimiento: { type: Array, default: () => [] },
    // false para el rol conductor (siempre es él mismo, sin ambigüedad); true
    // para cualquier otro rol, que debe elegir entre los conductores
    // realmente asignados al vehículo (titular + provisionales).
    mostrarSelectorConductor: Boolean,
})

const vehiculosAsignadosOpt = ref(props.vehiculosAsignados || [])





/* ------------------------------------------------------------------ */
/*  Form                                                               */
/* ------------------------------------------------------------------ */
// const today = new Date().toISOString().slice(0, 16) // yyyy-MM-ddTHH:mm
const today = new Date(new Date().getTime() - (new Date().getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
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
    id_conductor: (props.operacion ? props.operacion.id_conductor : null) ?? null,
    turno: (props.operacion ? props.operacion.turno : turnoDefault()) ?? turnoDefault(),
    fecha_inicio: (props.operacion ? props.operacion.fecha_i_f : today) ?? today,
    fecha_fin: (props.operacion ? props.operacion.fecha_f_f : '') ?? '',
    kilometraje_inicio: (props.operacion ? props.operacion.kilometraje_inicio : '') ?? '',
    kilometraje_fin: (props.operacion ? props.operacion.kilometraje_fin : '') ?? '',
    horometro_inicio: (props.operacion ? props.operacion.horometro_inicio : '') ?? '',
    horometro_fin: (props.operacion ? props.operacion.horometro_fin : '') ?? '',
    horas_trabajadas: (props.operacion ? props.operacion.horas_trabajadas : '') ?? '',
    estado: 'FINALIZADO',
    observaciones: (props.operacion ? props.operacion.observaciones : '') ?? '',
    notificar_observaciones: false,
    actividades_realizadas: (props.operacion ? props.operacion.actividades_realizadas_edit : []) ?? [],
    // Una entrada por tipo de mantenimiento de operación diaria. En edición
    // se rellenan con lo ya guardado (props.operacion.mantenimientos_edit).
    // valor -> tipos "cantidad"; realizado (bool) -> tipos "booleano".
    mantenimientos: (props.tiposMantenimiento || []).map((t) => {
        const guardado = (props.operacion?.mantenimientos_edit ?? []).find(
            (m) => m.id_tipo_mantenimiento === t.id,
        )

        return {
            id_tipo_mantenimiento: t.id,
            tipo_mantenimiento: t.tipo_mantenimiento,
            tipo_valor: t.tipo_valor,
            unidad_medida: t.unidad_medida,
            valor: guardado?.valor ?? '',
            realizado: guardado?.realizado === 'SI',
        }
    }),
})


const showErrorActividad = (idx) => {
    //verificar si esta la palabra "actividades_realizadas" en form.errors y si tiene errores para el índice idx que viene como texto "actividades_realizadas.1.hora_fin"
    if(form.errors && Object.keys(form.errors).some(key => key.startsWith(`actividades_realizadas.${idx}`))) {
        // convertir en array de keys solo los errores que correspondan a la actividad en el índice idx
        const actividadErrors = Object.keys(form.errors).filter(key => key.startsWith(`actividades_realizadas.${idx}`))
        return actividadErrors.map(key => form.errors[key]).join(', ')
    }
    return null
}

/* ------------------------------------------------------------------ */
/*  Auto-relleno al seleccionar vehículo                               */
/* ------------------------------------------------------------------ */
const loadingVehiculo = ref(false)

const formActividad = useForm({
    actividad: '',
    id_actividad: null,
    id_material: '',
    lugar: '',
    origen: '',
    destino: '',
    cantidad: '',
    unidad_medida: '',
    hora_inicio: '',
    hora_fin: '',
    detalle: '',
})

// Nombre del material trasladado a partir de su id (para el resumen de
// actividades ya agregadas).
const materialNombre = (id) => props.materiales.find((m) => m.id === id)?.material ?? ''


const tipoMedicion = ref('') // 'kilometraje' o 'horometro'

// Horas trabajadas: sólo de referencia en el formulario, se calcula en
// vivo a partir de fecha_inicio/fecha_fin (el backend igual las recalcula
// siempre desde esas mismas fechas al guardar, ver OperacionDiaria::booted()).
// Redondeo hacia arriba a 2 decimales, ej. 4:30 -> 4.50.
const horasTrabajadasCalculadas = computed(() => {
    if (!form.fecha_inicio || !form.fecha_fin) return null

    const inicio = new Date(form.fecha_inicio)
    const fin = new Date(form.fecha_fin)

    if (isNaN(inicio) || isNaN(fin) || fin <= inicio) return null

    const horas = (fin - inicio) / (1000 * 60 * 60)

    return Math.ceil(horas * 100) / 100
})

watch(horasTrabajadasCalculadas, (val) => {
    form.horas_trabajadas = val ?? ''
}, { immediate: true })

// Conductores activos/provisionales del vehículo seleccionado (sólo viene
// poblado desde el backend cuando mostrarSelectorConductor es true).
const conductoresDelVehiculo = computed(() => {
    const vehiculoSelected = vehiculosAsignadosOpt.value.find(v => v.id === form.id_vehiculo)
    return vehiculoSelected?.meta?.conductoresAsignados ?? []
})

watch(() => form.id_vehiculo, async (val) => {

    tipoMedicion.value = ''

    if (!val) {
        form.id_conductor = null
        return
    }

    const vehiculoSelected = vehiculosAsignadosOpt.value.find(v => v.id === val)

    if (vehiculoSelected) {
        tipoMedicion.value = vehiculoSelected.meta.tipo_medicion
    }

    if (props.mostrarSelectorConductor) {
        const conductores = vehiculoSelected?.meta?.conductoresAsignados ?? []

        // Si el conductor ya elegido no pertenece al nuevo vehículo, se
        // limpia; si sólo queda una opción, se autoselecciona.
        if (!conductores.some(c => c.id === form.id_conductor)) {
            form.id_conductor = conductores.length === 1 ? conductores[0].id : null
        }
    }
})


function quitarActividad(idx) {
    form.actividades_realizadas.splice(idx, 1)
}

/* ------------------------------------------------------------------ */
/*  Envío                                                              */
/* ------------------------------------------------------------------ */
function submit() {

    const routeName = props.operacion ? route('operacion-diaria.update', props.operacion.id) : route('operacion-diaria.store');

    form
        .transform((data) => {
            const out = {
                ...data,
                id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
                // Sólo se mandan los controles con algo cargado; el resto no
                // aplica a esta operación y no debe registrarse.
                mantenimientos: data.mantenimientos
                    .map((m) => ({
                        id_tipo_mantenimiento: m.id_tipo_mantenimiento,
                        valor:
                            m.tipo_valor === 'cantidad' && m.valor !== '' && m.valor !== null
                                ? m.valor
                                : null,
                        realizado: m.tipo_valor === 'booleano' && m.realizado ? 'SI' : null,
                    }))
                    .filter((m) => m.valor !== null || m.realizado !== null),
                _method: props.operacion ? 'PUT' : 'POST', // Agregar el campo _method para PUT si es una actualización
            }
            return out
        })
        .post(routeName)
}

/* ------------------------------------------------------------------ */
/*  Mantenimiento (operación diaria)                                   */
/* ------------------------------------------------------------------ */
const mantenimientoRegistrado = (m) =>
    (m.tipo_valor === 'cantidad' && m.valor !== '' && m.valor !== null && m.valor !== undefined) ||
    (m.tipo_valor === 'booleano' && !!m.realizado)

const mantenimientosRegistrados = computed(
    () => form.mantenimientos.filter(mantenimientoRegistrado).length,
)

const agregarActividad = () => {


    formActividad.post(route('operacion-diaria.agregar-actividad'), {
        onSuccess: () => {

            // Agregar la actividad al arreglo de actividades_realizadas
            form.actividades_realizadas.push({ ...formActividad });

            // Limpiar el formulario de actividad
            formActividad.actividad = '';
            formActividad.id_actividad = null;
            formActividad.id_material = '';
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
    // Si sólo hay un vehículo disponible para elegir, se auto-selecciona.
    if (vehiculosAsignadosOpt.value.length === 1 && !form.id_vehiculo) {
        form.id_vehiculo = vehiculosAsignadosOpt.value[0].id
    }

    // Modo edición: preseleccionar el vehículo y conductor que ya tiene la
    // operación (form.id_conductor se asigna después del id_vehiculo para
    // que el watcher no lo limpie: ambos se aplican antes de que corra).
    if (props.operacion) {
        form.id_vehiculo = props.operacion.id_vehiculo
        form.id_conductor = props.operacion.id_conductor
    }
})
</script>

<template>

    <Head title="Nueva Carga de Combustible" />
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
                <h1 class="page-title text-center fw-medium fs-18 mb-0">{{ props.operacion ? 'Editar' : 'Registrar' }} operación diaria</h1>
            </div>
            <Link :href="route('cargas.index')" class="btn btn-outline-secondary btn-wave">
                <i class="ri-arrow-left-line me-1"></i> Volver
            </Link>
        </div>
        <div v-else class="  mb-2 mt-1">
            <h1 class="page-title text-center fw-medium fs-18 mb-0">{{ props.conductor ? 'Editar' : 'Registrar' }} operación diaria</h1>

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
                                    <Multiselect v-model="form.id_vehiculo"
                                        :options="vehiculosAsignadosOpt" value-prop="id" label="label"
                                        :searchable="true" :filter-results="true" placeholder="Buscar vehículo..."
                                        no-options-text="Sin vehículos disponibles" no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_vehiculo }" />

                                    <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">{{
                                        form.errors.id_vehiculo }}</div>
                                    <div v-if="loadingVehiculo" class="text-muted small mt-1">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Cargando datos del
                                        vehículo...
                                    </div>
                                </div>

                                <!-- Conductor: sólo se muestra a quien no tiene el rol conductor,
                                     ya que un vehículo puede tener varios conductores asignados
                                     (1 titular + provisionales por permiso/vacaciones). -->
                                <div v-if="mostrarSelectorConductor" class="col-12">
                                    <label class="form-label fw-medium">
                                        Conductor <span class="text-danger">*</span>
                                    </label>
                                    <Multiselect v-model="form.id_conductor"
                                        :options="conductoresDelVehiculo" value-prop="id" label="label"
                                        :searchable="true" :filter-results="true" :disabled="!form.id_vehiculo"
                                        placeholder="Selecciona un conductor..."
                                        no-options-text="Este vehículo no tiene conductores asignados"
                                        no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_conductor }" />

                                    <div v-if="form.errors.id_conductor" class="text-danger small mt-1">{{
                                        form.errors.id_conductor }}</div>
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
                                    <label class="form-label fw-medium mb-1">
                                        Horas trabajadas
                                    </label>
                                    <div>
                                        <span class="badge bg-primary-transparent fs-14 px-3 py-2">
                                            <i class="bi bi-clock me-1"></i>
                                            {{ horasTrabajadasCalculadas !== null ? horasTrabajadasCalculadas.toFixed(2) : '—' }} horas
                                        </span>
                                    </div>
                                    <div class="form-text">Se calcula automáticamente a partir de la fecha/hora de inicio y fin.</div>
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
                        <div class="card-body pt-2 text-center">

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

                                    <div v-if="r.id_material" class="col-sm-6 small d-flex align-items-center  gap-3 my-0">
                                        <label class="form-label form-label-sm fw-medium mb-0">
                                            <i class="ri-stack-line text-info"></i>

                                            Material:
                                        </label>
                                        <p class="mb-0">
                                            {{ materialNombre(r.id_material) }}
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
                                    <div v-if="showErrorActividad(idx)" class="text-danger">{{
                                        showErrorActividad(idx) }}</div>

                                </div>
                            </div>

                            <div class="text-center px-5 py-2 border  border-info mt-4 mb-4  rounded-pill btn-wave btn btn-info-light"
                                @click="estaAbiertoModal = true">
                                <i class="ri-add-line  "></i>
                                <small>Añadir actividad</small>
                            </div>
                            <div v-if="form.errors.actividades_realizadas" class="text-danger">{{
                                form.errors.actividades_realizadas }}</div>

                        </div>
                    </div>
                </div>

                <!-- ====== MANTENIMIENTO (OPERACIÓN DIARIA) ====== -->
                <div v-if="form.mantenimientos.length" class="col-12">
                    <div class="custom-card" :class="{ card: !isMobile }">
                        <div
                            class="card-header d-flex align-items-center justify-content-between bd-blue-200 p-2 rounded">
                            <div class="card-title"><i class="ri-tools-line me-2"></i>
                                Controles de mantenimiento
                            </div>
                            <span class="badge bg-primary-transparent text-primary mant-count"
                                :class="{ 'mant-count--active': mantenimientosRegistrados > 0 }">
                                {{ mantenimientosRegistrados }} / {{ form.mantenimientos.length }} registrados
                            </span>
                        </div>
                        <div class="card-body pt-3">
                            <p class="text-muted fs-12 mb-3">
                                Anota sólo los controles que correspondan a esta operación. Deja en blanco los que
                                no apliquen.
                            </p>
                            <div class="row g-0 mant-list">
                                <div v-for="(m, idx) in form.mantenimientos" :key="m.id_tipo_mantenimiento"
                                    class="col-12 mant-item">
                                    <div class="mant-row" :class="{ 'mant-row--filled': mantenimientoRegistrado(m) }">
                                        <label class="mant-row__name" :for="`mant-${m.id_tipo_mantenimiento}`">
                                            <i class="mant-row__tick"
                                                :class="mantenimientoRegistrado(m) ? 'ri-checkbox-circle-fill' : 'ri-checkbox-blank-circle-line'"></i>
                                            {{ m.tipo_mantenimiento }}
                                        </label>

                                        <div v-if="m.tipo_valor === 'cantidad'" class="mant-row__control">
                                            <div class="input-group input-group-sm mant-input">
                                                <input :id="`mant-${m.id_tipo_mantenimiento}`" v-model="m.valor"
                                                    type="text" v-decimal="2" inputmode="decimal" class="form-control"
                                                    placeholder="—"
                                                    :class="{ 'is-invalid': form.errors[`mantenimientos.${idx}.valor`] }" />
                                                <span v-if="m.unidad_medida" class="input-group-text">{{
                                                    m.unidad_medida }}</span>
                                            </div>
                                        </div>

                                        <div v-else-if="m.tipo_valor === 'booleano'" class="mant-row__control">
                                            <span class="mant-row__state">{{ m.realizado ? 'Sí' : 'No' }}</span>
                                            <div class="form-check form-switch mb-0">
                                                <input :id="`mant-${m.id_tipo_mantenimiento}`" v-model="m.realizado"
                                                    class="form-check-input" type="checkbox" role="switch"
                                                    :aria-label="`${m.tipo_mantenimiento}: realizado`" />
                                            </div>
                                        </div>

                                        <span v-else class="mant-row__control text-muted fs-12">Sin configurar</span>
                                    </div>
                                    <div v-if="form.errors[`mantenimientos.${idx}.valor`]"
                                        class="text-danger fs-12 mant-row__error">
                                        {{ form.errors[`mantenimientos.${idx}.valor`] }}
                                    </div>
                                </div>
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
                    {{ form.processing ? 'Guardando...' : 'Guardar' }}
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
                            <TextareaAutocomplete :suggestions="actividadesSugeridas"
                             option-value="nombre_actividad" option-label="nombre_actividad"
                             v-model="formActividad.actividad" placeholder="Escribe o selecciona una actividad"
                             @select="opt=>{ formActividad.unidad_medida = opt.unidad_medida }" :invalid="formActividad.errors.actividad !== undefined"
                             />



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

                        <div v-if="tipoMedicion == 'kilometraje'" class="col-12">
                            <label class="form-label fw-medium">
                                Material trasladado
                            </label>
                            <select v-model="formActividad.id_material" class="form-select"
                                :class="{ 'is-invalid': formActividad.errors.id_material }">
                                <option value="">Sin material (traslado sin carga)</option>
                                <option v-for="m in materiales" :key="m.id" :value="m.id">{{ m.material }}</option>
                            </select>
                            <div class="form-text">Para controlar los traslados y viajes de material.</div>
                            <div v-if="formActividad.errors.id_material" class="text-danger small mt-1">{{
                                formActividad.errors.id_material }}</div>
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
                            <input v-model="formActividad.hora_inicio" type="time" class="form-control"
                                :class="{ 'is-invalid': formActividad.errors.hora_inicio }" placeholder="HH:MM" />

                            <div v-if="formActividad.errors.hora_inicio" class="text-danger small mt-1">{{
                                formActividad.errors.hora_inicio }}</div>

                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">
                                Hora Fin <span class="text-danger">*</span>
                            </label>
                            <input v-model="formActividad.hora_fin" type="time" class="form-control"
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
