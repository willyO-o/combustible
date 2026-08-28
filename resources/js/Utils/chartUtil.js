/**
 * Utilidades compartidas para los gráficos de ApexCharts (dashboard y reportes).
 */

const NEGRO_LEGIBLE = '#373d3f';
const GRIS_SUBTITULO = '#6b7280';

/**
 * Resuelve una variable CSS del tema a un color usable por ApexCharts
 * (ApexCharts no entiende `var(--x)`). Las variables `*-rgb` del tema vienen
 * como "r, g, b" y se convierten a `rgb(...)`.
 *
 * @param {string} nombre      Nombre de la variable, ej. '--primary-rgb'.
 * @param {string} [fallback]  Valor a devolver si la variable no está definida.
 */
export function colorTema(nombre, fallback = undefined) {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return fallback;
    }

    const valor = getComputedStyle(document.documentElement).getPropertyValue(nombre).trim();

    if (! valor) {
        return fallback;
    }

    return /^\d+\s*,\s*\d+\s*,\s*\d+$/.test(valor) ? `rgb(${valor})` : valor;
}

/**
 * Paleta categórica del tema (primary, success, warning, info, danger,
 * secondary) para gráficos de varias series/categorías. Se re-resuelve cada
 * vez que se llama, así sigue al modo claro / oscuro.
 *
 * @returns {string[]}
 */
export function paletaGraficos() {
    return [
        colorTema('--primary-rgb', '#3b82f6'),
        colorTema('--success-rgb', '#22c55e'),
        colorTema('--warning-rgb', '#f59e0b'),
        colorTema('--info-rgb', '#06b6d4'),
        colorTema('--danger-rgb', '#ef4444'),
        colorTema('--secondary-rgb', '#64748b'),
    ].filter(Boolean);
}

// Apariencia "para impresión": fondo blanco y textos oscuros, sin importar si
// la app está en modo claro u oscuro. Se aplica sólo durante la exportación y
// se revierte inmediatamente después.
const LOOK_EXPORT = {
    chart: { background: '#ffffff', foreColor: NEGRO_LEGIBLE },
    legend: { labels: { colors: NEGRO_LEGIBLE } },
    tooltip: { theme: 'light' },
    grid: { borderColor: '#e0e0e0' },
};

function descargarUri(uri, nombreArchivo) {
    const enlace = document.createElement('a');
    enlace.href = uri;
    enlace.download = nombreArchivo;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
}

// Snapshot de las claves que toca el look de exportación, para devolver el
// gráfico a como estaba (siguiendo el tema actual) después de exportar.
function lookActual(chart) {
    const config = chart?.w?.config ?? {};

    return {
        chart: {
            background: config.chart?.background ?? 'transparent',
            foreColor: config.chart?.foreColor ?? NEGRO_LEGIBLE,
        },
        legend: { labels: { colors: config.legend?.labels?.colors } },
        tooltip: { theme: config.tooltip?.theme ?? 'light' },
        grid: { borderColor: config.grid?.borderColor },
        // Los gráficos no llevan título/subtítulo en pantalla (ya está en el
        // card-header): se restauran a "sin texto" tras exportar.
        title: { text: config.title?.text || '' },
        subtitle: { text: config.subtitle?.text || '' },
    };
}

const esperarPintado = () => new Promise((resolve) => requestAnimationFrame(() => resolve()));

/**
 * Resuelve la instancia real de ApexCharts a partir de lo que reciba: el ref
 * del componente `<Apexchart>` de vue3-apexcharts (expone `.chart`) o la propia
 * instancia.
 */
function resolverInstancia(graficoOInstancia) {
    if (! graficoOInstancia) {
        return null;
    }

    // Ref del componente <Apexchart> de vue3-apexcharts (expone `.chart`).
    if (graficoOInstancia.chart?.w) {
        return graficoOInstancia.chart;
    }

    // Instancia de ApexCharts directamente.
    return graficoOInstancia.w ? graficoOInstancia : null;
}

/**
 * Exporta el gráfico como PNG con fondo blanco, etiquetas oscuras (legibles
 * también en modo oscuro) y un título/subtítulo incrustados para que la imagen
 * se entienda por sí sola. Luego devuelve el gráfico a su apariencia normal.
 *
 * @param {object} grafico        Ref del componente `<Apexchart>` (o instancia de ApexCharts).
 * @param {string} nombreArchivo  Nombre base del archivo, sin extensión.
 * @param {string} [titulo]       Título incrustado en la imagen (ej. "Horas Trabajadas por Día").
 * @param {string} [subtitulo]    Subtítulo / contexto (ej. "Dashboard" o "Reporte de Rendimiento").
 */
export async function descargarGraficoPng(grafico, nombreArchivo = 'grafico', titulo = '', subtitulo = '') {
    const chart = resolverInstancia(grafico);

    if (! chart || typeof chart.updateOptions !== 'function' || typeof chart.dataURI !== 'function') {
        return;
    }

    const previo = lookActual(chart);

    const lookExport = {
        ...LOOK_EXPORT,
        title: {
            text: titulo || undefined,
            align: 'center',
            margin: subtitulo ? 24 : 12,
            style: { fontSize: '15px', fontWeight: 700, color: NEGRO_LEGIBLE },
        },
        subtitle: {
            text: subtitulo || undefined,
            align: 'center',
            offsetY: 22,
            style: { fontSize: '12px', color: GRIS_SUBTITULO },
        },
    };

    try {
        await chart.updateOptions(lookExport, true, false, false);
        await esperarPintado();

        const salida = await chart.dataURI();

        if (salida?.blob) {
            const url = URL.createObjectURL(salida.blob);
            descargarUri(url, `${nombreArchivo}.png`);
            URL.revokeObjectURL(url);
        } else if (salida?.imgURI) {
            descargarUri(salida.imgURI, `${nombreArchivo}.png`);
        }
    } catch (error) {
        console.error('No se pudo exportar el gráfico:', error);
    } finally {
        // Siempre se restaura la apariencia original, aunque falle la exportación.
        await chart.updateOptions(previo, true, false, false);
    }
}
