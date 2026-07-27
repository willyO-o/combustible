<script setup>
/**
 * SearchSelect — wrapper de @vueform/multiselect para búsqueda asíncrona.
 * Props:
 *  - modelValue: valor seleccionado (objeto completo o null)
 *  - searchUrl:  URL del endpoint de búsqueda (recibe ?q=...)
 *  - valueProp:  campo que se usa como value (default 'id')
 *  - labelProp:  campo que se muestra (default 'label')
 *  - placeholder
 *  - invalid: booleano para mostrar borde rojo
 */
import Multiselect from '@vueform/multiselect'
import '@vueform/multiselect/themes/default.css'

import { ref, watch } from 'vue'

const props = defineProps({
    modelValue: { default: null },
    searchUrl: { type: String, required: true },
    valueProp: { type: String, default: 'id' },
    labelProp: { type: String, default: 'label' },
    placeholder: { type: String, default: 'Buscar...' },
    invalid: { type: Boolean, default: false },
    object: { type: Boolean, default: false }, // si true, devuelve objeto completo; si false, devuelve solo valueProp
    params: { type: Object, default: () => ({}) }, // parámetros adicionales para la búsqueda
    autoClear: { type: Boolean, default: true }, // si true, limpia el valor seleccionado al cambiar params
})

const multiselectRef = ref(null)
const emit = defineEmits(['update:modelValue', 'selected', 'cleared'])

async function search(query) {
    if (!query || query.length < 2) return []
    const url = new URL(props.searchUrl, window.location.origin)
    url.searchParams.set('q', query)
    Object.entries(props.params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            url.searchParams.set(key, value)
        }
    })
    const res = await fetch(url)
    if (!res.ok) return []
    return await res.json()
}


const onSelected = (option) => {
    emit('update:modelValue', option)
    // Solo emitimos selected cuando trabajamos con el objeto
    if (props.object) {
        emit('selected', option)
    }
}

const onCleared = () => {
    multiselectRef.value?.clear()

    emit('update:modelValue', null)
    emit('cleared')
}

const clear = () => {

    multiselectRef.value?.clear()

    emit('update:modelValue', null)
    emit('cleared')
}


watch(
    () => props.params,
    (newValue, oldValue) => {

        if (!props.autoClear)
            return

        if (JSON.stringify(newValue) !== JSON.stringify(oldValue)) {
            onCleared()
        }

    },
    {
        deep: true
    }
)
</script>

<template>
    <Multiselect ref="multiselectRef" :model-value="modelValue" @update:model-value="onSelected" @clear="onCleared"
        :options="search" :object="object" :value-prop="valueProp" :label="labelProp" :searchable="true" :min-chars="2"
        :delay="300" :resolve-on-load="false" :placeholder="placeholder" no-options-text="Sin resultados"
        no-results-text="No se encontraron resultados" :class="{ 'is-invalid-multiselect': invalid }" />
</template>

<style>
/* Integración con Bootstrap 5 */
.multiselect {
    --ms-font-size: 0.875rem;
    --ms-border-color: #dee2e6;
    --ms-border-color-active: #86b7fe;
    --ms-ring-color: rgba(13, 110, 253, 0.25);
    --ms-ring-width: 0.25rem;
    --ms-radius: 0.375rem;
    --ms-py: 0.375rem;
    --ms-px: 0.75rem;
    --ms-option-bg-selected: #0d6efd;
    --ms-option-bg-selected-pointed: #0a58ca;
    --ms-option-color-selected: #fff;
    --ms-option-color-selected-pointed: #fff;
}

.is-invalid-multiselect .multiselect-wrapper {
    border-color: #dc3545 !important;
}
</style>
