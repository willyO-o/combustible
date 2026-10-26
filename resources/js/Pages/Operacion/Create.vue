<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

import Multiselect from '@vueform/multiselect'
import Maindashboard from '@/Layouts/Maindashboard.vue'
import Modal from '@/Components/Modal.vue'; // Ajusta la ruta según tu proyecto
import TextareaAutocomplete from '@/Components/TextareaAutocomplete.vue'
import DateTimeInput from '@/Components/DateTimeInput.vue'


import { useBreakpoints, breakpointsTailwind } from '@vueuse/core'
import { storageUrl } from '@/Utils/storageUtil'

defineOptions({ layout: Maindashboard })


const estaAbiertoModal = ref(false) // Estado para controlar la visibilidad del modal;

const breakpoints = useBreakpoints(breakpointsTailwind)
const isMobile = breakpoints.smaller('md') // Devuelve true si la pantalla es menor a 768px


const props = defineProps({
    // [{ id, label, meta }] TODOS los vehículos activos, para cualquier rol.
    // meta.propio = asignado al usuario o de sus áreas (van primero);
    // meta.sin_area = no se puede registrar. Ver
    // OperacionDiariaController::vehiculosDisponibles().
    vehiculosAsignados: Array,
    operacion: Object, // la operación a editar (null en modo creación)
    actividadesSugeridas: Array, // [{ id, nombre_actividad, unidad_medida }] actividades sugeridas según el/las área(s) del usuario
    // [{ id, material }] catálogo de materiales trasladados; sólo se usa en el
    // formulario de actividad cuando el vehículo mide por kilometraje.
    materiales: { type: Array, default: () => [] },
    // [{ id, tipo_mantenimiento, tipo_valor: 'cantidad'|'booleano'|null, unidad_medida }]
    // controles de mantenimiento de ámbito operacion_diaria (activos).
    tiposMantenimiento: { type: Array, default: () => [] },
    // false para el conductor puro (siempre es él mismo, sin ambigüedad); true
    // para cualquier rol de gestión, que elige el operario entre todos los
    // conductores activos (prop operarios).
    mostrarSelectorConductor: Boolean,
    // Rol conductor sin ficha de conductor: aviso en vez de selectores vacíos.
    sinFichaConductor: { type: Boolean, default: false },
    // [{ id, label, meta }] todos los conductores activos (más el de la
    // operación en edición aunque ya no lo esté). Vacío para el conductor puro.
    operarios: { type: Array, default: () => [] },
    // true para jefe-area/administrador/super-admin: pueden registrar con
    // cualquier fecha (regularizar). El resto, sólo los últimos
    // diasAtrasSinRegularizar días (hoy o ayer). Lo valida el servidor
    // (OperacionStoreRequest); aquí sólo limita el calendario y avisa.
    puedeRegularizar: { type: Boolean, default: false },
    diasAtrasSinRegularizar: { type: Number, default: 1 },
})

const vehiculosAsignadosOpt = ref(props.vehiculosAsignados || [])

// meta.sin_area: el vehículo no tiene área vigente y el servidor rechazará
// el registro (la operación toma el área del vehículo).
const vehiculoSeleccionadoSinArea = computed(() =>
    !!vehiculosAsignadosOpt.value.find(v => v.id === form.id_vehiculo)?.meta?.sin_area
)

// En edición el vehículo queda bloqueado: cambiarlo invalidaría las lecturas
// (km/horómetro) y las actividades ya registradas, que dependen de su
// tipo_medicion.
const esEdicion = computed(() => !!props.operacion)





/* ------------------------------------------------------------------ */
/*  Form                                                               */
/* ------------------------------------------------------------------ */
// Horario de la jornada: en un alta, la fecha de inicio arranca hoy a la hora
// de ingreso y la fecha fin hoy a la hora de salida (ambas editables). También
// son las horas que propone el calendario si el campo se vacía.
const HORA_INGRESO = '08:00'
const HORA_SALIDA = '18:00'
// 'YYYY-MM-DDTHH:mm' en hora local (mismo formato que <input type="datetime-local">).
const hoy = new Date(new Date().getTime() - (new Date().getTimezoneOffset() * 60000)).toISOString().slice(0, 10)
const inicioDeJornada = `${hoy}T${HORA_INGRESO}`
const finDeJornada = `${hoy}T${HORA_SALIDA}`

