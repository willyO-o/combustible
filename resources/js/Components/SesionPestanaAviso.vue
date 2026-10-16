<script setup>
/**
 * Aviso de sesión cambiada (varias pestañas comparten una sola sesión).
 *
 * Se comparan dos identidades ({ id, nombre }; id = id del usuario o "invitado"):
 *  - `mostrada`: la de la página que se ve en la pestaña. Puede venir del
 *    historial: al ir "Atrás", Inertia restaura la página guardada sin
 *    consultar al servidor (p.ej. la del usuario que ya cerró sesión).
 *  - `sesion`: la de la cookie de sesión, según lo último confirmado por el
 *    servidor (carga inicial, respuestas de visitas de Inertia) o anunciado por
 *    otra pestaña por BroadcastChannel al iniciar/cerrar sesión.
 * Si no coinciden, la pestaña está desactualizada: se cubre con un aviso que no
 * deja seguir operando hasta actualizarla. Si vuelven a coincidir (p.ej. se
 * vuelve a entrar con el mismo usuario en otra pestaña), el aviso se quita.
 *
 * Es sólo la capa visible: el bloqueo real lo hace el middleware
 * VerificarSesionDePestana (las visitas de Inertia se recargan solas con 409;
 * una petición axios directa dispara EVENTO_SESION_CAMBIADA y muestra este aviso).
 *
 * Cada pestaña recuerda además en sessionStorage (propio de la pestaña y que
 * sobrevive a la recarga) con qué usuario estaba: si al recargarse resulta ser
 * otro, avisa con un toast por qué cambió.
 *
 * Se monta en app.js como hermano de <App>, igual que ConexionAviso.
 */
import { router } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { showToast } from '@/Utils/alertUtil';
import {
    EVENTO_SESION_CAMBIADA,
    IDENTIDAD_INVITADO,
    identidadCompletaDeLaPestana,
} from '@/Utils/sesionPestanaUtil';

const NOMBRE_CANAL = 'siget-sesion';
const CLAVE_PESTANA = 'siget.sesion-pestana';

const mostrada = ref(null);
const sesion = ref(null);
/** El servidor rechazó una petición axios por sesión cambiada. */
const detectadoPorServidor = ref(false);
const cerrandoSesion = ref(false);
const botonPrincipal = ref(null);

let canal = null;
let visitasEnCurso = 0;
const quitarEscuchas = [];

const hayDesajuste = computed(() => mostrada.value !== null
    && sesion.value !== null
    && mostrada.value.id !== sesion.value.id);

const visible = computed(() => detectadoPorServidor.value || hayDesajuste.value);

/** 'sesion-cerrada' | 'sesion-abierta' | 'otro-usuario' */
const variante = computed(() => {
    if (!hayDesajuste.value) {
        return 'otro-usuario';
    }

    if (sesion.value.id === IDENTIDAD_INVITADO) {
        return 'sesion-cerrada';
    }

    return mostrada.value.id === IDENTIDAD_INVITADO ? 'sesion-abierta' : 'otro-usuario';
});

const nombreSesion = computed(() => (hayDesajuste.value ? sesion.value.nombre : null));
const nombreMostrado = computed(() => mostrada.value?.nombre ?? null);

const titulo = computed(() => ({
    'sesion-cerrada': 'Tu sesión se cerró',
    'sesion-abierta': 'Ya hay una sesión iniciada',
    'otro-usuario': 'La sesión cambió de usuario',
}[variante.value]));

const icono = computed(() => ({
    'sesion-cerrada': 'ri-logout-box-r-line',
    'sesion-abierta': 'ri-user-follow-line',
    'otro-usuario': 'ri-user-shared-line',
}[variante.value]));

const textoBotonPrincipal = computed(() => {
    if (variante.value === 'sesion-cerrada') {
        return 'Ir a iniciar sesión';
    }

    return nombreSesion.value ? `Continuar como ${nombreSesion.value}` : 'Actualizar esta pestaña';
});

function leerPestanaRecordada() {
    try {
        return JSON.parse(window.sessionStorage.getItem(CLAVE_PESTANA) ?? 'null');
    } catch {
        return null;
    }
}

function recordarPestana(identidad) {
    try {
        window.sessionStorage.setItem(CLAVE_PESTANA, JSON.stringify(identidad));
    } catch {
        // Sin sessionStorage (modo privado estricto) sólo se pierde el toast.
    }
}

