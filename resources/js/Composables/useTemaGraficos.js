import { onBeforeUnmount, onMounted, ref } from 'vue'
import { paletaGraficos } from '@/Utils/chartUtil'

/**
 * Paleta de colores del tema para gráficos de ApexCharts, que se re-resuelve
 * automáticamente al alternar entre modo claro y oscuro (el layout cambia
 * `data-theme-mode` / `class` en <html>).
 *
 * Uso:
 *   const { paleta } = useTemaGraficos()
 *   const opciones = computed(() => ({ colors: paleta.value, ... }))
 */
export function useTemaGraficos() {
    const paleta = ref(paletaGraficos())

    let observador = null

    const refrescar = () => {
        paleta.value = paletaGraficos()
    }

    onMounted(() => {
        refrescar()

        if (window.MutationObserver) {
            observador = new MutationObserver(refrescar)
            observador.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-theme-mode', 'class'],
            })
        }
    })

    onBeforeUnmount(() => observador?.disconnect())

    return { paleta, refrescarPaletaGraficos: refrescar }
}
