/**
 * Identidad del sistema. El acrónimo (APP_NAME) y el nombre completo
 * (APP_LONG_NAME) vienen de las variables VITE_* del .env; los fallbacks
 * mantienen la app funcionando si no están definidas en el build.
 *
 * Backend equivalente: config('app.name') y config('app.long_name').
 */
export const APP_NAME = import.meta.env.VITE_APP_NAME || 'SIGET'

export const APP_LONG_NAME =
    import.meta.env.VITE_APP_LONG_NAME || 'Sistema Integral de Gestión de Maquinaria y Operaciones'