function escaparHtml(texto) {
    return String(texto).replace(/[&<>"']/g, (caracter) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[caracter]));
}

/**
 * La pestaña se recargó (p.ej. por el 409 del servidor) y la sesión ahora es
 * de otro usuario: explica por qué. Login/logout en esta misma pestaña no
 * pasan por aquí: son visitas de Inertia que ya actualizan lo recordado.
 */
function avisarSiCambioElUsuarioAlRecargar(actual) {
    const anterior = leerPestanaRecordada();

    if (!anterior?.id || actual.id === IDENTIDAD_INVITADO || anterior.id === actual.id) {
        return;
    }

    const nombre = escaparHtml(actual.nombre ?? 'otro usuario');

    showToast(anterior.id === IDENTIDAD_INVITADO
        ? `Ya había una sesión iniciada como <b>${nombre}</b> en este navegador. Para entrar con otra cuenta, primero cierra esa sesión.`
        : `Se inició sesión como <b>${nombre}</b> en otra pestaña. Esta pestaña se actualizó con esa sesión.`,
    'info');
}

/**
 * El servidor acaba de responder: lo que muestra la pestaña ES la sesión real
 * (las acciones y recargas parciales con otra identidad ya se rechazan con 409).
 */
function confirmarSesionDelServidor() {
    const actual = identidadCompletaDeLaPestana();

    if (actual === null) {
        return;
    }

    const cambio = sesion.value?.id !== actual.id;

    mostrada.value = actual;
    sesion.value = actual;
    detectadoPorServidor.value = false;

    if (cambio) {
        recordarPestana(actual);
        canal?.postMessage({ tipo: 'identidad', ...actual });
    }
}

/** Navegación sin visita al servidor (historial): sólo cambia lo mostrado. */
function alNavegar() {
    if (visitasEnCurso === 0) {
        mostrada.value = identidadCompletaDeLaPestana() ?? mostrada.value;
    }
}

function alRecibirMensaje(evento) {
    const datos = evento?.data;

    if (datos?.tipo !== 'identidad' || typeof datos.id !== 'string') {
        return;
    }

    sesion.value = { id: datos.id, nombre: datos.nombre ?? null };
}

function alDetectarEnServidor() {
    detectadoPorServidor.value = true;
}

function actualizarPestana() {
    window.location.assign(route('dashboard'));
}

function cerrarSesion() {
    cerrandoSesion.value = true;
    router.post(route('logout'), {}, {
        onFinish: () => {
            cerrandoSesion.value = false;
        },
    });
}

/** Mantiene el foco dentro del aviso mientras está abierto. */
function retenerFoco(evento) {
    if (!visible.value || evento.key !== 'Tab') {
        return;
    }

    const botones = [...document.querySelectorAll('.sesion-pestana-aviso button:not([disabled])')];

    if (botones.length === 0) {
        return;
    }

    const indice = botones.indexOf(document.activeElement);
    const siguiente = evento.shiftKey
        ? botones[(indice - 1 + botones.length) % botones.length]
        : botones[(indice + 1) % botones.length];

    evento.preventDefault();
    siguiente.focus();
}

watch(visible, async (mostrar) => {
    if (mostrar) {
        await nextTick();
        botonPrincipal.value?.focus();
    }
});

onMounted(() => {
    // Al montar, la página es la que dibujó el servidor (aunque luego Inertia
    // la reemplace por la del historial si se llegó con "Atrás").
    const inicial = identidadCompletaDeLaPestana();

    if (inicial !== null) {
        avisarSiCambioElUsuarioAlRecargar(inicial);
    }

    if (typeof window.BroadcastChannel === 'function') {
        canal = new window.BroadcastChannel(NOMBRE_CANAL);
        canal.addEventListener('message', alRecibirMensaje);
    }

    // También anuncia la sesión al resto de pestañas: una pestaña recién
    // cargada puede ser la primera en enterarse de que la sesión cambió.
    confirmarSesionDelServidor();

    quitarEscuchas.push(
        router.on('start', () => { visitasEnCurso += 1; }),
        router.on('finish', () => { visitasEnCurso = Math.max(0, visitasEnCurso - 1); }),
        router.on('success', confirmarSesionDelServidor),
        router.on('error', confirmarSesionDelServidor),
        router.on('navigate', alNavegar),
    );

    window.addEventListener(EVENTO_SESION_CAMBIADA, alDetectarEnServidor);
    document.addEventListener('keydown', retenerFoco, true);
});

onBeforeUnmount(() => {
    quitarEscuchas.forEach((quitar) => quitar());
    canal?.close();
    canal = null;
    window.removeEventListener(EVENTO_SESION_CAMBIADA, alDetectarEnServidor);
    document.removeEventListener('keydown', retenerFoco, true);
});
</script>

<template>
    <div
        v-if="visible"
        class="sesion-pestana-aviso"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="sesion-pestana-aviso-titulo"
        aria-describedby="sesion-pestana-aviso-texto"
    >
        <div class="sesion-pestana-aviso__tarjeta">
            <span class="sesion-pestana-aviso__icono" :class="`sesion-pestana-aviso__icono--${variante}`" aria-hidden="true">
                <i :class="icono"></i>
            </span>

            <h2 id="sesion-pestana-aviso-titulo" class="sesion-pestana-aviso__titulo">{{ titulo }}</h2>

            <p id="sesion-pestana-aviso-texto" class="sesion-pestana-aviso__texto">
                <template v-if="variante === 'sesion-cerrada'">
                    La sesión<template v-if="nombreMostrado"> de <strong>{{ nombreMostrado }}</strong></template>
                    ya se cerró. Inicia sesión nuevamente para continuar.
                </template>
                <template v-else-if="variante === 'sesion-abierta'">
                    La sesión abierta en este navegador es la de <strong>{{ nombreSesion ?? 'otro usuario' }}</strong>.
                    Para entrar con otra cuenta, primero cierra esa sesión.
                </template>
                <template v-else-if="nombreSesion">
                    La sesión abierta en este navegador ahora es la de <strong>{{ nombreSesion }}</strong>.
                    Esta pestaña todavía muestra la<template v-if="nombreMostrado"> de <strong>{{ nombreMostrado }}</strong></template>
                    <template v-else> anterior</template> y ya no puede guardar cambios.
                </template>
                <template v-else>
                    La sesión abierta en este navegador cambió en otra pestaña, así que esta ya no puede guardar cambios.
                </template>
            </p>

            <div class="sesion-pestana-aviso__acciones">
                <button
                    v-if="variante !== 'sesion-cerrada'"
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="cerrandoSesion"
                    @click="cerrarSesion"
                >
                    <i class="ri-logout-box-r-line me-1" aria-hidden="true"></i>Cerrar sesión
                </button>
                <button
                    ref="botonPrincipal"
                    type="button"
                    class="btn btn-primary"
                    :disabled="cerrandoSesion"
                    @click="actualizarPestana"
                >
                    <i class="ri-refresh-line me-1" aria-hidden="true"></i>{{ textoBotonPrincipal }}
                </button>
            </div>
        </div>
    </div>
</template>