// Límites del calendario de fecha de inicio: nunca después de hoy; sin rol
// de gestión, tampoco antes de N días atrás. Al editar, el día ya guardado
// sigue siendo elegible (si no, el calendario lo recortaría).
const finDeHoy = new Date(new Date().setHours(23, 59, 59, 999))
const minFechaInicio = computed(() => {
    if (props.puedeRegularizar) return null

    const minimo = new Date()
    minimo.setDate(minimo.getDate() - props.diasAtrasSinRegularizar)
    minimo.setHours(0, 0, 0, 0)

    const guardada = props.operacion?.fecha_i_f ? new Date(props.operacion.fecha_i_f.replace(' ', 'T')) : null
    if (guardada && !isNaN(guardada) && guardada < minimo) {
        guardada.setHours(0, 0, 0, 0)
        return guardada
    }

    return minimo
})
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
    fecha_inicio: (props.operacion ? props.operacion.fecha_i_f : inicioDeJornada) ?? inicioDeJornada,
    fecha_fin: (props.operacion ? props.operacion.fecha_f_f : finDeJornada) ?? '',
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
            // evidencia: File nuevo a subir (null = no se cambia). La ruta ya
            // guardada (para mostrar la miniatura) va aparte, en
            // evidencia_actual — nunca se reenvía al servidor tal cual, éste
            // la conserva solo si no llega un archivo nuevo.
            evidencia: null,
            evidencia_actual: guardado?.evidencia ?? null,
            eliminar_evidencia: false,
        }
    }),
})


const showErrorActividad = (idx) => {
    //verificar si esta la palabra "actividades_realizadas" en form.errors y si tiene errores para el índice idx que viene como texto "actividades_realizadas.1.cantidad"
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
    detalle: '',
})

// Nombre del material trasladado a partir de su id (para el resumen de
// actividades ya agregadas).
const materialNombre = (id) => props.materiales.find((m) => m.id === id)?.material ?? ''


const tipoMedicion = ref('') // 'kilometraje' o 'horometro'
// Tope de lectura por jornada del tipo del vehículo (km u horas; null = sin
// tope). Sólo para avisar en la franja de lecturas: el servidor lo valida.
const topeJornada = ref(null)

// Qué datos lleva cada actividad según cómo mide el vehículo: por
// kilometraje es un recorrido (origen -> destino, material opcional); por
// horómetro, el lugar donde trabajó el equipo. Mismo criterio que el backend
// (OperacionStoreRequest: lugar u origen/destino según tipo_medicion).
const MODOS_ACTIVIDAD = {
    kilometraje: {
        etiqueta: 'Kilometraje',
        icono: 'ri-road-map-line',
        descripcion: 'Registra el recorrido (origen y destino) y, si trasladó carga, el material.',
        ejemploUnidad: 'Ej: viajes, cargas',
    },
    horometro: {
        etiqueta: 'Horómetro',
        icono: 'ri-timer-line',
        descripcion: 'Registra el lugar donde trabajó el equipo.',
        ejemploUnidad: 'Ej: m³, pasadas, horas',
    },
}
const modoActual = computed(() => MODOS_ACTIVIDAD[tipoMedicion.value] ?? null)

// Una actividad se presenta como recorrido o como lugar según el vehículo;
// si el dato guardado no calza (actividades antiguas), se muestra lo que trae.
const esRecorrido = (r) =>
    tipoMedicion.value ? tipoMedicion.value === 'kilometraje' : !!(r.origen || r.destino)
const tieneValor = (v) => v !== null && v !== undefined && v !== ''
const cantidadActividad = (r) => [r.cantidad, r.unidad_medida].filter(tieneValor).join(' ')

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

// Lecturas: '' / null (sin cargar) -> null; el resto ya llega saneado por
// v-decimal ("1050.5") o desde el backend ("1050.50").
const numeroONulo = (valor) => {
    if (valor === '' || valor === null || valor === undefined) return null
    const numero = Number(valor)
    return Number.isFinite(numero) ? numero : null
}

// Recorrido (km) u horas de horómetro de la jornada, según el tipo_medicion
// del vehículo: lectura final - inicial. Sólo de referencia, no se envía (el
// backend valida que la final sea mayor que la inicial).
// estado: 'pendiente' (falta una lectura) | 'invalido' (final <= inicial) | 'ok'.
const lecturaCalculada = computed(() => {
    if (!tipoMedicion.value) return null

    const esKilometraje = tipoMedicion.value === 'kilometraje'
    const inicio = numeroONulo(esKilometraje ? form.kilometraje_inicio : form.horometro_inicio)
    const fin = numeroONulo(esKilometraje ? form.kilometraje_fin : form.horometro_fin)
    const base = {
        esKilometraje,
        etiqueta: esKilometraje ? 'Recorrido' : 'Horas de horómetro',
        unidad: esKilometraje ? 'km' : 'h',
        icono: esKilometraje ? 'bi-speedometer2' : 'bi-stopwatch',
        origen: esKilometraje ? 'Kilometraje final − inicial' : 'Horómetro final − inicial',
    }

    if (inicio === null || fin === null) return { ...base, estado: 'pendiente', valor: null }
    if (fin <= inicio) return { ...base, estado: 'invalido', valor: null }

    // Lecturas con 1 decimal: se redondea para no arrastrar errores de coma
    // flotante (1221.5 - 1210.2 = 11.299999...).
    const valor = Math.round((fin - inicio) * 10) / 10

    // Más que el tope del tipo de vehículo: casi seguro un error de tipeo
    // (ej. punto decimal olvidado). El servidor no guardará la operación.
    if (topeJornada.value !== null && valor > topeJornada.value) {
        return { ...base, estado: 'excede', valor }
    }

    return { ...base, estado: 'ok', valor }
})

