import { usePage } from '@inertiajs/vue3';

/**
 * Control de sesión entre pestañas.
 *
 * Todas las pestañas comparten la misma cookie de sesión: si en una se entra
 * con otro usuario, las demás siguen mostrando la interfaz del anterior pero
 * envían todo con la sesión nueva. Cada petición al propio sistema lleva la
 * identidad con la que se dibujó la pestaña (cabecera X-Sesion-Usuario) y el
 * middleware App\Http\Middleware\VerificarSesionDePestana bloquea las acciones
 * si no coincide con la sesión real. SesionPestanaAviso.vue avisa al usuario.
 */

/** Debe coincidir con VerificarSesionDePestana::CABECERA. */
export const CABECERA_SESION = 'X-Sesion-Usuario';

/** Debe coincidir con VerificarSesionDePestana::INVITADO. */
export const IDENTIDAD_INVITADO = 'invitado';

/** Debe coincidir con VerificarSesionDePestana::CABECERA_RESPUESTA. */
const CABECERA_SESION_CAMBIADA = 'x-sesion-cambiada';

/** Evento de window que dispara una respuesta 409 de sesión cambiada (axios). */
export const EVENTO_SESION_CAMBIADA = 'siget:sesion-cambiada';

const page = usePage();

/**
 * Identidad con la que se dibujó la pestaña: el id del usuario, "invitado" si
 * se dibujó sin sesión, o null si Inertia todavía no cargó la página.
 */
export function identidadDeLaPestana() {
    const props = page?.props;

    if (!props) {
        return null;
    }

    const id = props.auth?.user?.id;

    return id === undefined || id === null ? IDENTIDAD_INVITADO : String(id);
}

/**
 * Identidad y nombre visible de la página que muestra la pestaña:
 * { id, nombre } (nombre null si se dibujó sin sesión), o null si no cargó.
 */
export function identidadCompletaDeLaPestana() {
    const id = identidadDeLaPestana();

    if (id === null) {
        return null;
    }

    const usuario = page.props.auth?.user;

    return { id, nombre: usuario ? (usuario.persona?.nombre_completo || usuario.email || null) : null };
}

/**
 * Sólo se marca lo que va al propio sistema: una cabecera propia en una
 * petición a otro dominio obligaría a un preflight CORS y podría romperla.
 */
function esDelMismoOrigen(config) {
    try {
        const url = new URL(config.url ?? '', config.baseURL ?? window.location.href);

        return url.origin === window.location.origin;
    } catch {
        return false;
    }
}

/**
 * Instala en axios (la misma instancia que usa Inertia para sus visitas) la
 * cabecera de identidad y el aviso cuando el servidor detecta el cambio en
 * una petición axios directa (las de Inertia se recargan solas por 409).
 */
export function instalarControlDeSesion(axios) {
    axios.interceptors.request.use((config) => {
        const identidad = identidadDeLaPestana();

        if (identidad !== null && esDelMismoOrigen(config)) {
            config.headers.set(CABECERA_SESION, identidad);
        }

        return config;
    });

    axios.interceptors.response.use(undefined, (error) => {
        const respuesta = error?.response;

        if (respuesta?.status === 409 && respuesta.headers?.[CABECERA_SESION_CAMBIADA]) {
            window.dispatchEvent(new CustomEvent(EVENTO_SESION_CAMBIADA));
        }

        return Promise.reject(error);
    });
}
