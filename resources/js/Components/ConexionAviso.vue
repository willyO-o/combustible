<script setup>
/**
 * Aviso de conexión (estilo Facebook): aparece abajo cuando se pierde la
 * conexión y, al volver, confirma "Se restableció tu conexión" y se oculta.
 *
 * Se monta una sola vez en app.js, como hermano de <App> (igual que
 * PageLoader), así funciona en todas las pantallas, también en el login.
 *
 * Distingue dos casos porque el servidor está en la red local: sin red en el
 * equipo ("sin conexión a internet") y red presente pero el sistema no
 * responde ("no se puede conectar con el servidor"). `navigator.onLine` sólo
 * sabe lo primero, así que la vuelta se confirma pidiendo la ruta de salud de
 * Laravel (/up). Señales que disparan la comprobación: eventos online/offline
 * del navegador, una navegación de Inertia que falla sin respuesta (evento
 * "exception"; el header consulta notificaciones cada 30 s, así que una
 * caída del servidor se detecta sola), volver a la pestaña y "Reintentar".
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const REINTENTO_MS = 8000
const OCULTAR_RESTABLECIDA_MS = 4500
const TIMEOUT_PING_MS = 6000

// 'conectado' | 'sin-red' | 'sin-servidor' | 'restablecida'
const estado = ref('conectado')
const descartado = ref(false)
const comprobando = ref(false)

const sinConexion = computed(() => estado.value === 'sin-red' || estado.value === 'sin-servidor')
const visible = computed(() => estado.value === 'restablecida' || (sinConexion.value && !descartado.value))

const textos = computed(() => ({
    'sin-red': {
        titulo: 'Estás sin conexión a internet',
        detalle: 'Revisa tu Wi‑Fi o datos móviles. Puedes seguir viendo esta página.',
    },
    'sin-servidor': {
        titulo: 'No se puede conectar con el servidor',
        detalle: 'Tu red funciona, pero el sistema no responde. Seguimos intentando.',
    },
    restablecida: { titulo: 'Se restableció tu conexión', detalle: '' },
}[estado.value] ?? { titulo: '', detalle: '' }))

let temporizadorReintento = null
let temporizadorOcultar = null

const page = usePage()

// Raíz real del sistema (host y subcarpeta de la petición), derivada de la
// prop compartida storage_url; no de APP_URL, que puede no coincidir.
function raizDelSistema() {
    const base = page.props?.storage_url
    return typeof base === 'string' ? base.replace(/\/storage\/?$/, '') : ''
}

async function servidorResponde() {
    const control = new AbortController()
    const limite = setTimeout(() => control.abort(), TIMEOUT_PING_MS)
    try {
        const respuesta = await fetch(`${raizDelSistema()}/up?_=${Date.now()}`, {
            method: 'HEAD',
            cache: 'no-store',
            credentials: 'same-origin',
            signal: control.signal,
        })
        return respuesta.ok
    } catch {
        return false
    } finally {
        clearTimeout(limite)
    }
}

function programarReintento() {
    clearTimeout(temporizadorReintento)
    temporizadorReintento = setTimeout(comprobar, REINTENTO_MS)
}

function marcarSinConexion() {
    clearTimeout(temporizadorOcultar)
    const nuevo = navigator.onLine ? 'sin-servidor' : 'sin-red'
    if (!sinConexion.value) {
        descartado.value = false
    }
    estado.value = nuevo
    programarReintento()
}

function marcarConectado() {
    clearTimeout(temporizadorReintento)
    if (!sinConexion.value) {
        return
    }
    estado.value = 'restablecida'
    clearTimeout(temporizadorOcultar)
    temporizadorOcultar = setTimeout(() => {
        estado.value = 'conectado'
    }, OCULTAR_RESTABLECIDA_MS)
}

async function comprobar() {
    if (comprobando.value) {
        return
    }
    comprobando.value = true
    try {
        const conectado = navigator.onLine && (await servidorResponde())
        conectado ? marcarConectado() : marcarSinConexion()
    } finally {
        comprobando.value = false
    }
}

function alPerderRed() {
    marcarSinConexion()
}

function alVolverRed() {
    // El navegador dice "hay red", pero sólo se confirma si el servidor responde.
    comprobar()
}

function alCambiarVisibilidad() {
    if (document.visibilityState === 'visible' && sinConexion.value) {
        comprobar()
    }
}

let quitarEscuchaInertia = null

onMounted(() => {
    window.addEventListener('offline', alPerderRed)
    window.addEventListener('online', alVolverRed)
    document.addEventListener('visibilitychange', alCambiarVisibilidad)

    // Una visita/recarga de Inertia que falla SIN respuesta del servidor
    // (corte de red, servidor caído). Con respuesta (404, 500, 422...) no es
    // un problema de conexión y no se toca. No se cancela el evento: el
    // manejo normal de Inertia sigue igual.
    quitarEscuchaInertia = router.on('exception', (evento) => {
        if (!evento.detail?.exception?.response) {
            comprobar()
        }
    })

    if (!navigator.onLine) {
        marcarSinConexion()
    }
})

onBeforeUnmount(() => {
    window.removeEventListener('offline', alPerderRed)
    window.removeEventListener('online', alVolverRed)
    document.removeEventListener('visibilitychange', alCambiarVisibilidad)
    quitarEscuchaInertia?.()
    clearTimeout(temporizadorReintento)
    clearTimeout(temporizadorOcultar)
})
</script>

<template>
    <Transition name="conexion">
        <div v-if="visible" class="conexion-aviso" :class="`conexion-aviso--${estado}`"
            :role="sinConexion ? 'alert' : 'status'" :aria-live="sinConexion ? 'assertive' : 'polite'">
            <span class="conexion-aviso__icono" aria-hidden="true">
                <i :class="estado === 'restablecida' ? 'ri-wifi-line' : estado === 'sin-red' ? 'ri-wifi-off-line' : 'ri-server-line'"></i>
            </span>

            <div class="conexion-aviso__texto">
                <strong>{{ textos.titulo }}</strong>
                <span v-if="textos.detalle">{{ textos.detalle }}</span>
            </div>

            <template v-if="sinConexion">
                <button type="button" class="conexion-aviso__accion" :disabled="comprobando" @click="comprobar">
                    <span v-if="comprobando" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                    {{ comprobando ? 'Comprobando…' : 'Reintentar' }}
                </button>
                <button type="button" class="conexion-aviso__cerrar" aria-label="Ocultar aviso" @click="descartado = true">
                    <i class="ri-close-line" aria-hidden="true"></i>
                </button>
            </template>
        </div>
    </Transition>
</template>

<style scoped>
/* Superficie invertida respecto del tema (oscura en claro, clara en oscuro),
   como los avisos de Facebook. Tokens propios: --custom-white varía según el
   estilo de página y no garantiza contraste aquí. */