const formatearTope = (valor) => Number(valor).toLocaleString('es-BO', { maximumFractionDigits: 1 })

// Horómetro vs. jornada: qué parte de las horas trabajadas estuvo el equipo
// en marcha. Si el horómetro supera la jornada (con 0.1 h de tolerancia) casi
// siempre es una lectura mal tipeada.
const usoDeJornada = computed(() => {
    const lectura = lecturaCalculada.value
    const horas = horasTrabajadasCalculadas.value

    if (!lectura || lectura.esKilometraje || lectura.estado !== 'ok' || !horas) return null

    return {
        porcentaje: Math.round((lectura.valor / horas) * 100),
        excede: lectura.valor > horas + 0.1,
    }
})

// Conductores activos/provisionales asignados al vehículo seleccionado (sólo
// viene poblado cuando mostrarSelectorConductor es true).
const conductoresDelVehiculo = computed(() => {
    const vehiculoSelected = vehiculosAsignadosOpt.value.find(v => v.id === form.id_vehiculo)
    return vehiculoSelected?.meta?.conductoresAsignados ?? []
})

// Opciones del combo "Operario": todos los operarios activos; los asignados
// al vehículo elegido se muestran primero, marcados con una insignia (slot
// #option) como sugerencia.
// OJO: Multiselect (refreshLabels) reescribe `label` sobre el objeto de la
// opción seleccionada cada vez que cambian las opciones. Por eso siempre se
// entregan copias (nunca los objetos de props.operarios) y el `label` no se
// modifica: si se le concatenara el texto "asignado", se acumulaba en cada
// recálculo ("· asignado al vehículo · asignado al vehículo ...").
const operariosOpt = computed(() => {
    const idsAsignados = new Set(conductoresDelVehiculo.value.map(c => c.id))

    return props.operarios
        .map(operario => ({ ...operario, asignado: idsAsignados.has(operario.id) }))
        .sort((a, b) => Number(b.asignado) - Number(a.asignado))
})

watch(() => form.id_vehiculo, async (val, oldVal) => {

    tipoMedicion.value = ''
    topeJornada.value = null

    // Al cambiar de vehículo (no en la selección inicial) se vacían las
    // actividades y los campos que dependen del tipo_medicion: origen/destino
    // vs lugar, material trasladado y las lecturas de km/horómetro dejan de
    // corresponder al nuevo vehículo.
    if (oldVal) {
        form.actividades_realizadas = []
        form.kilometraje_inicio = ''
        form.kilometraje_fin = ''
        form.horometro_inicio = ''
        form.horometro_fin = ''
        form.clearErrors()
    }

    if (!val) {
        return
    }

    const vehiculoSelected = vehiculosAsignadosOpt.value.find(v => v.id === val)

    if (vehiculoSelected) {
        tipoMedicion.value = vehiculoSelected.meta.tipo_medicion
        topeJornada.value = vehiculoSelected.meta.tope_jornada ?? null
    }

    // Cualquier operario activo es válido para cualquier vehículo, así que el
    // ya elegido se respeta. Si aún no hay uno y el vehículo tiene un único
    // asignado, se sugiere preseleccionándolo (editable).
    if (props.mostrarSelectorConductor && !form.id_conductor) {
        const conductores = vehiculoSelected?.meta?.conductoresAsignados ?? []

        if (conductores.length === 1) {
            form.id_conductor = conductores[0].id
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
                // Se manda SIEMPRE una entrada por cada tipo de mantenimiento
                // (aunque esté vacía) y en el mismo orden que form.mantenimientos:
                // el índice de cada fila en el arreglo enviado tiene que calzar
                // con el índice que usa el template para mostrar sus errores
                // (`mantenimientos.${idx}.evidencia`, etc.) — filtrar acá antes
                // de enviar reindexaba el arreglo y los errores del servidor
                // terminaban debajo de la fila equivocada. El propio backend ya
                // descarta las filas sin valor/realizado/evidencia (ver
                // SincronizarMantenimientosOperacionAction), así que no hace
                // falta filtrar de este lado. "evidencia" viaja como File sólo
                // si se eligió una foto nueva — sin archivo nuevo, el servidor
                // conserva sola la que ya tenía guardada para ese control.
                mantenimientos: data.mantenimientos.map((m) => ({
                    id_tipo_mantenimiento: m.id_tipo_mantenimiento,
                    valor:
                        m.tipo_valor === 'cantidad' && m.valor !== '' && m.valor !== null
                            ? m.valor
                            : null,
                    realizado: m.tipo_valor === 'booleano' && m.realizado ? 'SI' : null,
                    evidencia: m.evidencia ?? null,
                    eliminar_evidencia: !!m.eliminar_evidencia,
                })),
                _method: props.operacion ? 'PUT' : 'POST', // Agregar el campo _method para PUT si es una actualización
            }
            return out
        })
        .post(routeName, { forceFormData: true })
}

