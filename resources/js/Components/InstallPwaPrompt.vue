<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Banner discreto para instalar la PWA. Se monta una única vez a nivel raíz
 * en app.js, junto a <App> y <PageLoader>.
 *
 * - Android/Chrome/Edge/Desktop: captura el evento nativo `beforeinstallprompt`
 *   y ofrece un botón que dispara el diálogo de instalación del navegador.
 * - iOS/iPadOS (Safari): no dispara `beforeinstallprompt` (Apple no lo
 *   soporta), así que se muestra un aviso con los pasos manuales de
 *   "Compartir → Añadir a pantalla de inicio".
 * - No se muestra si la app ya corre instalada (modo standalone) ni si el
 *   usuario ya la descartó recientemente.
 */

const DISMISS_KEY = 'pwa-install-dismissed-at'
const DISMISS_DIAS = 14

const visible = ref(false)
const esIOS = ref(false)
let deferredPrompt = null

function estaInstalada() {
    return (
        window.matchMedia?.('(display-mode: standalone)').matches ||
        window.navigator.standalone === true
    )
}

function fueDescartadoRecientemente() {
    const valor = localStorage.getItem(DISMISS_KEY)
    if (!valor) return false
    const dias = (Date.now() - Number(valor)) / (1000 * 60 * 60 * 24)
    return dias < DISMISS_DIAS
}

function descartar() {
    visible.value = false
    localStorage.setItem(DISMISS_KEY, String(Date.now()))
}

async function instalar() {
    if (!deferredPrompt) return
    deferredPrompt.prompt()
    await deferredPrompt.userChoice
    deferredPrompt = null
    visible.value = false
}

function alBeforeInstallPrompt(event) {
    event.preventDefault()
    deferredPrompt = event
    if (!estaInstalada() && !fueDescartadoRecientemente()) {
        visible.value = true
    }
}

function alInstalado() {
    visible.value = false
    deferredPrompt = null
}

onMounted(() => {
    if (estaInstalada() || fueDescartadoRecientemente()) {
        return
    }

    esIOS.value = /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !window.MSStream

    window.addEventListener('beforeinstallprompt', alBeforeInstallPrompt)
    window.addEventListener('appinstalled', alInstalado)

    // iOS nunca dispara beforeinstallprompt: se muestra el aviso manual
    // directamente, con un pequeño retardo para no competir con la carga inicial.
    if (esIOS.value) {
        setTimeout(() => {
            if (!estaInstalada()) visible.value = true
        }, 2500)
    }
})

onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', alBeforeInstallPrompt)
    window.removeEventListener('appinstalled', alInstalado)
})
</script>

<template>
    <Transition name="pwa-banner-fade">
        <div v-if="visible" class="pwa-install-banner" role="status" aria-live="polite">
            <span class="pwa-install-icon">
                <i class="ri-gas-station-fill"></i>
            </span>

            <div class="pwa-install-texto">
                <template v-if="esIOS">
                    <strong>Instala esta app en tu iPhone/iPad</strong>
                    <span>
                        Toca <i class="ri-share-forward-line"></i> Compartir y luego
                        "Añadir a pantalla de inicio".
                    </span>
                </template>
                <template v-else>
                    <strong>Instala la aplicación</strong>
                    <span>Accede más rápido, incluso sin conexión.</span>
                </template>
            </div>

            <button v-if="!esIOS" type="button" class="btn btn-primary btn-sm pwa-install-btn" @click="instalar">
                <i class="ri-download-2-line me-1"></i> Instalar
            </button>

            <button type="button" class="pwa-install-close" aria-label="Cerrar" @click="descartar">
                <i class="ri-close-line"></i>
            </button>
        </div>
    </Transition>
</template>

<style scoped lang="scss">
.pwa-install-banner {
    position: fixed;
    left: 1rem;
    right: 1rem;
    // deja despejado el botón flotante ".scrollToTop" del tema (fixed,
    // bottom:20px, 2.5rem) para que nunca queden superpuestos.
    bottom: calc(20px + 2.5rem + 0.75rem);
    z-index: 9000;
    max-width: 26rem;
    margin: 0 auto;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.9rem;
    border-radius: 0.75rem;
    background: var(--custom-white, #fff);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.18);
    border: 1px solid var(--default-border, rgba(0, 0, 0, 0.08));
}

.pwa-install-icon {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.65rem;
    background: var(--primary-color, #162450);
    color: #fff;
    font-size: 1.35rem;
}

.pwa-install-texto {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pwa-install-texto strong {
    font-size: 0.85rem;
}

.pwa-install-texto span {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.pwa-install-btn {
    flex-shrink: 0;
    white-space: nowrap;
}

.pwa-install-close {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border: 0;
    background: transparent;
    color: var(--text-muted);
    font-size: 1.1rem;
    border-radius: 50%;
}

.pwa-install-close:hover {
    background: rgba(0, 0, 0, 0.06);
}

.pwa-banner-fade-enter-active,
.pwa-banner-fade-leave-active {
    transition: opacity 0.25s ease, transform 0.25s ease;
}

.pwa-banner-fade-enter-from,
.pwa-banner-fade-leave-to {
    opacity: 0;
    transform: translateY(0.5rem);
}
</style>
