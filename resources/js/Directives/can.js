/**
 * Controla la visibilidad de un elemento según los permisos compartidos por Inertia.
 *
 * Uso:
 *   v-can="'vales.crear'"                         // Requiere un permiso.
 *   v-can="['vales.crear', 'vales.editar']"       // Requiere cualquiera (OR).
 *   v-can.all="['vales.crear', 'vales.editar']"   // Requiere todos (AND).
 *   Los usuarios con el rol "super-admin" siempre tienen acceso, salvo a los
 *   PERMISOS_EXCLUSIVOS (ver abajo).
 *
 * La directiva solo mejora la interfaz. Las rutas y acciones del backend deben
 * seguir protegidas mediante middleware o Policies de Laravel.
 */
const can = {
    mounted(element, binding) {
        updateVisibility(element, binding);
    },

    updated(element, binding) {
        updateVisibility(element, binding);
    },
};

const page = usePage();

/**
 * Permisos que el super-admin NO recibe por defecto: no son de Roles, el
 * servidor los comparte sólo a quien corresponde (p.ej. el encargado de
 * regularización del .env, ver SoloEncargadoDeRegularizacion::PERMISO_INTERFAZ).
 */
const PERMISOS_EXCLUSIVOS = ['operacion-diaria.regularizacion.gestionar'];

/**
 * Misma regla que v-can, para usarla en el script (p.ej. elegir a dónde
 * lleva un enlace según el permiso). Cualquiera de los permisos basta.
 */
export function tienePermiso(value) {
    return estaAutorizado(normalizePermissions(value), false);
}

/**
 * El super-admin tiene todo excepto los permisos exclusivos, que se
 * comprueban siempre contra los permisos compartidos.
 */
function estaAutorizado(requiredPermissions, requiresAll) {
    const currentPermissions = getCurrentPermissions();
    const isSuperAdmin = isCurrentUserSuperAdmin();
    const tiene = (permission) => currentPermissions.includes(permission)
        || (isSuperAdmin && !PERMISOS_EXCLUSIVOS.includes(permission));

    if (requiredPermissions.length === 0) {
        // El super-admin no requiere permisos individuales, incluso cuando el
        // elemento de navegación todavía no declara una propiedad permission.
        return isSuperAdmin;
    }

    return requiresAll ? requiredPermissions.every(tiene) : requiredPermissions.some(tiene);
}

function isCurrentUserSuperAdmin() {
    const auth = page.props?.auth ?? {};

    return auth.is_super_admin === true
        || (Array.isArray(auth.roles) && auth.roles.includes('super-admin'));
}

function updateVisibility(element, binding) {
    // Un valor vacío o inválido nunca concede acceso por accidente (salvo al
    // super-admin, ver estaAutorizado()).
    element.hidden = !estaAutorizado(normalizePermissions(binding.value), binding.modifiers.all === true);
}

function normalizePermissions(value) {
    const permissions = Array.isArray(value) ? value : [value];

    return permissions.filter(
        (permission) => typeof permission === 'string' && permission.trim().length > 0,
    );
}

function getCurrentPermissions() {
    const permissions = page?.props?.auth?.permissions;

    return Array.isArray(permissions) ? permissions : [];
}

export default can;
import { usePage } from '@inertiajs/vue3';
