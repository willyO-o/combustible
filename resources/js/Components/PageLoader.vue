<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'

/**
 * Loader global de transición de página: se activa automáticamente en cada
 * navegación de Inertia (router.on('start'/'finish')), sin que cada página
 * tenga que hacer nada. Se monta una única vez a nivel raíz en app.js, junto
 * a <App>, para que funcione también entre layouts distintos (p.ej. Login ->
 * Dashboard).
 */

// Pequeños retardos para evitar parpadeos: no se muestra si la navegación es
// casi instantánea, y una vez mostrado permanece un mínimo de tiempo para
// que la animación no se corte a la mitad.
const SHOW_DELAY = 150
const MIN_VISIBLE_TIME = 350

const visible = ref(false)

let showTimer = null
let shownAt = null
let hideTimer = null

function limpiarTimers() {
    if (showTimer) {
        clearTimeout(showTimer)
        showTimer = null
    }
    if (hideTimer) {
        clearTimeout(hideTimer)
        hideTimer = null
    }
}

// Inertia marca como `showProgress: false` las visitas que no son una
// navegación real: polling (usePoll, p.ej. las notificaciones del header
// cada 30s), prefetch y recargas parciales en segundo plano. Sin este
// filtro, el loader "parpadeaba" solo cada cierto tiempo aunque el usuario
// no estuviera navegando.
function esNavegacionVisible(event) {
    return event?.detail?.visit?.showProgress !== false
}

function alIniciar(event) {
    if (!esNavegacionVisible(event)) {
        return
    }

    // Si ya está visible (navegación solapada con la anterior), sólo
    // cancelamos un posible ocultamiento pendiente y seguimos mostrando.
    if (hideTimer) {
        clearTimeout(hideTimer)
        hideTimer = null
    }
    if (visible.value || showTimer) {
        return
    }

    showTimer = setTimeout(() => {
        showTimer = null
        visible.value = true
        shownAt = Date.now()
    }, SHOW_DELAY)
}

function alFinalizar(event) {
    if (!esNavegacionVisible(event)) {
        return
    }

    if (showTimer) {
        // La navegación terminó antes de llegar a mostrarse: no hace falta.
        clearTimeout(showTimer)
        showTimer = null
        return
    }

    if (!visible.value) {
        return
    }

    const transcurrido = Date.now() - (shownAt ?? Date.now())
    const restante = Math.max(MIN_VISIBLE_TIME - transcurrido, 0)

    hideTimer = setTimeout(() => {
        hideTimer = null
        visible.value = false
    }, restante)
}

let quitarListenerStart = null
let quitarListenerFinish = null

onMounted(() => {
    quitarListenerStart = router.on('start', alIniciar)
    quitarListenerFinish = router.on('finish', alFinalizar)
})

onBeforeUnmount(() => {
    quitarListenerStart?.()
    quitarListenerFinish?.()
    limpiarTimers()
})
</script>

<template>
    <Transition name="page-loader-fade">
        <div v-if="visible" class="page-loader-overlay" role="status" aria-live="polite" aria-label="Cargando">
            <div class="page-loader-box">
                <span class="page-loader-icon">
                    <i class="ri-gas-station-line page-loader-icon-base"></i>
                    <i class="ri-gas-station-fill page-loader-icon-fill"></i>
                </span>
                <span class="page-loader-text">Cargando<span class="page-loader-dots"><span>.</span><span>.</span><span>.</span></span></span>
            </div>
        </div>
    </Transition>
</template>

<style scoped lang="scss">
.page-loader-overlay {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(var(--body-bg-rgb), 0.85);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}

.page-loader-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
}

.page-loader-icon {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 4rem;
    height: 4rem;
}

.page-loader-icon-base,
.page-loader-icon-fill {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    line-height: 1;
}

.page-loader-icon-base {
    color: var(--text-muted);
    opacity: 0.3;
}

.page-loader-icon-fill {
    color: var(--primary-color);
    animation: page-loader-fill 1.6s ease-in-out infinite;
}

.page-loader-text {
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--text-muted);
    letter-spacing: 0.02em;
}

.page-loader-dots span {
    animation: page-loader-blink 1.4s infinite;
    animation-fill-mode: both;
}

.page-loader-dots span:nth-child(2) {
    animation-delay: 0.2s;
}

.page-loader-dots span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes page-loader-fill {
    0% {
        clip-path: inset(100% 0 0 0);
    }

    45% {
        clip-path: inset(0 0 0 0);
    }

    60% {
        clip-path: inset(0 0 0 0);
    }

    100% {
        clip-path: inset(100% 0 0 0);
    }
}

@keyframes page-loader-blink {

    0%,
    80%,
    100% {
        opacity: 0.2;
    }

    40% {
        opacity: 1;
    }
}

.page-loader-fade-enter-active,
.page-loader-fade-leave-active {
    transition: opacity 0.2s ease;
}

.page-loader-fade-enter-from,
.page-loader-fade-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {

    .page-loader-icon-fill,
    .page-loader-dots span {
        animation: none;
    }
}
</style>
