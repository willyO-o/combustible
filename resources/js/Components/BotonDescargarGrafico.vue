<script setup>
/**
 * Botón "descargar" para un gráfico de ApexCharts. Exporta un PNG con fondo
 * blanco, etiquetas oscuras (legible aunque la app esté en modo oscuro) y el
 * título/subtítulo incrustados en la imagen, vía @/Utils/chartUtil.
 *
 * Se coloca en el card-header, junto al título del gráfico.
 *
 * Uso:
 *   <Apexchart ref="miGrafico" ... />
 *   <BotonDescargarGrafico :grafico="miGrafico" nombre="horas-trabajadas"
 *       titulo="Horas Trabajadas por Día" subtitulo="Dashboard" />
 */
import { ref } from 'vue'
import { descargarGraficoPng } from '@/Utils/chartUtil'

const props = defineProps({
    // Ref del componente <Apexchart> (o instancia de ApexCharts).
    grafico: { type: Object, default: null },
    // Nombre base del archivo descargado (sin extensión).
    nombre: { type: String, default: 'grafico' },
    // Título incrustado en la imagen descargada.
    titulo: { type: String, default: '' },
    // Contexto/subtítulo incrustado (módulo al que pertenece el gráfico).
    subtitulo: { type: String, default: '' },
})

const descargando = ref(false)

const descargar = async () => {
    if (descargando.value) {
        return
    }

    descargando.value = true

    try {
        await descargarGraficoPng(props.grafico, props.nombre, props.titulo, props.subtitulo)
    } finally {
        descargando.value = false
    }
}
</script>

<template>
    <button type="button" class="btn btn-sm btn-icon btn-light" title="Descargar gráfico (PNG)"
        :disabled="descargando || !grafico" @click="descargar">
        <i class="ri-download-2-line"></i>
    </button>
</template>
