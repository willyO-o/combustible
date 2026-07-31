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

import { ref, computed, watch } from 'vue'
import axios from 'axios'

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
    minCharsProp: { type: Number, default: 2 }, // mínimo de caracteres para iniciar búsqueda
})

const multiselectRef = ref(null)
const emit = defineEmits(['update:modelValue', 'selected', 'cleared', 'change-data'])

async function search(query) {
    if ((!query || query.length < props.minCharsProp) && !Object.keys(props.params).length) return []
    const params = { q: query }
    Object.entries(props.params).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            params[key] = value
        }
    })
    try {
        const { data } = await axios.get(props.searchUrl, { params })
        return data
    } catch {
        return []
    }
}


const onSelected = (option) => {
    emit('update:modelValue', option)
    // Solo emitimos selected cuando trabajamos con el objeto
    if (props.object) {
        emit('selected', option)
    }
}

const onCleared = () => {
    emit('update:modelValue', null)
    emit('cleared')
}

const onChange = (value) => {
    emit('change-data', value)
}

// Texto de búsqueda actual, para decidir qué mensaje mostrar cuando no hay opciones
const searchQuery = ref('')

const onSearchChange = (query) => {
    searchQuery.value = query
}

// Si hay params extra, la búsqueda no depende del mínimo de caracteres (ver search())
const belowMinChars = computed(() =>
    !Object.keys(props.params).length && searchQuery.value.length < props.minCharsProp
)

const minCharsHintText = computed(() => {
    const min = props.minCharsProp
    return `Escriba al menos ${min} caracter${min === 1 ? '' : 'es'} para comenzar la búsqueda`
})

watch(
    () => props.params,
    (newValue, oldValue) => {

        if (!props.autoClear) return

        if (JSON.stringify(newValue) !== JSON.stringify(oldValue)) {
            multiselectRef.value?.clear()
            multiselectRef.value?.refreshOptions()

        }

    },
    {
        deep: true
    }
)
</script>

<template>
    <Multiselect ref="multiselectRef" :model-value="modelValue" @update:model-value="onSelected" @clear="onCleared"
        @change="onChange" @search-change="onSearchChange" :options="search" :object="object" :value-prop="valueProp"
        :label="labelProp" :searchable="true" :min-chars="minCharsProp" :delay="300" :resolve-on-load="false"
        :placeholder="placeholder" no-results-text="No se encontraron resultados"
        :class="{ 'is-invalid-multiselect': invalid }">
        <template #nooptions>
            <div class="multiselect-no-options">
                {{ belowMinChars ? minCharsHintText : 'Sin resultados' }}
            </div>
        </template>
    </Multiselect>
</template>

<style scoped>
/* definir la variable --ms-bg para dark y light */

/* .multiselect-search {
    --ms-bg: #000;
} */

/* .multiselect {
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
} */

/* .is-invalid-multiselect .multiselect-wrapper {
    border-color: #dc3545 !important;
} */
</style>
