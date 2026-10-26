/**
 * Espera (ms) desde la última tecla antes de aplicar un filtro o una búsqueda
 * (listados, reportes y buscadores remotos como SearchSelect). Única fuente
 * para todo el sistema: subirla evita una consulta por cada letra mientras
 * el usuario todavía está escribiendo.
 */
export const ESPERA_FILTROS_MS = 700
