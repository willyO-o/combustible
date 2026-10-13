<script setup>
/**
 * Contraseña inicial de una cuenta nueva, visible y editable.
 *
 * Se prellena con el formato por defecto "CI#Plusmetals" y lo sigue en vivo
 * mientras se escribe el C.I., hasta que el usuario la modifica a mano;
 * "Usar formato" vuelve al valor por defecto. Si se deja vacía, el servidor
 * aplica el mismo formato (User::contrasenaInicial), así que nunca queda una
 * cuenta sin contraseña. El formato se replica aquí sólo para mostrarlo: la
 * fuente es User::contrasenaPorDefecto().
 *
 * Uso:
 *   <ContrasenaInicialInput v-model="form.password" :ci="form.ci" :error="form.errors.password" />
 */
import { computed, onMounted, ref, watch } from 'vue'

const SUFIJO = '#Plusmetals'

const props = defineProps({
    modelValue: { type: String, default: '' },
    ci: { type: String, default: '' },
    error: { type: String, default: '' },
    id: { type: String, default: 'contrasena-inicial' },
})

const emit = defineEmits(['update:modelValue'])

const porDefecto = computed(() => {
    const ci = (props.ci ?? '').trim()
    return ci ? `${ci}${SUFIJO}` : ''
})

// true en cuanto el usuario escribe algo propio: desde ahí el C.I. ya no la pisa.
const editada = ref(false)
const visible = ref(true)

const esPorDefecto = computed(() => props.modelValue === porDefecto.value)

onMounted(() => {
    if (!props.modelValue) {
        emit('update:modelValue', porDefecto.value)
    } else if (!esPorDefecto.value) {
        editada.value = true
    }
})

watch(porDefecto, (valor) => {
    if (!editada.value) {
        emit('update:modelValue', valor)
    }
})

function onInput(evento) {
    editada.value = true
    emit('update:modelValue', evento.target.value)
}

function usarFormato() {
    editada.value = false
    emit('update:modelValue', porDefecto.value)
}
</script>

<template>
    <div>
        <label :for="id" class="form-label fw-medium">Contraseña inicial</label>
        <div class="input-group">
            <input
                :id="id"
                :value="modelValue"
                :type="visible ? 'text' : 'password'"
                class="form-control"
                :class="{ 'is-invalid': error }"
                maxlength="255"
                autocomplete="new-password"
                spellcheck="false"
                :placeholder="porDefecto || 'Escribe el C.I. para generarla'"
                @input="onInput"
            />
            <button type="button" class="btn btn-outline-light border" :title="visible ? 'Ocultar' : 'Mostrar'"
                :aria-label="visible ? 'Ocultar contraseña' : 'Mostrar contraseña'" @click="visible = !visible">
                <i :class="visible ? 'ri-eye-off-line' : 'ri-eye-line'" aria-hidden="true"></i>
            </button>
            <button v-if="!esPorDefecto && porDefecto" type="button" class="btn btn-outline-light border"
                title="Volver al formato CI#Plusmetals" @click="usarFormato">
                <i class="ri-refresh-line me-1" aria-hidden="true"></i>Usar formato
            </button>
        </div>
        <div v-if="error" class="text-danger small mt-1">{{ error }}</div>
        <div v-else class="form-text">
            <template v-if="esPorDefecto">Formato por defecto <code>CI#Plusmetals</code>. Puedes cambiarla.</template>
            <template v-else-if="!modelValue">Vacía: se usará <code>{{ porDefecto || 'CI#Plusmetals' }}</code>.</template>
            <template v-else>Contraseña personalizada (mínimo 8 caracteres, con letras y números).</template>
            El usuario podrá cambiarla desde su perfil.
        </div>
    </div>
</template>
