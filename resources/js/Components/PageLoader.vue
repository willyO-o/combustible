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
    <!-- v-show (no v-if): el overlay se monta una sola vez y queda en el DOM
         oculto con display:none. Así el <img> del logo se descarga y decodifica
         al montar la app, y cada vez que el loader aparece ya está pintado (con
         v-if el subárbol se recreaba en cada navegación y el logo parpadeaba /
         "no cargaba" el primer frame). Oculto no tiene costo de render ni
         ejecuta las animaciones CSS. -->
    <Transition name="page-loader-fade">
        <div v-show="visible" class="page-loader-overlay" role="status" aria-live="polite" aria-label="Cargando">
            <div class="page-loader-box">
                <img class="page-loader-logo" src="/images/logo/logo-plus-metals.svg" alt="" aria-hidden="true" />
                <span class="page-loader-bar" aria-hidden="true"></span>
                <span class="page-loader-text">Cargando<span class="page-loader-dots"><span>.</span><span>.</span><span>.</span></span></span>

            </div>
        </div>
    </Transition>
</template>

<!-- Estilos en resources/css/my-styles.css (sección "PageLoader.vue"). -->

