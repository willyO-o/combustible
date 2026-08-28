<script setup>
import Maindashboard from '@/Layouts/Maindashboard.vue';
defineOptions({ layout: Maindashboard })
import CardAnalitic from '@/Components/CardAnalitic.vue';
import BotonDescargarGrafico from '@/Components/BotonDescargarGrafico.vue';
import { colorTema } from '@/Utils/chartUtil';

import { Head, usePage } from '@inertiajs/vue3';

import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    // Cada métrica llega sólo si el usuario tiene el permiso del widget
    // correspondiente (ver DashboardController); si no, viene null / vacío.
    conductores: { type: Object, default: null },
    vales: { type: Object, default: null },
    cargas: { type: Object, default: null },
    vehiculos: { type: Object, default: null },
    errors: { type: Object, default: () => ({}) },
    reporteMes: { type: Array, default: () => [] },
    // { labels: string[], series: number[] } — órdenes de trabajo por estado
    // (sólo para técnico de mantenimiento / gestión).
    ordenesPorEstado: { type: Object, default: null },
    // { dia: {labels,series}, semana: {labels,series} } — horas trabajadas
    // (sólo para el rol conductor / gestión).
    horasTrabajadas: { type: Object, default: null },
});

const page = usePage();

const permisos = computed(() => {
    const lista = page.props?.auth?.permissions;
    return Array.isArray(lista) ? lista : [];
});
const puede = (permiso) => permisos.value.includes(permiso);

// Todo valor de un gráfico se muestra con máximo 2 decimales.
const a2Decimales = (valor) => Math.round((Number(valor) || 0) * 100) / 100;

const nombreUsuario = computed(() => {
    const user = page.props?.auth?.user ?? {};
    const persona = user.persona;

    if (persona?.nombres) {
        return [persona.nombres, persona.paterno].filter(Boolean).join(' ');
    }

    return user.name ?? '';
});

// Sólo se arma la tarjeta cuya métrica llegó desde el backend; el orden es el
// mismo que tenía el dashboard.
const infoCards = computed(() => {
    const cards = [];

    if (props.cargas) {
        cards.push({
            permiso: 'dashboard.tarjeta-cargas.ver',
            title: 'Cargas de Combustible',
            avatarClass: 'flex-shrink-0',
            count: props.cargas.total,
            percent: props.cargas.porcentaje,
            priceColor: 'success',
            svgIcon: `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="currentColor">
                    <rect x="10" y="10" width="24" height="36" rx="2"/>
                    <rect x="14" y="16" width="16" height="10" fill="white"/>
                    <path d="M34 18h8v8c0 2 2 4 4 4s4-2 4-4v-6" fill="none" stroke="currentColor" stroke-width="3"/>
                    <path d="M48 42c0-4 6-10 6-10s6 6 6 10a6 6 0 1 1-12 0z"/>
                </svg>
            `,
            percentColor: 'bg-success-transparent badge fs-12',
        });
    }

    if (props.vales) {
        cards.push({
            permiso: 'dashboard.tarjeta-vales.ver',
            title: 'Vales utilizados',
            avatarClass: 'avatar-md flex-shrink-0',
            count: props.vales.total,
            percent: props.vales.porcentaje,
            priceColor: 'secondary',
            svgIcon: `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"></rect><path d="M128,24A104,104,0,1,0,232,128,104.13,104.13,0,0,0,128,24Zm40,112H136v32a8,8,0,0,1-16,0V136H88a8,8,0,0,1,0-16h32V88a8,8,0,0,1,16,0v32h32a8,8,0,0,1,0,16Z"></path></svg>
            `,
            percentColor: 'bg-success-transparent badge fs-12',
        });
    }

    if (props.vehiculos) {
        cards.push({
            permiso: 'dashboard.tarjeta-vehiculos.ver',
            title: 'Total Vehículos',
            avatarClass: 'avatar-md flex-shrink-0',
            count: props.vehiculos.total,
            percent: props.vehiculos.porcentaje,
            priceColor: 'warning',
            svgIcon: `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="18" width="34" height="20" rx="2"/>
                    <path d="M36 24h12l8 8v6H36z"/>
                    <rect x="42" y="26" width="8" height="6"/>
                    <line x1="2" y1="38" x2="56" y2="38"/>
                    <circle cx="14" cy="44" r="6"/>
                    <circle cx="46" cy="44" r="6"/>
                </svg>
            `,
            percentColor: 'bg-danger-transparent badge fs-12',
        });
    }

    if (props.conductores) {
        cards.push({
            permiso: 'dashboard.tarjeta-conductores.ver',
            title: 'Total Conductores',
            avatarClass: 'avatar-md flex-shrink-0',
            count: props.conductores.total,
            percent: props.conductores.porcentaje,
            priceColor: 'primary',
            svgIcon: `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"></rect><path d="M208,32H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32ZM192,184H64a8,8,0,0,1,0-16H192a8,8,0,0,1,0,16Zm0-48H64a8,8,0,0,1,0-16H192a8,8,0,0,1,0,16Zm0-48H64a8,8,0,0,1,0-16H192a8,8,0,0,1,0,16Z"></path></svg>
            `,
            percentColor: 'bg-success-transparent badge fs-12',
        });
    }

    return cards;
});

