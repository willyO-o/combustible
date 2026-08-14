<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    suggestions: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    placeholder: { type: String, default: 'Escribe una actividad por línea...' },
    helpText: { type: String, default: '' },
    rows: { type: Number, default: 4 },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    invalidFeedback: { type: String, default: '' },
    // Carácter que separa cada actividad. Por defecto: salto de línea.
    delimiter: { type: String, default: '' },
    // Mínimo de caracteres escritos antes de mostrar sugerencias.
    minChars: { type: Number, default: 1 },
    // Máximo de sugerencias visibles en el dropdown.
    maxSuggestions: { type: Number, default: 8 },
    caseSensitive: { type: Boolean, default: true },
    // Ignora tildes/diacríticos al buscar (ej: "inspeccion" encuentra "Inspección").
    // Actívalo en true por defecto ya que es el comportamiento esperado en español.
    ignoreAccents: { type: Boolean, default: true },
    // Añade automáticamente el delimitador tras seleccionar una sugerencia.
    appendDelimiterOnSelect: { type: Boolean, default: true },
    showHints: { type: Boolean, default: true },
    // Actívalo cuando el componente se use dentro de un modal (o cualquier
    // contenedor con overflow: hidden/auto) para que el dropdown se renderice
    // fuera de ese contenedor y no se recorte ni quede tapado.
    usePortal: { type: Boolean, default: false },
    // --- Soporte para arrays de objetos (no solo strings) ---
    // Qué propiedad usar para MOSTRAR y FILTRAR cada sugerencia.
    // Acepta: nada (si "suggestions" ya es array de strings), un nombre de
    // propiedad ("nombre"), o una función (item) => string para casos como
    // concatenar campos: (item) => `${item.codigo} - ${item.nombre}`.
    optionLabel: { type: [String, Function], default: null },
    // Qué propiedad INSERTAR en el textarea al seleccionar. Si no se define,
    // se usa el mismo resultado de optionLabel.
    optionValue: { type: [String, Function], default: null },
})

const emit = defineEmits(['update:modelValue', 'select'])

const uid = `ta-autocomplete-${Math.random().toString(36).slice(2, 9)}`

const textareaRef = ref(null)
const internalValue = ref(props.modelValue)
const cursorPos = ref(0)
const currentSegment = ref('')
const activeIndex = ref(-1)
const showSuggestions = ref(false)
const portalStyle = ref({})

