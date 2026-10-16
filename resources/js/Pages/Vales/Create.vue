<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import Maindashboard from '@/Layouts/Maindashboard.vue'
defineOptions({ layout: Maindashboard })
import Multiselect from '@vueform/multiselect'
import GrifoFormModal from '@/Components/GrifoFormModal.vue'
import DateTimeInput from '@/Components/DateTimeInput.vue'

import { computed, onMounted, ref, watch } from 'vue'
import { formatDate } from '@/Utils/dateUtil'

const props = defineProps({
    nextNroVale: String,
    tiposCombustible: Array,
    vale: Object,
    vehiculoActual: Object,
    conductorActual: Object,
    grifos: Array,
    // { fecha: 'YYYY-MM-DD', dias: int } según tiempo_expiracion parametrizado
    vencimientoSugerido: Object,
    // [{ id, label, meta }] todos los conductores activos (sólo al crear).
    operarios: { type: Array, default: () => [] },
    // [{ id, label, meta }] vehículos que el usuario puede usar (sólo al
    // crear): jefe de área, los de sus áreas; administrador, todos.
    vehiculos: { type: Array, default: () => [] },
})

// Copia local editable: al dar de alta un grifo nuevo desde el "+", se
// agrega aquí mismo y queda seleccionado de inmediato.
const grifosList = ref([...props.grifos])

const grifoModal = ref(null)

function onGrifoCreado(grifo) {
    grifosList.value.push(grifo)
    form.id_grifo = grifo.id
}


const today = new Date(new Date().getTime() - (new Date().getTimezoneOffset() * 60000)).toISOString().slice(0, 16);

const form = useForm({
    nro_vale: props.vale ? props.vale.nro : props.nextNroVale,
    fecha_emision: props.vale ? props.vale.fecha_emision : today,
    litros: props.vale ? props.vale.litros : '',
    precio: props.vale ? props.vale.precio : '',
    id_vehiculo: props.vale ? props.vale.id_vehiculo : null,
    id_conductor: props.vale ? props.vale.id_conductor : null,
    id_grifo: props.vale ? props.vale.id_grifo : null,
    estado_vale: 'PENDIENTE',
    id_tipo_combustible: props.vale ? props.vale.id_tipo_combustible : null,
    fecha_vencimiento: props.vale
        ? String(props.vale.fecha_vencimiento ?? '').slice(0, 10)
        : props.vencimientoSugerido.fecha,
})

/* ------------------------------------------------------------------ */
/*  Vencimiento: sólo día, vigente hasta las 23:59 de esa fecha.       */
/*  Debe ser posterior a hoy; la duración parametrizada es sugerencia. */
/* ------------------------------------------------------------------ */

const MS_POR_DIA = 86400000

// 'YYYY-MM-DD' -> Date en hora local (new Date('YYYY-MM-DD') parsearía en UTC)
const fechaLocal = (ymd) => {
    const m = String(ymd ?? '').match(/^(\d{4})-(\d{2})-(\d{2})$/)
    return m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3])) : null
}

const aYmd = (d) => [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
].join('-')

const hoy = new Date()
hoy.setHours(0, 0, 0, 0)
const manana = aYmd(new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate() + 1))