const mostrarGrafico = computed(() => puede('dashboard.grafico-combustible.ver') && props.reporteMes.length > 0);

const mostrarOrdenes = computed(() =>
    puede('dashboard.grafico-ordenes.ver') && (props.ordenesPorEstado?.series?.length ?? 0) > 0
);

const mostrarHoras = computed(() =>
    puede('dashboard.grafico-horas.ver') && (props.horasTrabajadas?.dia?.series?.length ?? 0) > 0
);

// El dashboard no muestra nada cuando el usuario no tiene ningún widget.
const sinWidgets = computed(() =>
    infoCards.value.length === 0 && !mostrarGrafico.value && !mostrarOrdenes.value && !mostrarHoras.value
);

// Gráfico de torta: órdenes de trabajo por estado. Un color del tema por estado
// (colorTema() resuelve la variable CSS -> color usable por ApexCharts).
const paletaEstados = () => [
    colorTema('--primary-rgb'),
    colorTema('--warning-rgb'),
    colorTema('--success-rgb'),
    colorTema('--info-rgb'),
    colorTema('--danger-rgb'),
    colorTema('--secondary-rgb'),
].filter(Boolean);

const ordenesChart = ref(null);
const horasChart = ref(null);
const audienceChart = ref(null);

const ordenesOptions = ref({
    chart: { type: 'donut', height: 340, toolbar: { show: false } },
    labels: props.ordenesPorEstado?.labels ?? [],
    colors: paletaEstados(),
    legend: { position: 'bottom', labels: { colors: colorTema('--default-text-color') } },
    dataLabels: { enabled: true },
    stroke: { width: 2, colors: [colorTema('--custom-white')] },
    plotOptions: { pie: { donut: { size: '62%' } } },
    tooltip: { fillSeriesColor: false },
});

const ordenesSeries = computed(() => (props.ordenesPorEstado?.series ?? []).map(a2Decimales));

// Gráfico de barras: horas trabajadas por día / semana (rol conductor).
const horasVista = ref('dia');

const horasOptions = ref({
    chart: { type: 'bar', height: 330, toolbar: { show: false } },
    plotOptions: { bar: { columnWidth: '45%', borderRadius: 3 } },
    colors: [colorTema('--primary-rgb')],
    dataLabels: { enabled: false },
    grid: { borderColor: colorTema('--default-border'), strokeDashArray: 3 },
    legend: { show: false },
    xaxis: { categories: [], axisBorder: { color: colorTema('--default-border') } },
    yaxis: { title: { text: 'Horas' }, labels: { formatter: (valor) => a2Decimales(valor) } },
    tooltip: { y: { formatter: (valor) => `${a2Decimales(valor)} h` } },
});

const horasSeries = ref([{ name: 'Horas trabajadas', data: [] }]);

const cambiarVistaHoras = (vista) => {
    horasVista.value = vista;
    const datos = props.horasTrabajadas?.[vista] ?? { labels: [], series: [] };

    horasSeries.value = [{ name: 'Horas trabajadas', data: datos.series.map(a2Decimales) }];
    horasOptions.value = {
        ...horasOptions.value,
        xaxis: { ...horasOptions.value.xaxis, categories: datos.labels },
    };
};

// Re-resuelve los colores del tema al cambiar entre modo claro / oscuro
// (el layout alterna data-theme-mode / class en <html>).
let observadorTema = null;

const sincronizarColoresTema = () => {
    ordenesOptions.value = {
        ...ordenesOptions.value,
        colors: paletaEstados(),
        legend: { ...ordenesOptions.value.legend, labels: { colors: colorTema('--default-text-color') } },
        stroke: { ...ordenesOptions.value.stroke, colors: [colorTema('--custom-white')] },
    };
    horasOptions.value = {
        ...horasOptions.value,
        colors: [colorTema('--primary-rgb')],
        grid: { ...horasOptions.value.grid, borderColor: colorTema('--default-border') },
        xaxis: { ...horasOptions.value.xaxis, axisBorder: { color: colorTema('--default-border') } },
    };
};

const audienceoptions = ref({
    chart: {
        toolbar: { show: false },
        type: 'line',
        height: 330,
    },
    grid: {
        borderColor: '#f1f1f1',
        strokeDashArray: 3
    },
    colors: [],
    labels: [],
    dataLabels: {
        enabled: false
    },
    stroke: {
        width: [1, 1.1],
        curve: ['straight', 'smooth'],
        dashArray: [0, 2]
    },
    legend: {
        show: false,
    },
    xaxis: {
        axisBorder: {
            color: '#e9e9e9',
        },
    },
    plotOptions: {
        bar: {
            columnWidth: "30%",
            borderRadius: 2
        }
    },
})

const audienceseries = ref([{
    name: 'Total Litros',
    type: 'column',
    data: []
}]);