/* ------------------------------------------------------------------ */
/*  Mantenimiento (operación diaria)                                   */
/* ------------------------------------------------------------------ */
// Vista previa de la evidencia de cada control (clave = id_tipo_mantenimiento).
// Arranca con las ya guardadas (edición, como URL pública vía storageUrl()); al elegir un
// archivo nuevo se reemplaza por su data-URL (mismo patrón que la fotografía
// del vehículo en Vehiculos/Create.vue). Quitar la evidencia borra la clave.
const evidenciaPreview = ref(
    Object.fromEntries(
        form.mantenimientos
            .filter((m) => m.evidencia_actual)
            .map((m) => [m.id_tipo_mantenimiento, storageUrl(m.evidencia_actual)]),
    ),
)
// Referencias a los <input type=file> de cada fila (no necesita ser
// reactivo): sólo se usan para limpiar el input nativo al quitar una
// evidencia, así se puede volver a elegir el mismo archivo después.
const evidenciaInputs = {}

function onEvidenciaChange(m, e) {
    const file = e.target.files[0]
    if (!file) return
    m.evidencia = file
    m.eliminar_evidencia = false
    const reader = new FileReader()
    reader.onload = (ev) => { evidenciaPreview.value[m.id_tipo_mantenimiento] = ev.target.result }
    reader.readAsDataURL(file)
}

function quitarEvidencia(m, inputEl) {
    m.evidencia = null
    m.eliminar_evidencia = true
    delete evidenciaPreview.value[m.id_tipo_mantenimiento]
    if (inputEl) inputEl.value = ''
}

// La evidencia es obligatoria para todo control que se está registrando
// (cantidad con valor cargado, o booleano marcado "Sí"); marcar "No" no la
// exige — no hay nada que fotografiar de algo que no se hizo (mismo
// criterio que valida OperacionStoreRequest en el servidor).
const evidenciaRequerida = (m) =>
    (m.tipo_valor === 'cantidad' && m.valor !== '' && m.valor !== null && m.valor !== undefined) ||
    (m.tipo_valor === 'booleano' && m.realizado === true)

const mantenimientoRegistrado = (m) =>
    (m.tipo_valor === 'cantidad' && m.valor !== '' && m.valor !== null && m.valor !== undefined) ||
    (m.tipo_valor === 'booleano' && !!m.realizado) ||
    !!evidenciaPreview.value[m.id_tipo_mantenimiento]

const mantenimientosRegistrados = computed(
    () => form.mantenimientos.filter(mantenimientoRegistrado).length,
)

function abrirModalActividad() {
    formActividad.clearErrors()
    estaAbiertoModal.value = true
}

function cerrarModalActividad() {
    formActividad.clearErrors()
    estaAbiertoModal.value = false
}