.conexion-aviso {
    --conexion-fondo: #1d2129;
    --conexion-texto: #ffffff;
    --conexion-texto-suave: rgba(255, 255, 255, 0.74);
    --conexion-separador: rgba(255, 255, 255, 0.14);
    --conexion-icono-fondo: rgba(255, 255, 255, 0.12);
    --conexion-exito: #32d484;

    position: fixed;
    left: 1.25rem;
    bottom: calc(1.25rem + env(safe-area-inset-bottom, 0px));
    z-index: 100001; /* sobre el PageLoader: si una navegación queda colgada por el corte, se ve por qué */
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: max-content;
    max-width: min(30rem, calc(100vw - 2.5rem));
    padding: 0.7rem 0.6rem 0.7rem 0.75rem;
    border-radius: 0.85rem;
    background: var(--conexion-fondo);
    color: var(--conexion-texto);
    box-shadow:
        0 0.75rem 2rem -0.5rem rgba(0, 0, 0, 0.45),
        0 0.15rem 0.4rem rgba(0, 0, 0, 0.2);
}

/* Sin :global(): Vue lo compilaría sólo como "[data-theme-mode=dark]" y
   descartaría ".conexion-aviso". Así, el atributo de alcance va al final. */
[data-theme-mode='dark'] .conexion-aviso {
    --conexion-fondo: #f1f3f7;
    --conexion-texto: #14171d;
    --conexion-texto-suave: rgba(20, 23, 29, 0.7);
    --conexion-separador: rgba(20, 23, 29, 0.12);
    --conexion-icono-fondo: rgba(20, 23, 29, 0.08);
    --conexion-exito: #13995a;
}

.conexion-aviso__icono {
    flex: none;
    display: inline-grid;
    place-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    background: var(--conexion-icono-fondo);
    font-size: 1.15rem;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.conexion-aviso--restablecida .conexion-aviso__icono {
    background: var(--conexion-exito);
    color: #fff;
    animation: conexion-pulso 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}

.conexion-aviso__texto {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
    line-height: 1.3;
}

.conexion-aviso__texto strong {
    font-size: 0.875rem;
    font-weight: 600;
}

.conexion-aviso__texto span {
    font-size: 0.78rem;
    color: var(--conexion-texto-suave);
}

.conexion-aviso__accion {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    margin-inline-start: 0.25rem;
    padding: 0.4rem 0.7rem;
    border: 0;
    border-inline-start: 1px solid var(--conexion-separador);
    border-radius: 0;
    background: transparent;
    color: inherit;
    font-size: 0.82rem;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
}

.conexion-aviso__accion:disabled {
    cursor: default;
    opacity: 0.75;
}

.conexion-aviso__cerrar {
    flex: none;
    display: inline-grid;
    place-items: center;
    width: 2rem;
    height: 2rem;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: var(--conexion-texto-suave);
    font-size: 1.1rem;
    cursor: pointer;
}

@media (hover: hover) {
    .conexion-aviso__accion:not(:disabled):hover {
        text-decoration: underline;
        text-underline-offset: 0.2em;
    }
    .conexion-aviso__cerrar:hover {
        background: var(--conexion-icono-fondo);
        color: var(--conexion-texto);
    }
}

.conexion-aviso__accion:focus-visible,
.conexion-aviso__cerrar:focus-visible {
    outline: 2px solid currentColor;
    outline-offset: 2px;
}

/* Celular: a todo el ancho, abajo (como en la app de Facebook). */
@media (max-width: 575.98px) {
    .conexion-aviso {
        left: 0.75rem;
        right: 0.75rem;
        bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));
        width: auto;
        max-width: none;
    }
}

/* Entrada: sube desde abajo con desaceleración; salida más corta. */
.conexion-enter-active {
    transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease-out;
}
.conexion-leave-active {
    transition: transform 0.2s ease-in, opacity 0.2s ease-in;
}
.conexion-enter-from,
.conexion-leave-to {
    opacity: 0;
    transform: translateY(1.25rem);
}

@keyframes conexion-pulso {
    0% { transform: scale(0.6); }
    60% { transform: scale(1.12); }
    100% { transform: scale(1); }
}

@media (prefers-reduced-motion: reduce) {
    .conexion-enter-active,
    .conexion-leave-active {
        transition: opacity 0.15s linear;
    }
    .conexion-enter-from,
    .conexion-leave-to {
        transform: none;
    }
    .conexion-aviso--restablecida .conexion-aviso__icono {
        animation: none;
    }
}
</style>