function computePortalPosition() {
    if (!textareaRef.value) return
    const rect = textareaRef.value.getBoundingClientRect()
    portalStyle.value = {
        position: 'fixed',
        top: `${rect.bottom + 2}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        // Por encima del z-index del modal de Bootstrap (1055) y su backdrop.
        zIndex: 1080,
    }
}

function handleReposition() {
    if (showSuggestions.value && props.usePortal) computePortalPosition()
}

watch(showSuggestions, (visible) => {
    if (visible && props.usePortal) {
        nextTick(computePortalPosition)
    }
})

if (typeof window !== 'undefined') {
    // capture:true para detectar scroll dentro del modal (que no burbujea a window)
    window.addEventListener('scroll', handleReposition, true)
    window.addEventListener('resize', handleReposition)
    onBeforeUnmount(() => {
        window.removeEventListener('scroll', handleReposition, true)
        window.removeEventListener('resize', handleReposition)
    })
}

// Mantener sincronizado con el valor externo (v-model)
watch(
    () => props.modelValue,
    (val) => {
        if (val !== internalValue.value) internalValue.value = val
    }
)

watch(internalValue, (val) => {
    emit('update:modelValue', val)
})

// Quita mayúsculas (salvo caseSensitive) y tildes/diacríticos (salvo ignoreAccents=false).
// Usa NFD (descompone "é" en "e" + acento) y elimina la marca de acento con regex.
const normalize = (str) => {
    let result = props.caseSensitive ? str : str.toLowerCase()
    if (props.ignoreAccents) {
        result = result.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    }
    return result
}

// Devuelve el texto a mostrar/filtrar para una sugerencia, sea string u objeto.
function getLabel(item) {
    if (item == null) return ''
    if (typeof item === 'string' || typeof item === 'number') return String(item)
    if (typeof props.optionLabel === 'function') return String(props.optionLabel(item))
    if (typeof props.optionLabel === 'string') return String(item[props.optionLabel] ?? '')
    // Objeto sin optionLabel definido: aviso silencioso vía fallback legible.
    return String(item.label ?? item.nombre ?? item.name ?? JSON.stringify(item))
}

// Devuelve el texto que se inserta en el textarea al seleccionar.
function getValue(item) {
    if (item == null) return ''
    if (typeof item === 'string' || typeof item === 'number') return String(item)
    if (typeof props.optionValue === 'function') return String(props.optionValue(item))
    if (typeof props.optionValue === 'string') return String(item[props.optionValue] ?? '')
    // Sin optionValue: usa el mismo texto que se muestra.
    return getLabel(item)
}

const filteredSuggestions = computed(() => {
    const query = normalize(currentSegment.value.trim())
    if (query.length < props.minChars) return []

    const matches = props.suggestions.filter((item) => normalize(getLabel(item)).includes(query))

    // Si ya coincide exactamente con la única sugerencia, no mostrar nada.
    if (matches.length === 1 && normalize(getLabel(matches[0])) === query) return []

    // Prioriza coincidencias que empiezan con el texto escrito.
    matches.sort((a, b) => {
        const aStarts = normalize(getLabel(a)).startsWith(query) ? 0 : 1
        const bStarts = normalize(getLabel(b)).startsWith(query) ? 0 : 1
        return aStarts - bStarts || getLabel(a).localeCompare(getLabel(b))
    })

    return matches.slice(0, props.maxSuggestions)
})

function updateCursorPosition(e) {
    cursorPos.value = e.target.selectionStart
}

function getSegmentFromCursor() {
    const textBeforeCursor = internalValue.value.slice(0, cursorPos.value)
    const parts = textBeforeCursor.split(props.delimiter)
    return parts[parts.length - 1]
}

function onInput(e) {
    cursorPos.value = e.target.selectionStart
    currentSegment.value = getSegmentFromCursor()

    if (currentSegment.value?.trim()?.length >= props.minChars) {
        showSuggestions.value = true
        activeIndex.value = 0
    } else {
        showSuggestions.value = false
        activeIndex.value = -1
    }
}

function onFocus(e) {
    updateCursorPosition(e)
}

function onKeydown(e) {
    if (!showSuggestions.value || !filteredSuggestions.value.length) return

    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault()
            activeIndex.value = (activeIndex.value + 1) % filteredSuggestions.value.length
            break
        case 'ArrowUp':
            e.preventDefault()
            activeIndex.value =
                (activeIndex.value - 1 + filteredSuggestions.value.length) % filteredSuggestions.value.length
            break
        case 'Enter':
            if (activeIndex.value >= 0) {
                e.preventDefault()
                selectSuggestion(filteredSuggestions.value[activeIndex.value])
            }
            break
        case 'Escape':
            showSuggestions.value = false
            break
        case 'Tab':
            // stopPropagation evita choques con el focus-trap de Bootstrap dentro de modales.
            if (activeIndex.value >= 0) {
                e.preventDefault()
                e.stopPropagation()
                selectSuggestion(filteredSuggestions.value[activeIndex.value])
            }
            break
    }
}

function selectSuggestion(item) {
    const value = internalValue.value
    const textBeforeCursor = value.slice(0, cursorPos.value)
    const textAfterCursor = value.slice(cursorPos.value)

    const lastDelimiterIndex = textBeforeCursor.lastIndexOf(props.delimiter)
    const prefix = textBeforeCursor.slice(0, lastDelimiterIndex + 1)

    const insertText = getValue(item)
    const suffix = props.appendDelimiterOnSelect ? props.delimiter : ''
    const newValue = prefix + insertText + suffix + textAfterCursor

    internalValue.value = newValue
    showSuggestions.value = false
    activeIndex.value = -1

    // Emite el ítem original completo (string u objeto) para que el padre
    // pueda usar otras propiedades (id, categoría, etc.) si lo necesita.
    emit('select', item)

    nextTick(() => {
        const newCursorPos = (prefix + insertText + suffix).length
        textareaRef.value.focus()
        textareaRef.value.setSelectionRange(newCursorPos, newCursorPos)
        cursorPos.value = newCursorPos
    })
}

function onBlur() {
    // Pequeño retraso para permitir que el click en la sugerencia se registre.
    setTimeout(() => {
        showSuggestions.value = false
    }, 150)
}

function highlight(text) {
    const query = currentSegment.value.trim()
    if (!query) return text

    // Busca la posición de la coincidencia sobre las versiones normalizadas
    // (sin tildes/mayúsculas según config) pero resalta sobre el texto ORIGINAL,
    // ya que quitar solo la marca diacrítica preserva la posición carácter a carácter.
    const normalizedText = normalize(text)
    const normalizedQuery = normalize(query)
    const matchIndex = normalizedText.indexOf(normalizedQuery)

    if (matchIndex === -1) return text

    const before = text.slice(0, matchIndex)
    const match = text.slice(matchIndex, matchIndex + query.length)
    const after = text.slice(matchIndex + query.length)

    return `${before}<strong class="text-warning ">${match}</strong>${after}`
}
</script>

<template>
    <div class="position-relative">
        <label v-if="label" :for="uid" class="form-label">
            {{ label }}
            <span v-if="required" class="text-danger">*</span>
        </label>

        <textarea :id="uid" ref="textareaRef" v-model="internalValue" class="form-control"
            :class="{ 'is-invalid': invalid }" :rows="rows" :placeholder="placeholder" :disabled="disabled"
            autocomplete="off" @input="onInput" @keydown="onKeydown" @click="updateCursorPosition"
            @keyup="updateCursorPosition" @blur="onBlur" @focus="onFocus"></textarea>

        <div v-if="helpText && !invalid" class="form-text">{{ helpText }}</div>
        <div v-if="invalid && invalidFeedback" class="invalid-feedback">{{ invalidFeedback }}</div>

        <!-- Dropdown de sugerencias -->
        <!-- usePortal="true" lo saca del flujo del DOM (útil dentro de modales con overflow) -->
        <Teleport to="body" :disabled="!usePortal">
            <ul v-if="showSuggestions && filteredSuggestions.length"  class="list-group shadow-sm suggestion-dropdown"
                :class="usePortal ? 'suggestion-dropdown--portal' : 'position-absolute w-100'"
                :style="usePortal ? portalStyle : { zIndex: 1055 }">
                <li v-for="(item, index) in filteredSuggestions" :key="getValue(item) + index"
                    class="list-group-item list-group-item-action py-1 px-2 small"
                    :class="{ active: index === activeIndex }" @mousedown.prevent="selectSuggestion(item)"
                    @mouseenter="activeIndex = index">
                    <span v-html="highlight(getLabel(item))"></span>
                </li>

                <li v-if="suggestions.length > maxSuggestions && filteredSuggestions.length === maxSuggestions"
                    class="list-group-item disabled small text-muted py-1 px-2 fst-italic">
                    Sigue escribiendo para afinar la búsqueda…
                </li>
            </ul>
        </Teleport>

        <!-- Ayuda de teclado (opcional, visible sólo cuando hay sugerencias) -->
        <div v-if="showSuggestions && filteredSuggestions.length && showHints" class="form-text mt-1">
            <kbd>↑</kbd> <kbd>↓</kbd> para navegar · <kbd>Enter</kbd> para seleccionar · <kbd>Esc</kbd> para cerrar
        </div>
    </div>
</template>


<style scoped>
.suggestion-dropdown {
    max-height: 220px;
    overflow-y: auto;
    margin-top: 2px;
}

/* En modo portal el estilo inline (position/top/left/width) ya lo posiciona;
   aquí solo controlamos apariencia. Sin "scoped" el navegador igual aplica
   estos estilos porque Vue les añade el atributo data-v-xxxx al <ul>. */
.suggestion-dropdown--portal {
    margin-top: 0;
}

.suggestion-dropdown .list-group-item {
    cursor: pointer;
}



.suggestion-dropdown .list-group-item.active {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
    color: #fff;
}

.suggestion-dropdown .list-group-item.active strong {
    color: #fff;
}
</style>
