/**
 * Utilidades de presentación de la bitácora de auditoría, compartidas por
 * Pages/Auditoria/Index.vue y Components/AuditoriaDetalleModal.vue.
 */

// Las clases se escriben completas (no interpoladas) porque son clases del
// tema y así quedan visibles para cualquier análisis estático del bundle.
const EVENTO_CLASES = {
    created: { badge: 'bg-success-transparent text-success', solido: 'bg-success', texto: 'text-success' },
    updated: { badge: 'bg-info-transparent text-info', solido: 'bg-info', texto: 'text-info' },
    deleted: { badge: 'bg-danger-transparent text-danger', solido: 'bg-danger', texto: 'text-danger' },
    restored: { badge: 'bg-warning-transparent text-warning', solido: 'bg-warning', texto: 'text-warning' },
}

const EVENTO_POR_DEFECTO = {
    badge: 'bg-secondary-transparent text-secondary',
    solido: 'bg-secondary',
    texto: 'text-secondary',
}

/**
 * @param {string} clave  created | updated | deleted | restored
 * @param {'badge'|'solido'|'texto'} variante
 */
export const claseEvento = (clave, variante = 'badge') =>
    (EVENTO_CLASES[clave] ?? EVENTO_POR_DEFECTO)[variante]

const COLORES_AVATAR = [
    'bg-primary-transparent text-primary',
    'bg-success-transparent text-success',
    'bg-warning-transparent text-warning',
    'bg-info-transparent text-info',
    'bg-danger-transparent text-danger',
]

/** Mismo criterio que Pages/Usuarios/Index.vue: color estable por nombre. */
export const avatarColor = (nombre) =>
    COLORES_AVATAR[(nombre?.charCodeAt(0) ?? 0) % COLORES_AVATAR.length]

export const iniciales = (nombre) =>
    (nombre ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte.charAt(0).toUpperCase())
        .join('') || '?'
