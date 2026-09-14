<script setup>
/**
 * Select de unidad de capacidad (m³, ton, L, etc.) + opción "Otro" que
 * habilita un input de texto libre. Reutilizado por Vehiculos/Create.vue
 * (capacidad_unidad) y TiposVehiculo/Create.vue (unidad_capacidad_sugerida)
 * — mismo set de opciones en los dos lugares para que la unidad sugerida de
 * un tipo de vehículo siempre case con una opción del selector de Vehiculo.
 *
 * v-model: string plano ('m³', 'kg', 'ton personalizada', '' si no hay
 * unidad elegida) — no un objeto, para que se guarde tal cual en la columna
 * varchar del backend (vehiculo.capacidad_unidad / tipo_vehiculo.unidad_capacidad_sugerida).
 */
import { ref, computed, watch } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, default: '' },
    invalid: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

// Capacidad y volumen, y peso/carga — las dos categorías que de verdad
// describen "cuánto carga/contiene" un vehículo o máquina (ver conversación
// de diseño: se dejó fuera a propósito presión/temperatura/consumo/etc.,
// que son especificaciones técnicas, no "capacidad").
const OPCIONES = [
    { value: 'm³', label: 'm³ — metros cúbicos (tolva, cuchara)' },
    { value: 'yd³', label: 'yd³ — yardas cúbicas' },
    { value: 'L', label: 'L — litros (tanque)' },
    { value: 'gal', label: 'gal — galones' },
    { value: 't', label: 't — toneladas métricas (carga útil)' },
    { value: 'kg', label: 'kg — kilogramos' },
    { value: 'lb', label: 'lb — libras' },
]
const VALORES_FIJOS = OPCIONES.map((o) => o.value)
const OTRO = '__otro__'

// Si el valor ya venía cargado (edición) y no calza con ninguna opción fija,
// se interpreta como "Otro" con ese texto precargado en el input.
const esOtro = ref(!!props.modelValue && !VALORES_FIJOS.includes(props.modelValue))
const textoOtro = ref(esOtro.value ? props.modelValue : '')

const seleccion = computed({
    get: () => (esOtro.value ? OTRO : props.modelValue || ''),
    set(valor) {
        if (valor === OTRO) {
            esOtro.value = true
            emit('update:modelValue', textoOtro.value)
        } else {
            esOtro.value = false
            textoOtro.value = ''
            emit('update:modelValue', valor)
        }
    },
})

// Re-sincroniza si el padre cambia el valor desde afuera (ej. al elegir un
// tipo de vehículo que sugiere una unidad, o al resetear el formulario).
watch(
    () => props.modelValue,
    (valor) => {
        if (esOtro.value && valor === textoOtro.value) return // eco de nuestro propio emit
        esOtro.value = !!valor && !VALORES_FIJOS.includes(valor)
        textoOtro.value = esOtro.value ? valor : ''
    },
)

function onTextoOtroInput() {
    emit('update:modelValue', textoOtro.value)
}
</script>

<template>
    <div>
        <select
            :id="id"
            v-model="seleccion"
            class="form-select"
            :class="{ 'is-invalid': invalid }"
        >
            <option value="">— Sin especificar —</option>
            <option v-for="o in OPCIONES" :key="o.value" :value="o.value">{{ o.label }}</option>
            <option :value="OTRO">Otro (especificar)...</option>
        </select>
        <input
            v-if="esOtro"
            v-model="textoOtro"
            type="text"
            class="form-control mt-2"
            :class="{ 'is-invalid': invalid }"
            placeholder="Ej: m³/h, ton corta..."
            maxlength="20"
            @input="onTextoOtroInput"
        />
    </div>
</template>
