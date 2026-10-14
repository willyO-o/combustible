/**
 * Nombre legible de un rol (los no listados, p.ej. uno creado a futuro desde
 * el módulo de Roles, se muestran con su nombre técnico tal cual).
 */
export const etiquetaRol = (rol) => ({
    administrador: 'Administrador',
    conductor: 'Conductor',
    'jefe-area': 'Jefe de Área',
    'tecnico-mantenimiento': 'Técnico de Mantenimiento',
}[rol] ?? rol)
