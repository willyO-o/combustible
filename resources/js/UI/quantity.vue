<template>
    <button type="button" aria-label="button" :class="decClass" @click="decrementValue">
        <i class="ri-subtract-line"></i>
    </button>
    <input type="text" :class="inputClass" aria-label="quantity" :placeholder="placeholder" :name="name" v-model="inputValue" @blur="validateInput" />
    <button type="button" aria-label="button" :class="incClass" @click="incrementValue">
        <i class="ri-add-line"></i>
    </button>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
    decClass?: string
    inputClass?: string
    incClass?: string
    initialValue?: number
    name?: string
    placeholder?: string
    defaultValue?: number
}>()

const emit = defineEmits<{
    (e: 'input', value: number): void
}>()

const inputValue = ref<number | string>(props.initialValue ?? props.defaultValue ?? 1)

const incrementValue = () => {
    const parsed = parseInt(String(inputValue.value), 10)
    inputValue.value = isNaN(parsed) ? 1 : parsed + 1
}

const decrementValue = () => {
    const parsed = parseInt(String(inputValue.value), 10)
    if (isNaN(parsed) || parsed <= 1) {
        inputValue.value = 1
    } else {
        inputValue.value = parsed - 1
    }
}

const validateInput = () => {
    const parsed = parseInt(String(inputValue.value), 10)
    inputValue.value = isNaN(parsed) || parsed < 1 ? 1 : parsed
}

watch(inputValue, (newVal) => {
    const parsed = parseInt(String(newVal), 10)
    emit('input', isNaN(parsed) ? 1 : parsed)
})
</script>
