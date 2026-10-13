import { usePage } from '@inertiajs/vue3'

/**
 * URL pública completa de un archivo subido al disco "public" de Laravel
 * (fotos, evidencias, respaldos, logo), a partir de su ruta relativa tal como
 * se guarda en la base (ej. 'vehiculos/abc.jpg').
 *
 * Usa la base `storage_url` que comparte HandleInertiaRequests (host y
 * subcarpeta reales del servidor). Un "/storage/..." fijo apunta siempre a la
 * raíz del dominio y deja de funcionar si el sistema se publica en una
 * subcarpeta. Devuelve null sin ruta, y respeta una URL que ya sea absoluta.
 *
 * @param {string|null|undefined} ruta
 * @returns {string|null}
 */
export function storageUrl(ruta) {
    if (!ruta) return null

    const texto = String(ruta)
    if (/^(https?:)?\/\//i.test(texto) || texto.startsWith('data:') || texto.startsWith('blob:')) {
        return texto
    }

    const base = usePage().props?.storage_url || '/storage'

    return `${base}/${texto.replace(/^\/+/, '').replace(/^storage\//, '')}`
}
