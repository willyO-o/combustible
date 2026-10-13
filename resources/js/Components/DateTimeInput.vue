<script setup>
/**
 * Campo de fecha y hora con formato fijo de 24 horas (dd/mm/aaaa HH:mm),
 * igual en todas las computadoras. Reemplaza a <input type="datetime-local">,
 * que el navegador dibuja según la configuración regional del equipo (12 h
 * "a. m./p. m." en unos, 24 h en otros; mes/día en inglés).
 *
 * El v-model es el MISMO string que entregaba el input nativo:
 * 'YYYY-MM-DDTHH:mm' (acepta también 'YYYY-MM-DD HH:mm', el formato con el
 * que el backend devuelve las fechas al editar). Así ni el cálculo de horas
 * ni la validación del servidor cambian.
 *
 * Uso:
 *   <DateTimeInput v-model="form.fecha_inicio" :invalid="!!form.errors.fecha_inicio"
 *       hora-sugerida="08:30" />
 *
 * Envuelve daterange-picker-vue3 en modo fecha única con selector de hora
 * (mismo paquete que DateRangeFilter.vue). Trampas del paquete que este
 * componente neutraliza:
 *  - Escribe directo sobre el objeto que recibe por v-model apenas se toca un
 *    día (Reflect.set), antes de "Aplicar", así que su "Cancelar" no revierte.
 *    Por eso el valor real vive sólo en props.modelValue y, al cerrarse el
 *    calendario por cualquier vía, el objeto interno se reconstruye desde ahí.
 *  - Los minutos van de 5 en 5 por defecto: se fuerza 1, si no un 08:37 ya
 *    guardado no tendría opción en la lista.
 *  - El selector de hora sólo aparece con una fecha elegida: con el campo
 *    vacío, al abrir se propone hoy a la `horaSugerida` (o la hora actual),
 *    que sólo se guarda si el usuario pulsa "Aplicar".
 */
import { markRaw, nextTick, ref, watch } from 'vue'
import DateRangePickerImport from 'daterange-picker-vue3'

// Mismo desenvolvimiento que DateRangeFilter.vue: el paquete sólo publica un
// build UMD y, según el bundler, el import trae el componente o { default }.
// NO usar "import * as" (namespace congelado -> rompe sólo en producción).
function desenvolverComponente(mod) {
    let actual = mod
    while (actual && actual.default && actual.default !== actual && !actual.render && !actual.setup) {
        actual = actual.default
    }
    return actual
}

const DateRangePicker = markRaw(desenvolverComponente(DateRangePickerImport))

const props = defineProps({
    modelValue: { type: String, default: '' },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    placeholder: { type: String, default: 'dd/mm/aaaa --:--' },
    // 'HH:mm' propuesta al abrir el calendario con el campo vacío; vacío =
    // hora actual.
    horaSugerida: { type: String, default: '' },
    opens: { type: String, default: 'right' }, // 'left' | 'right' | 'center'
})

const emit = defineEmits(['update:modelValue'])

const PATRON = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/

/** String del v-model -> Date en hora LOCAL (sin el corrimiento UTC de new Date(str)). */
function aFecha(valor) {
    const partes = PATRON.exec(valor ?? '')
    if (!partes) return null
    const [, y, m, d, h, min] = partes.map(Number)
    const fecha = new Date(y, m - 1, d, h, min)
    return isNaN(fecha) ? null : fecha
}

const dosDigitos = (n) => String(n).padStart(2, '0')

/** Date -> 'YYYY-MM-DDTHH:mm' (mismo formato que <input type="datetime-local">). */
function aValor(fecha) {
    return `${fecha.getFullYear()}-${dosDigitos(fecha.getMonth() + 1)}-${dosDigitos(fecha.getDate())}`
        + `T${dosDigitos(fecha.getHours())}:${dosDigitos(fecha.getMinutes())}`
}

/** Texto visible, siempre 24 h: 'dd/mm/aaaa HH:mm'. */
function aTexto(valor) {
    const partes = PATRON.exec(valor ?? '')
    if (!partes) return ''
    const [, y, m, d, h, min] = partes
    return `${d}/${m}/${y} ${h}:${min}`
}

// Dos Date distintas: el paquete escribe sobre startDate/endDate por separado.
function modeloDesde(fecha) {
    return { startDate: fecha ? new Date(fecha) : null, endDate: fecha ? new Date(fecha) : null }
}

function propuestaInicial() {
    const fecha = new Date()
    const sugerida = /^(\d{1,2}):(\d{2})$/.exec(props.horaSugerida)
    if (sugerida) {
        fecha.setHours(Number(sugerida[1]), Number(sugerida[2]), 0, 0)
    } else {
        fecha.setSeconds(0, 0)
    }
    return fecha
}

const modeloPicker = ref(modeloDesde(aFecha(props.modelValue)))

function resincronizar() {
    modeloPicker.value = modeloDesde(aFecha(props.modelValue))
}

watch(() => props.modelValue, resincronizar)

function onToggle(abierto) {
    if (abierto) {
        if (!aFecha(props.modelValue)) {
            modeloPicker.value = modeloDesde(propuestaInicial())
        }
        return
    }
    // Cerrado por Aplicar, Cancelar, Esc o clic afuera: se descarta lo no
    // aplicado. En nextTick, cuando el padre ya recibió el valor aplicado.
    nextTick(resincronizar)
}

function onUpdate({ startDate }) {
    const fecha = startDate ? new Date(startDate) : null
    if (!fecha || isNaN(fecha)) return
    emit('update:modelValue', aValor(fecha))
}

const localeEs = {
    direction: 'ltr',
    format: 'dd/mm/yyyy HH:MM', // HH = 24 h, MM = minutos (mm = mes)
    separator: ' - ',
    applyLabel: 'Aplicar',
    cancelLabel: 'Cancelar',
    weekLabel: 'Sem',
    customRangeLabel: 'Personalizado',
    daysOfWeek: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá'],
    monthNames: [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ],
    firstDay: 1,
}
</script>

<template>
    <DateRangePicker
        v-model="modeloPicker"
        :locale-data="localeEs"
        single-date-picker="single"
        :ranges="false"
        :time-picker="true"
        :time-picker24-hour="true"
        :time-picker-increment="1"
        :auto-apply="false"
        :show-dropdowns="false"
        :disabled="disabled"
        :opens="opens"
        :control-container-class="[
            'form-control date-time-input',
            { 'is-invalid': invalid, 'date-time-input--disabled': disabled },
        ]"
        @toggle="onToggle"
        @update="onUpdate"
    >
        <template #input>
            <span class="date-time-input__texto" :class="{ 'text-muted': !aTexto(modelValue) }">
                {{ aTexto(modelValue) || placeholder }}
            </span>
            <i class="ri-calendar-schedule-line date-time-input__icono" aria-hidden="true"></i>
        </template>
    </DateRangePicker>
</template>