const agregarActividad = () => {

    formActividad.post(route('operacion-diaria.agregar-actividad'), {
        preserveScroll: true,
        onSuccess: () => {

            // Sólo los datos de la actividad (no el objeto useForm entero,
            // que arrastraba errors/processing/métodos al formulario
            // principal). Se descarta el campo que no corresponde al tipo de
            // medición del vehículo.
            const datos = formActividad.data()
            if (tipoMedicion.value === 'kilometraje') {
                datos.lugar = ''
            } else if (tipoMedicion.value === 'horometro') {
                datos.origen = ''
                datos.destino = ''
                datos.id_material = ''
            }
            form.actividades_realizadas.push(datos);

            // Limpiar el formulario de actividad
            formActividad.actividad = '';
            formActividad.id_actividad = null;
            formActividad.id_material = '';
            formActividad.lugar = '';
            formActividad.origen = '';
            formActividad.destino = '';
            formActividad.cantidad = '';
            formActividad.unidad_medida = '';
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
    // Alta: se auto-selecciona si sólo hay un vehículo, o si de todos los
    // vehículos el usuario tiene asignado uno solo (p.ej. un conductor con su
    // vehículo): igual que antes de liberar la lista completa.
    if (!props.operacion && !form.id_vehiculo) {
        const propios = vehiculosAsignadosOpt.value.filter(v => v.meta?.propio)
        const unico = vehiculosAsignadosOpt.value.length === 1
            ? vehiculosAsignadosOpt.value[0]
            : (propios.length === 1 ? propios[0] : null)

        if (unico) {
            form.id_vehiculo = unico.id
        }
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

    <div v-if="props.sinFichaConductor" class="alert alert-warning d-flex align-items-start gap-2 mb-4" role="alert">
        <i class="ri-error-warning-line fs-18" aria-hidden="true"></i>
        <div>
            <strong>Tu cuenta no tiene ficha de conductor.</strong>
            Tienes el rol Conductor pero no estás registrado como operario, así que no hay vehículos a tu nombre.
            Pide a un administrador que registre tu ficha de conductor y te asigne un vehículo.
        </div>
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
                                        :searchable="true" :filter-results="true" :disabled="esEdicion"
                                        :can-clear="!esEdicion" placeholder="Buscar vehículo..."
                                        no-options-text="Sin vehículos disponibles" no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_vehiculo }">
                                        <template #option="{ option }">
                                            <span>{{ option.label }}</span>
                                            <span v-if="option.meta?.propio"
                                                class="badge bg-primary-transparent text-primary fw-normal ms-2">Asignado</span>
                                            <span v-if="option.meta?.sin_area"
                                                class="badge bg-warning-transparent text-warning fw-normal ms-2">Sin área</span>
                                        </template>
                                    </Multiselect>

                                    <div v-if="!esEdicion && vehiculoSeleccionadoSinArea && !form.errors.id_vehiculo"
                                        class="text-warning small mt-1">
                                        <i class="ri-error-warning-line me-1"></i>Este vehículo no tiene un área asignada:
                                        no se podrá registrar hasta asignarlo a un área.
                                    </div>

                                    <div v-if="esEdicion" class="text-muted small mt-1">
                                        <i class="ri-lock-line me-1"></i>El vehículo no se puede cambiar al editar la
                                        operación.
                                    </div>
                                    <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">{{
                                        form.errors.id_vehiculo }}</div>
                                    <div v-if="loadingVehiculo" class="text-muted small mt-1">
                                        <span class="spinner-border spinner-border-sm me-1"></span> Cargando datos del
                                        vehículo...
                                    </div>
                                </div>

                                <!-- Operario: se oculta al conductor puro (siempre es él mismo);
                                     los roles de gestión eligen entre todos los operarios activos. -->
                                <div v-if="mostrarSelectorConductor" class="col-12">
                                    <label class="form-label fw-medium">
                                        Operario <span class="text-danger">*</span>
                                    </label>
                                    <Multiselect v-model="form.id_conductor"
                                        :options="operariosOpt" value-prop="id" label="label"
                                        :searchable="true" :filter-results="true"
                                        placeholder="Selecciona un operario..."
                                        no-options-text="No hay operarios activos registrados"
                                        no-results-text="Sin resultados"
                                        :class="{ 'is-invalid-multiselect': form.errors.id_conductor }">
                                        <template #option="{ option }">
                                            <span>{{ option.label }}</span>
                                            <span v-if="option.asignado"
                                                class="badge bg-primary-transparent text-primary fw-normal ms-2">Asignado al vehículo</span>
                                        </template>
                                    </Multiselect>

                                    <div v-if="form.errors.id_conductor" class="text-danger small mt-1">{{
                                        form.errors.id_conductor }}</div>
                                </div>

                                <!-- Fecha/hora: siempre 24 h, sin depender de la configuración
                                     regional de la computadora (ver DateTimeInput.vue). -->
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Fecha y hora inicio <span class="text-danger">*</span>
                                    </label>
                                    <DateTimeInput v-model="form.fecha_inicio" :invalid="!!form.errors.fecha_inicio"
                                        :hora-sugerida="HORA_INGRESO" :min-date="minFechaInicio" :max-date="puedeRegularizar ? null : finDeHoy" />
                                    <div v-if="form.errors.fecha_inicio" class="text-danger small mt-1">{{
                                        form.errors.fecha_inicio }}</div>
                                    <div v-else class="form-text">
                                        <template v-if="puedeRegularizar">Puedes registrar cualquier fecha para regularizar.</template>
                                        <template v-else>Sólo hoy o ayer.</template>
                                        Una operación por vehículo y por día.
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-medium">
                                        Fecha y hora fin <span class="text-danger">*</span>
                                    </label>
                                    <DateTimeInput v-model="form.fecha_fin" :invalid="!!form.errors.fecha_fin"
                                        :hora-sugerida="HORA_SALIDA" />
                                    <div v-if="form.errors.fecha_fin" class="text-danger small mt-1">{{
                                        form.errors.fecha_fin }}</div>
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
                                    <label class="form-label fw-medium">Horómetro inicial</label>
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
                                    <label class="form-label fw-medium">Horómetro final</label>
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

                                <!-- Lecturas calculadas en vivo (sólo referencia): horas de la
                                     jornada y, según el vehículo, recorrido u horas de horómetro. -->
                                <div class="col-12">
                                    <div class="op-calc" aria-live="polite">
                                        <div class="op-calc__item"
                                            :class="{ 'op-calc__item--ok': horasTrabajadasCalculadas !== null }">
                                            <span class="op-calc__label">
                                                <i class="bi bi-clock" aria-hidden="true"></i>
                                                Horas trabajadas
                                            </span>
                                            <span class="op-calc__value">
                                                <template v-if="horasTrabajadasCalculadas !== null">
                                                    {{ horasTrabajadasCalculadas.toFixed(2) }}<span class="op-calc__unit">h</span>
                                                </template>
                                                <template v-else>—</template>
                                            </span>
                                            <span class="op-calc__hint">Fecha y hora fin − inicio</span>
                                        </div>

                                        <div v-if="lecturaCalculada" class="op-calc__item" :class="{
                                            'op-calc__item--ok': lecturaCalculada.estado === 'ok' && !usoDeJornada?.excede,
                                            'op-calc__item--warning': usoDeJornada?.excede,
                                            'op-calc__item--danger': ['invalido', 'excede'].includes(lecturaCalculada.estado),
                                        }">
                                            <span class="op-calc__label">
                                                <i class="bi" :class="lecturaCalculada.icono" aria-hidden="true"></i>
                                                {{ lecturaCalculada.etiqueta }}
                                            </span>
                                            <span class="op-calc__value">
                                                <template v-if="['ok', 'excede'].includes(lecturaCalculada.estado)">
                                                    {{ lecturaCalculada.valor.toFixed(1) }}<span class="op-calc__unit">{{ lecturaCalculada.unidad }}</span>
                                                </template>
                                                <template v-else>—</template>
                                            </span>
                                            <span v-if="lecturaCalculada.estado === 'invalido'" class="op-calc__hint">
                                                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                                                La lectura final debe ser mayor a la inicial
                                            </span>
                                            <span v-else-if="lecturaCalculada.estado === 'excede'" class="op-calc__hint" role="alert">
                                                <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
                                                Supera el máximo de {{ formatearTope(topeJornada) }} {{ lecturaCalculada.unidad }} por jornada
                                                para este tipo de vehículo. Revisa la lectura final: ¿falta un punto decimal?
                                            </span>
                                            <span v-else-if="usoDeJornada?.excede" class="op-calc__hint">
                                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                                Supera las {{ horasTrabajadasCalculadas.toFixed(2) }} h de la jornada; revisa las lecturas
                                            </span>
                                            <span v-else-if="lecturaCalculada.estado === 'pendiente'" class="op-calc__hint">
                                                Completa la lectura inicial y final
                                            </span>
                                            <span v-else class="op-calc__hint">
                                                {{ lecturaCalculada.origen }}<template v-if="usoDeJornada"> · {{ usoDeJornada.porcentaje }} % de la jornada</template><template v-if="topeJornada !== null"> · máx. {{ formatearTope(topeJornada) }} {{ lecturaCalculada.unidad }}</template>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="form-text">Se calculan automáticamente con las fechas y las lecturas cargadas.</div>
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
                            <span v-if="modoActual" class="badge bg-info-transparent text-info op-act-modo"
                                :title="modoActual.descripcion">
                                <i :class="modoActual.icono" class="me-1" aria-hidden="true"></i>{{ modoActual.etiqueta }}
                            </span>
                        </div>
                        <div class="card-body pt-2 text-center">

                            <ol v-if="form.actividades_realizadas.length" class="op-act-lista text-start">
                                <li v-for="(r, idx) in form.actividades_realizadas" :key="idx" class="op-act"
                                    :class="{ 'op-act--error': showErrorActividad(idx) }">
                                    <div class="op-act__cabecera">
                                        <span class="op-act__num" aria-hidden="true">{{ idx + 1 }}</span>
                                        <span class="op-act__nombre">{{ r.actividad }}</span>
                                        <button type="button" class="btn btn-sm btn-icon btn-danger-light flex-shrink-0"
                                            :title="`Quitar la actividad ${idx + 1}`" :aria-label="`Quitar la actividad ${idx + 1}`"
                                            @click="quitarActividad(idx)">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>

                                    <dl class="op-act__datos">
                                        <!-- Kilometraje: un recorrido de origen a destino -->
                                        <div v-if="esRecorrido(r)" class="op-act__dato op-act__dato--ancho">
                                            <dt>Recorrido</dt>
                                            <dd class="op-act__ruta">
                                                <span><i class="ri-map-pin-line" aria-hidden="true"></i>{{ r.origen || '—' }}</span>
                                                <i class="ri-arrow-right-line op-act__flecha" aria-label="hacia"></i>
                                                <span><i class="ri-flag-line" aria-hidden="true"></i>{{ r.destino || '—' }}</span>
                                            </dd>
                                        </div>
                                        <div v-if="esRecorrido(r)" class="op-act__dato">
                                            <dt>Material</dt>
                                            <dd :class="{ 'op-act__vacio': !r.id_material }">
                                                <i class="ri-stack-line" aria-hidden="true"></i>{{ r.id_material ? materialNombre(r.id_material) : 'Sin carga' }}
                                            </dd>
                                        </div>

                                        <!-- Horómetro: el lugar donde trabajó el equipo (y actividades
                                             antiguas que lo traen aunque el vehículo mida por km) -->
                                        <div v-if="!esRecorrido(r) || r.lugar" class="op-act__dato" :class="{ 'op-act__dato--ancho': !esRecorrido(r) }">
                                            <dt>Lugar</dt>
                                            <dd :class="{ 'op-act__vacio': !r.lugar }">
                                                <i class="ri-map-pin-2-line" aria-hidden="true"></i>{{ r.lugar || '—' }}
                                            </dd>
                                        </div>

                                        <div class="op-act__dato">
                                            <dt>Cantidad</dt>
                                            <dd :class="{ 'op-act__vacio': !cantidadActividad(r) }">{{ cantidadActividad(r) || '—' }}</dd>
                                        </div>

                                        <!-- Horas ya no se capturan: sólo actividades antiguas las traen al editar -->
                                        <div v-if="r.hora_inicio || r.hora_fin" class="op-act__dato">
                                            <dt>Horario</dt>
                                            <dd>{{ r.hora_inicio }} - {{ r.hora_fin }}</dd>
                                        </div>
                                    </dl>

                                    <div v-if="showErrorActividad(idx)" class="op-act__error">
                                        <i class="ri-error-warning-line me-1" aria-hidden="true"></i>{{ showErrorActividad(idx) }}
                                    </div>
                                </li>
                            </ol>
                            <p v-else-if="form.id_vehiculo" class="text-muted small mb-0 mt-2">
                                Aún no hay actividades. Agrega al menos una de la jornada.
                            </p>

                            <button type="button"
                                class="text-center px-5 py-2 border border-info mt-4 mb-2 rounded-pill btn-wave btn btn-info-light"
                                :disabled="!form.id_vehiculo" @click="abrirModalActividad">
                                <i class="ri-add-line"></i>
                                <small>Añadir actividad</small>
                            </button>
                            <div v-if="!form.id_vehiculo" class="text-muted small mb-2">
                                Selecciona primero un vehículo para registrar actividades.
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

                                        <div class="mant-row__evidencia">
                                            <input
                                                :ref="(el) => { if (el) evidenciaInputs[m.id_tipo_mantenimiento] = el }"
                                                :id="`mant-foto-${m.id_tipo_mantenimiento}`" type="file"
                                                accept="image/*" capture="environment" class="d-none"
                                                @change="onEvidenciaChange(m, $event)" />
                                            <label :for="`mant-foto-${m.id_tipo_mantenimiento}`"
                                                class="mant-evidencia-btn"
                                                :class="{ 'mant-evidencia-btn--requerida': evidenciaRequerida(m) && !evidenciaPreview[m.id_tipo_mantenimiento] }"
                                                :title="evidenciaRequerida(m) ? 'Evidencia fotográfica obligatoria' : 'Adjuntar evidencia fotográfica'">
                                                <img v-if="evidenciaPreview[m.id_tipo_mantenimiento]"
                                                    :src="evidenciaPreview[m.id_tipo_mantenimiento]"
                                                    class="mant-evidencia-thumb" alt="Evidencia" />
                                                <i v-else class="ri-camera-line"></i>
                                            </label>
                                            <button v-if="evidenciaPreview[m.id_tipo_mantenimiento]" type="button"
                                                class="mant-evidencia-quitar" title="Quitar evidencia"
                                                @click="quitarEvidencia(m, evidenciaInputs[m.id_tipo_mantenimiento])">
                                                <i class="ri-close-line"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div v-if="form.errors[`mantenimientos.${idx}.valor`]"
                                        class="text-danger fs-12 mant-row__error">
                                        {{ form.errors[`mantenimientos.${idx}.valor`] }}
                                    </div>
                                    <div v-if="form.errors[`mantenimientos.${idx}.evidencia`]"
                                        class="text-danger fs-12 mant-row__error">
                                        {{ form.errors[`mantenimientos.${idx}.evidencia`] }}
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



        <Modal :show="estaAbiertoModal" maxWidth="md" @close="cerrarModalActividad">

            <form action="" @submit.prevent="agregarActividad">
                <div class="p-6">
                    <h2 class="text-lg text-center font-medium text-gray-600">Nueva actividad</h2>

                    <p v-if="modoActual" class="op-act-modal__modo">
                        <span class="badge bg-info-transparent text-info">
                            <i :class="modoActual.icono" class="me-1" aria-hidden="true"></i>{{ modoActual.etiqueta }}
                        </span>
                        <span>{{ modoActual.descripcion }}</span>
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

                        <div v-if="tipoMedicion == 'kilometraje'" class="col-sm-6">
                            <label for="actividad-origen" class="form-label fw-medium">
                                <i class="ri-map-pin-line text-info me-1" aria-hidden="true"></i>Origen <span class="text-danger">*</span>
                            </label>
                            <input id="actividad-origen" v-model="formActividad.origen" type="text" class="form-control"
                                :class="{ 'is-invalid': formActividad.errors.origen }" placeholder="Ej: Cantera" maxlength="255" />

                            <div v-if="formActividad.errors.origen" class="text-danger small mt-1">{{
                                formActividad.errors.origen }}</div>

                        </div>

                        <div v-if="tipoMedicion == 'kilometraje'" class="col-sm-6">
                            <label for="actividad-destino" class="form-label fw-medium">
                                <i class="ri-flag-line text-info me-1" aria-hidden="true"></i>Destino <span class="text-danger">*</span>
                            </label>
                            <input id="actividad-destino" v-model="formActividad.destino" type="text" class="form-control"
                                :class="{ 'is-invalid': formActividad.errors.destino }"
                                placeholder="Ej: Planta" maxlength="255" />

                            <div v-if="formActividad.errors.destino" class="text-danger small mt-1">{{
                                formActividad.errors.destino }}</div>

                        </div>

                        <div v-if="tipoMedicion == 'kilometraje'" class="col-12">
                            <label for="actividad-material" class="form-label fw-medium">
                                <i class="ri-stack-line text-info me-1" aria-hidden="true"></i>Material trasladado
                            </label>
                            <select id="actividad-material" v-model="formActividad.id_material" class="form-select"
                                :class="{ 'is-invalid': formActividad.errors.id_material }">
                                <option value="">Sin material (traslado sin carga)</option>
                                <option v-for="m in materiales" :key="m.id" :value="m.id">{{ m.material }}</option>
                            </select>
                            <div class="form-text">Para controlar los traslados y viajes de material.</div>
                            <div v-if="formActividad.errors.id_material" class="text-danger small mt-1">{{
                                formActividad.errors.id_material }}</div>
                        </div>

                        <div v-if="tipoMedicion == 'horometro'" class="col-12">
                            <label for="actividad-lugar" class="form-label fw-medium">
                                <i class="ri-map-pin-2-line text-info me-1" aria-hidden="true"></i>Lugar de trabajo <span class="text-danger">*</span>
                            </label>
                            <input id="actividad-lugar" v-model="formActividad.lugar" type="text" class="form-control"
                                :class="{ 'is-invalid': formActividad.errors.lugar }"
                                placeholder="Ej: Frente de explotación norte" maxlength="255" />

                            <div v-if="formActividad.errors.lugar" class="text-danger small mt-1">{{
                                formActividad.errors.lugar }}</div>

                        </div>
                        <div class="col-4">
                            <label class="form-label fw-medium">
                                Cantidad <span class="text-muted fw-normal small">(opcional)</span>
                            </label>
                            <input v-model="formActividad.cantidad" type="text" class="form-control" v-entero="1"
                                :class="{ 'is-invalid': formActividad.errors.cantidad }" placeholder="Cantidad" />

                            <div v-if="formActividad.errors.cantidad" class="text-danger small mt-1">{{
                                formActividad.errors.cantidad }}</div>

                        </div>
                        <div class="col-8">
                            <label class="form-label fw-medium">
                                Unidad de medida <span class="text-muted fw-normal small">(opcional)</span>
                            </label>
                            <input v-model="formActividad.unidad_medida" type="text" class="form-control"
                                :class="{ 'is-invalid': formActividad.errors.unidad_medida }"
                                :placeholder="modoActual?.ejemploUnidad ?? 'Ej: viajes, cargas'" maxlength="30" />

                            <div v-if="formActividad.errors.unidad_medida" class="text-danger small mt-1">{{
                                formActividad.errors.unidad_medida }}</div>

                        </div>

                    </div>

                    <!-- Botón para cerrar el modal desde adentro -->
                    <div class="mt-6 flex justify-between">
                        <button @click="cerrarModalActividad" class="btn btn-secondary" type="button">
                            Cancelar
                        </button>

                        <button class="btn btn-primary" :disabled="formActividad.processing">
                            <span v-if="formActividad.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Agregar actividad
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
