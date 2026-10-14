/**
 * Nombre legible de un rol (los no listados, p.ej. uno creado a futuro desde
 * el módulo de Roles, se muestran con su nombre técnico tal cual).
 */
export const etiquetaRol = (rol) => ({
    'super-admin': 'Super Administrador',
    administrador: 'Administrador',
    conductor: 'Conductor',
    'jefe-area': 'Jefe de Área',
    'tecnico-mantenimiento': 'Técnico de Mantenimiento',
}[rol] ?? rol)

/**
 * Clase de badge por rol. Conductor y Jefe de Área conservan los colores que
 * ya usa el listado de Personas (tipoBadge en Personas/Index.vue).
 */
export const claseRol = (rol) => ({
    'super-admin': 'bg-danger-transparent text-danger',
    administrador: 'bg-success-transparent text-success',
    conductor: 'bg-primary-transparent text-primary',
    'jefe-area': 'bg-info-transparent text-info',
    'tecnico-mantenimiento': 'bg-warning-transparent text-warning',
}[rol] ?? 'bg-secondary-transparent text-secondary')
