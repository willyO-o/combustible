<script setup>
/**
 * Campo "Carnet de Identidad" que avisa si el CI ya está registrado.
 *
 * Mientras se escribe (con una pausa de 450 ms) consulta
 * search.persona-por-ci y, si existe, muestra un aviso fijo bajo el campo
 * con quién es y un enlace a su registro. Al salir del campo con un CI
 * repetido, además, abre una alerta (una sola vez por CI, para no
 * interrumpir a mitad de la escritura). Es sólo un aviso: el servidor sigue
 * rechazando el duplicado al guardar (unique en PersonaRequest/ConductorRequest).
 *
 * Uso:
 *   <CiInput v-model="form.ci" :error="form.errors.ci" />
 *   <CiInput v-model="form.ci" :error="form.errors.ci" :ignorar-id="persona.id" />  (edición)
 */
import { onBeforeUnmount, ref, watch } from 'vue'
import axios from 'axios'
import { showWarning } from '@/Utils/alertUtil.js'

const props = defineProps({
    modelValue: { type: String, default: '' },
    error: { type: String, default: '' },
    // Persona que se está editando: su propio CI no cuenta como repetido.
    ignorarId: { type: [Number, String], default: null },
    id: { type: String, default: 'ci' },
})

const emit = defineEmits(['update:modelValue'])

const verificando = ref(false)
const existente = ref(null) // persona encontrada (para el CI ya verificado)
const ciVerificado = ref('') // CI al que corresponde `existente`

let temporizador = null
let consultaActual = 0
const alertados = new Set()

const normalizar = (ci) => (ci ?? '').trim()

async function verificar(ci) {
    const numero = ++consultaActual
    verificando.value = true
    try {
        const { data } = await axios.get(route('search.persona-por-ci'), {
            params: { ci, ignorar: props.ignorarId || undefined },
        })
        // Sólo vale la respuesta de la última consulta (el CI pudo cambiar).
        if (numero !== consultaActual) return
        existente.value = data.existe ? data.persona : null
        ciVerificado.value = ci
    } catch {
        // Sin conexión o error: no se bloquea nada, el servidor valida al guardar.
        if (numero === consultaActual) {
            existente.value = null
            ciVerificado.value = ''
        }
    } finally {
        if (numero === consultaActual) verificando.value = false
    }
}

watch(() => props.modelValue, (valor) => {
    clearTimeout(temporizador)
    const ci = normalizar(valor)

    if (ci !== ciVerificado.value) {
        existente.value = null
    }
    if (ci.length < 3) {
        consultaActual++
        verificando.value = false
        ciVerificado.value = ''
        return
    }
    temporizador = setTimeout(() => verificar(ci), 450)
})

onBeforeUnmount(() => clearTimeout(temporizador))

function escapar(texto) {
    return String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

async function alSalir() {
    const ci = normalizar(props.modelValue)
    if (ci.length < 3) return

    // Si quedó una verificación pendiente, se resuelve ya.
    if (ci !== ciVerificado.value) {
        clearTimeout(temporizador)
        await verificar(ci)
    }

    const persona = existente.value
    if (!persona || alertados.has(ci)) return
    alertados.add(ci)

    const detalle = [persona.tipo, persona.estado_persona].filter(Boolean).map(escapar).join(' · ')
    showWarning(
        'Este CI ya está registrado',
        persona.eliminada
            ? `El CI <b>${escapar(persona.ci)}</b> pertenece a <b>${escapar(persona.nombre_completo)}</b>, una persona eliminada del sistema. No se puede volver a registrar con el mismo CI; consulta con un administrador.`
            : `El CI <b>${escapar(persona.ci)}</b> ya pertenece a <b>${escapar(persona.nombre_completo)}</b>${detalle ? ` (${detalle})` : ''}. Revisa el número o busca su registro existente en lugar de crear uno nuevo.`,
    )
}
</script>

<template>
    <div>
        <label :for="id" class="form-label fw-medium">Carnet de Identidad <span class="text-danger">*</span></label>
        <div class="position-relative">
            <input :id="id" :value="modelValue" type="text" class="form-control"
                :class="{ 'is-invalid': error || existente }" placeholder="Ej: 12345678" maxlength="20"
                autocomplete="off" @input="emit('update:modelValue', $event.target.value)" @blur="alSalir" />
            <span v-if="verificando" class="spinner-border spinner-border-sm text-muted ci-input__spinner"
                role="status" aria-label="Verificando CI"></span>
        </div>
        <div v-if="error" class="text-danger small mt-1">{{ error }}</div>
        <div v-else-if="existente" class="ci-input__aviso mt-1" role="alert">
            <i class="ri-error-warning-line" aria-hidden="true"></i>
            <span v-if="existente.eliminada">
                Pertenece a <strong>{{ existente.nombre_completo }}</strong>, persona eliminada. No se puede volver a usar.
            </span>
            <span v-else>
                Ya registrado: <strong>{{ existente.nombre_completo }}</strong><template v-if="existente.tipo"> · {{ existente.tipo }}</template>.
                <a v-if="existente.url" :href="existente.url" target="_blank" rel="noopener">Ver registro</a>
            </span>
        </div>
    </div>
</template>

<style scoped>
.ci-input__spinner {
    position: absolute;
    top: 50%;
    right: 0.75rem;
    margin-top: -0.5rem;
}
.ci-input__aviso {
    display: flex;
    gap: 0.35rem;
    align-items: flex-start;
    font-size: 0.8125rem;
    line-height: 1.35;
    color: var(--bs-danger, #dc3545);
}
.ci-input__aviso i {
    flex: none;
    margin-top: 0.1rem;
}
.ci-input__aviso a {
    margin-inline-start: 0.25rem;
    font-weight: 500;
    white-space: nowrap;
}
</style>