const cambiarDatos = (tipo) => {
    if (tipo === 'litros') {
        audienceseries.value[0].data = props.reporteMes.map(item => a2Decimales(item.total_litros));
        audienceseries.value[0].name = 'Total Litros';
        audienceoptions.value = { ...audienceoptions.value, colors: [colorTema('--primary-rgb')] };

    } else if (tipo === 'costo') {
        audienceseries.value[0].data = props.reporteMes.map(item => a2Decimales(item.total_precio));
        audienceseries.value[0].name = 'Total Costo Bs.';
        audienceoptions.value = { ...audienceoptions.value, colors: [colorTema('--success-rgb')] };
    }
}

onMounted(() => {
    // Los colores del tema ya están aplicados en el DOM: re-resuélvelos.
    sincronizarColoresTema();

    if (mostrarHoras.value) {
        cambiarVistaHoras('dia');
    }

    if ((mostrarOrdenes.value || mostrarHoras.value) && window.MutationObserver) {
        observadorTema = new MutationObserver(sincronizarColoresTema);
        observadorTema.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-theme-mode', 'class', 'data-menu-styles', 'data-header-styles'],
        });
    }

    if (!mostrarGrafico.value) {
        return;
    }
    audienceoptions.value.labels = props.reporteMes.map(item => item.mes);
    cambiarDatos('litros');
})

onBeforeUnmount(() => {
    observadorTema?.disconnect();
})

</script>

<template>

    <Head title="Dashboard" />

        <div class="text-center my-4">
            <h2 class="fs-3 fw-semibold mb-1">
                Bienvenido<span v-if="nombreUsuario">, {{ nombreUsuario }}</span>
            </h2>
            <p class="text-muted mb-0">Este es el resumen de tu operación</p>
        </div>

        <div class="row row-cols-xxl-4 row-cols-md-3 row-cols-1">
            <div class="col" v-for="card in infoCards" :key="card.permiso" v-can="card.permiso">
                <CardAnalitic :listCard="true" :cardClass="`dashboard-main-card card ${card.priceColor}`" :list="card"
                    :NoCountUp="true" />
            </div>
        </div>

        <div class="row" v-if="mostrarGrafico">
            <div class="col-md-12" v-can="'dashboard.grafico-combustible.ver'">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            Gastos de Combustible por Mes
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
                                <input @click="cambiarDatos('litros')" type="radio" class="btn-check" name="filtro" id="btnradio1" checked>
                                <label class="btn btn-outline-primary" for="btnradio1">Litros</label>

                                <input @click="cambiarDatos('costo')" type="radio" class="btn-check" name="filtro" id="btnradio2">
                                <label class="btn btn-outline-primary" for="btnradio2">Costo</label>
                            </div>
                            <BotonDescargarGrafico :grafico="audienceChart" nombre="gastos-combustible-por-mes"
                                titulo="Gastos de Combustible por Mes" subtitulo="Dashboard" />
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="audience-metrics">
                            <Apexchart ref="audienceChart" height="370px" type="line" :options="audienceoptions" :series="audienceseries" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" v-if="mostrarOrdenes">
            <div class="col-xxl-5 col-lg-6" v-can="'dashboard.grafico-ordenes.ver'">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            Órdenes de Trabajo por Estado
                        </div>
                        <BotonDescargarGrafico :grafico="ordenesChart" nombre="ordenes-por-estado"
                            titulo="Órdenes de Trabajo por Estado" subtitulo="Dashboard" />
                    </div>
                    <div class="card-body d-flex justify-content-center">
                        <div style="width: 100%; max-width: 360px;">
                            <Apexchart ref="ordenesChart" type="donut" height="340" :options="ordenesOptions" :series="ordenesSeries" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" v-if="mostrarHoras">
            <div class="col-md-12" v-can="'dashboard.grafico-horas.ver'">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            Horas Trabajadas
                        </div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="btn-group" role="group" aria-label="Vista de horas trabajadas">
                                <input @click="cambiarVistaHoras('dia')" type="radio" class="btn-check" name="vistaHoras" id="vistaHorasDia" checked>
                                <label class="btn btn-outline-primary" for="vistaHorasDia">Por día</label>

                                <input @click="cambiarVistaHoras('semana')" type="radio" class="btn-check" name="vistaHoras" id="vistaHorasSemana">
                                <label class="btn btn-outline-primary" for="vistaHorasSemana">Por semana</label>
                            </div>
                            <BotonDescargarGrafico :grafico="horasChart" nombre="horas-trabajadas"
                                :titulo="horasVista === 'semana' ? 'Horas Trabajadas por Semana' : 'Horas Trabajadas por Día'"
                                subtitulo="Dashboard" />
                        </div>
                    </div>
                    <div class="card-body">
                        <Apexchart ref="horasChart" type="bar" height="330" :options="horasOptions" :series="horasSeries" />
                    </div>
                </div>
            </div>
        </div>

        <div class="row" v-if="sinWidgets">
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="ri-bar-chart-2-line fs-1 d-block mb-2"></i>
                        Aún no hay indicadores disponibles para tu perfil.
                    </div>
                </div>
            </div>
        </div>

</template>
