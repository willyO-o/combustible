import { onBeforeUnmount } from 'vue'
import { Modal } from 'bootstrap'

/**
 * Envuelve una instancia de Modal de Bootstrap para garantizar que se libera
 * si el componente que la abrió se destruye, incluso a mitad de la
 * animación de cierre.
 *
 * Por qué hace falta: Bootstrap inyecta el backdrop del modal directamente
 * en <body>, por fuera del árbol que gestiona Vue, y sólo lo retira cuando
 * termina la transición de cierre (evento `hidden.bs.modal`). Inertia crea
 * una instancia nueva del componente de página en cada visita —incluida una
 * redirección de vuelta a la "misma" página tras guardar un formulario
 * dentro de un modal, salvo que se use `preserveState: true`—, así que la
 * instancia anterior (con el modal recién cerrándose) se destruye antes de
 * que Bootstrap termine de limpiar el backdrop. Eso deja un backdrop
 * "fantasma" que bloquea toda la interacción hasta recargar la página.
 *
 * Solución: además de las llamadas normales a mostrar()/ocultar(), se llama
 * a dispose() —síncrono, retira el backdrop de inmediato sin esperar la
 * transición— justo antes de que el componente se desmonte.
 */
export function useBootstrapModal() {
    let instance = null

    function obtener(el) {
        if (!instance) {
            instance = new Modal(el)
        }
        return instance
    }

    function mostrar(el) {
        obtener(el).show()
    }

    function ocultar() {
        instance?.hide()
    }

    onBeforeUnmount(() => {
        instance?.dispose()
    })

    return { obtener, mostrar, ocultar }
}