const fechaLarga = new Intl.DateTimeFormat('es-BO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })

const diasVigencia = computed(() => {
    const fecha = fechaLocal(form.fecha_vencimiento)
    return fecha ? Math.round((fecha - hoy) / MS_POR_DIA) : null
})

const vencimientoInvalido = computed(() => diasVigencia.value !== null && diasVigencia.value < 1)

const textoVigencia = computed(() => {
    const dias = diasVigencia.value
    if (dias === null || dias < 1) return null
    return dias === 1 ? 'Vigente hasta mañana' : `Vigente ${dias} días`
})

const textoFechaLarga = computed(() => {
    const fecha = fechaLocal(form.fecha_vencimiento)
    return fecha ? fechaLarga.format(fecha) : ''
})

const esSugerida = computed(() => form.fecha_vencimiento === props.vencimientoSugerido.fecha)

const diasSugeridos = computed(() => {
    const dias = props.vencimientoSugerido.dias
    return `${dias} día${dias === 1 ? '' : 's'}`
})

// Al editar un vale cuyo vencimiento ya pasó: hay que elegir una fecha nueva.
const vencimientoOriginalPasado = !!props.vale?.fecha_vencimiento
    && new Date(String(props.vale.fecha_vencimiento).replace(' ', 'T')) <= new Date()

function usarVencimientoSugerido() {
    form.fecha_vencimiento = props.vencimientoSugerido.fecha
}

watch(() => form.fecha_vencimiento, () => form.clearErrors('fecha_vencimiento'))

/* ------------------------------------------------------------------ */
/*  Conductor: todos los operarios activos (prop operarios), con los   */
/*  asignados al vehículo elegido primero y marcados.                  */
/* ------------------------------------------------------------------ */
// ids de los conductores asignados (titular + provisionales) al vehículo
// elegido, de meta.conductores_asignados de search.vehiculos.
const conductoresDelVehiculo = ref([])

// Copias: Multiselect reescribe `label` sobre la opción seleccionada, así que
// nunca se le pasan los objetos de la prop ni se altera el label (ver
// .ai/rules/js.md).
const operariosOpt = computed(() => {
    const asignados = new Set(conductoresDelVehiculo.value)

    return props.operarios
        .map(operario => ({ ...operario, asignado: asignados.has(operario.id) }))
        .sort((a, b) => Number(b.asignado) - Number(a.asignado))
})

const vehiculoSinConductor = computed(() =>
    !!form.id_vehiculo && conductoresDelVehiculo.value.length === 0
)

/* ------------------------------------------------------------------ */
/*  Vehículo: todos los del usuario ya cargados (prop vehiculos)       */
/* ------------------------------------------------------------------ */
// Copias (Multiselect reescribe `label` de la opción seleccionada).
const vehiculosOpt = computed(() => props.vehiculos.map(vehiculo => ({ ...vehiculo })))

// Multiselect emite el id (o null al limpiar): se busca el vehículo para
// tomar su meta (combustible, tipo de medición, conductores asignados).
function onVehiculoChange(idVehiculo) {
    form.clearErrors('id_vehiculo')
    vehiculoSeleccionado(props.vehiculos.find(v => v.id === idVehiculo) ?? null)
}

// Los selects pueden devolver el objeto completo {id, label}: se extrae el
// id antes de enviar.
function submit() {
    if (!form.fecha_vencimiento) {
        form.setError('fecha_vencimiento', 'Indique la fecha de vencimiento.')
        return
    }

    if (vencimientoInvalido.value) {
        form.setError('fecha_vencimiento', 'La fecha de vencimiento debe ser posterior a hoy.')
        return
    }

    const ruta = props.vale ? route('vales.update', props.vale.id) : route('vales.store')
    form
        .transform((data) => ({
            ...data,
            nro_vale: Number(data.nro_vale),
            id_vehiculo: data.id_vehiculo?.id ?? data.id_vehiculo,
            id_conductor: data.id_conductor?.id ?? data.id_conductor,
            id_grifo: data.id_grifo?.id ?? data.id_grifo,
            id_tipo_combustible: data.id_tipo_combustible?.id ?? data.id_tipo_combustible,
            _method: props.vale ? 'PUT' : 'POST',
        }))
        .post(ruta)
}


const vehiculoSeleccionado = (vehiculo) => {

    form.id_grifo = null
    form.id_tipo_combustible = vehiculo?.meta.id_tipo_combustible ?? null

    // Conductor: se sugiere el titular del vehículo (o su único asignado);
    // si no tiene ninguno se deja vacío para elegirlo a mano de la lista de
    // todos los operarios activos.
    conductoresDelVehiculo.value = vehiculo?.meta?.conductores_asignados ?? []
    const idsOperarios = new Set(props.operarios.map(o => o.id))
    const titular = vehiculo?.meta?.id_conductor
    const sugerido = idsOperarios.has(titular)
        ? titular
        : (conductoresDelVehiculo.value.length === 1 && idsOperarios.has(conductoresDelVehiculo.value[0])
            ? conductoresDelVehiculo.value[0]
            : null)
    form.id_conductor = sugerido
    form.clearErrors('id_conductor')


    if (vehiculo?.meta?.tipo_medicion == 'horometro') {

        //extraer la extacion de servicio que tenga el valor del campo es_pricipal = true y asignar su id al campo id_grifo
        const grifoPrincipal = grifosList.value.find(g => g.es_principal);
        form.id_grifo = grifoPrincipal ? grifoPrincipal.id : null;
    }


}

onMounted(() => {
    // Alta: si el usuario tiene un único vehículo, se preselecciona (con su
    // conductor, combustible y estación sugeridos).
    if (!props.vale && props.vehiculos.length === 1 && !form.id_vehiculo) {
        form.id_vehiculo = props.vehiculos[0].id
        vehiculoSeleccionado(props.vehiculos[0])
    }
})
</script>

<template>

    <Head :title="props.vale ? 'Editar Vale' : 'Nuevo Vale'" />

    <!-- Breadcrumb -->
    <div class="d-flex align-items-center justify-content-between page-header-breadcrumb flex-wrap gap-2 mb-4">
        <div>
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item">
                        <Link :href="route('dashboard')">Inicio</Link>
                    </li>
                    <li class="breadcrumb-item">
                        <Link :href="route('vales.index')">Vales</Link>
                    </li>
                    <li class="breadcrumb-item active">{{ props.vale ? 'Editar' : 'Nuevo' }}</li>
                </ol>
            </nav>
            <h1 class="page-title fw-medium fs-18 mb-0">
                {{ props.vale ? 'Editar' : 'Nuevo' }} Vale</h1>
        </div>
        <Link :href="route('vales.index')" class="btn btn-outline-secondary btn-wave">
            <i class="ri-arrow-left-line me-1"></i> Volver
        </Link>
    </div>

    <form @submit.prevent="submit">
        <div class="row g-4">

            <!-- Relaciones con búsqueda -->
            <div class="col-xl-6">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <div class="card-title">Asignación</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            <!-- Vehículo -->
                            <div class="col-12">
                                <label class="form-label fw-medium">
                                    Vehículo <span class="text-danger">*</span>
                                </label>
                                <Multiselect v-if="!props.vale" v-model="form.id_vehiculo"
                                    :options="vehiculosOpt" value-prop="id" label="label"
                                    :searchable="true" :filter-results="true"
                                    placeholder="Selecciona o busca por código, placa o marca..."
                                    no-options-text="No tienes vehículos activos asignados"
                                    no-results-text="Sin resultados"
                                    @change="onVehiculoChange"
                                    :class="{ 'is-invalid-multiselect': form.errors.id_vehiculo }" />
                                <!-- v-else debe ir JUSTO después del v-if: no insertar nada entre ambos. -->
                                <select v-else v-model="form.id_vehiculo" class="form-select" disabled
                                    :class="{ 'is-invalid': form.errors.id_vehiculo }">
                                    <option :value="props.vehiculoActual?.id">{{ props.vehiculoActual?.label }}</option>
                                </select>

                                <div v-if="form.errors.id_vehiculo" class="text-danger small mt-1">{{
                                    form.errors.id_vehiculo }}</div>
                            </div>

                            <!-- Conductor -->
                            <div class="col-12">
                                <label class="form-label fw-medium">
                                    Conductor <span class="text-danger">*</span>
                                </label>
                                <Multiselect v-if="!props.vale" v-model="form.id_conductor"
                                    :options="operariosOpt" value-prop="id" label="label"
                                    :searchable="true" :filter-results="true"
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
                                <!-- v-else debe ir JUSTO después del v-if del Multiselect: no
                                     insertar nada entre ambos (rompería el alta). -->
                                <select v-else v-model="form.id_conductor" class="form-select" disabled
                                    :class="{ 'is-invalid': form.errors.id_conductor }">
                                    <option :value="props.conductorActual?.id">{{ props.conductorActual?.label }}</option>
                                </select>

                                <div v-if="!props.vale && vehiculoSinConductor && !form.errors.id_conductor"
                                    class="form-text text-warning">
                                    <i class="ri-information-line"></i> Este vehículo no tiene conductor asignado:
                                    elige el conductor manualmente.
                                </div>

                                <div v-if="form.errors.id_conductor" class="text-danger small mt-1">{{
                                    form.errors.id_conductor }}</div>
                            </div>

                            <!-- Grifo -->
                            <div class="col-12">
                                <label class="form-label fw-medium">
                                    Estación de servicio <span class="text-danger">*</span>
                                </label>

                                <div class="d-flex gap-2">
                                    <select v-model="form.id_grifo" class="form-select"
                                        :class="{ 'is-invalid': form.errors.id_grifo }">
                                        <option :value="null" disabled>Seleccione una opcion...</option>
                                        <option v-for="grifo in grifosList" :key="grifo.id" :value="grifo.id">{{
                                            grifo.razon_social }} - {{ grifo.ciudad }}</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary flex-shrink-0"
                                        title="Registrar nueva estación de servicio" @click="grifoModal?.open()">
                                        <i class="ri-add-line"></i>
                                    </button>
                                </div>

                                <div v-if="form.errors.id_grifo" class="text-danger small mt-1">{{
                                    form.errors.id_grifo }}</div>
                            </div>



                        </div>
                    </div>
                </div>
            </div>
            <!-- Datos del vale -->
            <div class="col-xl-6">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <div class="card-title">Datos del Vale</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            <!-- Nro. Vale -->
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">
                                    Nro. Vale <span class="text-danger">*</span>
                                </label>
                                <input v-model="form.nro_vale" type="text" class="form-control" disabled
                                    :class="{ 'is-invalid': form.errors.nro_vale }" min="1" />
                                <div v-if="form.errors.nro_vale" class="invalid-feedback">{{ form.errors.nro_vale }}
                                </div>
                            </div>

                            <!-- Fecha Emisión -->
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">
                                    Fecha de Emisión <span class="text-danger">*</span>
                                </label>
                                <!-- Sólo lectura, en 24 h en cualquier computadora (DateTimeInput). -->
                                <DateTimeInput v-model="form.fecha_emision" disabled
                                    :invalid="!!form.errors.fecha_emision" />
                                <div v-if="form.errors.fecha_emision" class="text-danger small mt-1">{{
                                    form.errors.fecha_emision }}</div>
                            </div>

                            <!-- Fecha de Vencimiento -->
                            <div class="col-12">
                                <label for="fecha_vencimiento" class="form-label fw-medium">
                                    Vence el <span class="text-danger">*</span>
                                </label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text"><i class="ri-calendar-event-line"></i></span>
                                    <input id="fecha_vencimiento" v-model="form.fecha_vencimiento" type="date"
                                        class="form-control" :min="manana" required
                                        :class="{ 'is-invalid': form.errors.fecha_vencimiento || vencimientoInvalido }"
                                        aria-describedby="fecha_vencimiento_ayuda" />
                                    <span class="input-group-text vale-vencimiento-hora">hasta las 23:59</span>
                                    <div v-if="form.errors.fecha_vencimiento || vencimientoInvalido"
                                        class="invalid-feedback">
                                        {{ form.errors.fecha_vencimiento || 'La fecha de vencimiento debe ser posterior a hoy.' }}
                                    </div>
                                </div>

                                <div id="fecha_vencimiento_ayuda" class="vale-vencimiento-ayuda">
                                    <span v-if="textoVigencia" class="vale-vencimiento-resumen">
                                        <i class="ri-time-line" aria-hidden="true"></i>
                                        <strong>{{ textoVigencia }}</strong>
                                        <span>· {{ textoFechaLarga }}</span>
                                    </span>

                                    <span v-if="esSugerida" class="vale-vencimiento-sugerida">
                                        <i class="ri-checkbox-circle-line" aria-hidden="true"></i>
                                        Plazo sugerido por parámetros ({{ diasSugeridos }})
                                    </span>
                                    <button v-else type="button" class="btn btn-sm btn-primary-light btn-wave"
                                        @click="usarVencimientoSugerido">
                                        <i class="ri-lightbulb-flash-line me-1" aria-hidden="true"></i>
                                        Usar sugerida: {{ formatDate(props.vencimientoSugerido.fecha) }}
                                        ({{ diasSugeridos }})
                                    </button>
                                </div>

                                <div v-if="vencimientoOriginalPasado" class="alert alert-warning py-2 mt-2 mb-0 small"
                                    role="status">
                                    <i class="ri-error-warning-line me-1" aria-hidden="true"></i>
                                    Este vale venció el {{ formatDate(props.vale.fecha_vencimiento, true) }}. Elija una
                                    fecha posterior a hoy para que vuelva a estar vigente.
                                </div>
                            </div>

                            <!-- Litros -->
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">
                                    Litros <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input v-model="form.litros" type="text" v-decimal="2" class="form-control"
                                        :class="{ 'is-invalid': form.errors.litros }" placeholder="0.00" />
                                    <span class="input-group-text">Lt</span>
                                    <div v-if="form.errors.litros" class="invalid-feedback">{{ form.errors.litros }}
                                    </div>
                                </div>
                            </div>

                            <!-- Precio -->
                            <div class="col-sm-6">
                                <label class="form-label fw-medium">
                                    Precio (Bs) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">Bs</span>
                                    <input v-model="form.precio" type="text" v-decimal="2" class="form-control"
                                        :class="{ 'is-invalid': form.errors.precio }" placeholder="0.00" />
                                    <div v-if="form.errors.precio" class="invalid-feedback">{{ form.errors.precio }}
                                    </div>
                                </div>
                            </div>



                            <!-- Resumen precio/litros -->
                            <div v-if="form.litros && form.precio" class="col-12">
                                <div class="alert alert-info py-2 mb-0">
                                    <i class="ri-information-line me-1"></i>
                                    Total estimado:
                                    <strong>Bs {{ (Number(form.litros) * Number(form.precio)).toFixed(2) }}</strong>
                                    ({{ form.litros }} Lt × Bs {{ form.precio }})
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>



        </div>

        <!-- Botones -->
        <div class="d-flex justify-content-end gap-2 mt-4">
            <Link :href="route('vales.index')" class="btn btn-outline-secondary btn-wave">
                Cancelar
            </Link>
            <button type="submit" class="btn btn-primary btn-wave" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1" role="status"></span>
                <i v-else class="ri-save-line me-1"></i>
                {{ form.processing ? 'Guardando...' : 'Guardar Vale' }}
            </button>
        </div>
    </form>

    <!-- Modal reutilizable para dar de alta un grifo al vuelo -->
    <GrifoFormModal ref="grifoModal" @created="onGrifoCreado" />
</template>
