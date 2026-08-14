<script setup>
// import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Maindashboard from '@/Layouts/Maindashboard.vue';
defineOptions({ layout: Maindashboard })
import CardAnalitic from '@/Components/CardAnalitic.vue';

import { Head } from '@inertiajs/vue3';

import { onMounted, ref } from 'vue';

const props = defineProps({
    conductores: Object,
    vales: Object,
    cargas: Object,
    vehiculos: Object,
    errors: Object,
    reporteMes: Array,
});

const infoCards = ref([

    {
        title: "Cargas de Combustible",
        avatarClass: "flex-shrink-0",
        count: props.cargas.total,
        percent: props.cargas.porcentaje,
        priceColor: "success",
        svgIcon: `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="currentColor">
                <rect x="10" y="10" width="24" height="36" rx="2"/>
                <rect x="14" y="16" width="16" height="10" fill="white"/>
                <path d="M34 18h8v8c0 2 2 4 4 4s4-2 4-4v-6" fill="none" stroke="currentColor" stroke-width="3"/>
                <path d="M48 42c0-4 6-10 6-10s6 6 6 10a6 6 0 1 1-12 0z"/>
            </svg>
            `,
        percentColor: 'bg-success-transparent badge fs-12'
    },

    {
        title: "Vales utilizados",
        avatarClass: "avatar-md flex-shrink-0",
        count: props.vales.total,
        percent: props.vales.porcentaje,
        priceColor: "secondary",
        svgIcon: `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"></rect><path d="M128,24A104,104,0,1,0,232,128,104.13,104.13,0,0,0,128,24Zm40,112H136v32a8,8,0,0,1-16,0V136H88a8,8,0,0,1,0-16h32V88a8,8,0,0,1,16,0v32h32a8,8,0,0,1,0,16Z"></path></svg>
        `,
        percentColor: 'bg-success-transparent badge fs-12'
    },

    {
        title: "Total Vehículos",
        avatarClass: "avatar-md flex-shrink-0",
        count: props.vehiculos.total,
        percent: props.vehiculos.porcentaje,
        priceColor: "warning",
        //svg de un vehiculo de carga con una caja y ruedas
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
        percentColor: 'bg-danger-transparent badge fs-12'
    },
    {
        title: "Total Conductores",
        avatarClass: "avatar-md flex-shrink-0",
        count: props.conductores.total,
        percent: props.conductores.porcentaje,
        priceColor: "primary",
        svgIcon: `
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><rect width="256" height="256" fill="none"></rect><path d="M208,32H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32ZM192,184H64a8,8,0,0,1,0-16H192a8,8,0,0,1,0,16Zm0-48H64a8,8,0,0,1,0-16H192a8,8,0,0,1,0,16Zm0-48H64a8,8,0,0,1,0-16H192a8,8,0,0,1,0,16Z"></path></svg>
        `,
        percentColor: 'bg-success-transparent badge fs-12'
    },


]);


const audienceoptions = ref({
    chart: {
        toolbar: {
            show: false
        },
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
        audienceseries.value[0].data = props.reporteMes.map(item => item.total_litros);
        audienceseries.value[0].name = 'Total Litros';
        audienceoptions.value.colors = ["var(--primary-color)",];

    } else if (tipo === 'costo') {
        audienceseries.value[0].data = props.reporteMes.map(item => item.total_precio);
        audienceseries.value[0].name = 'Total Costo Bs.';
        audienceoptions.value.colors = ["var(--success-rgb)",];
    }
}

onMounted(() => {
    audienceoptions.value.labels = props.reporteMes.map(item => item.mes);
    cambiarDatos('litros');
})

</script>

<template>

    <Head title="Dashboard" />

            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Dashboard
            </h2>

        <div class="row row-cols-xxl-4 row-cols-md-3 row-cols-1">
            <div className="col" v-for='(idx) in infoCards' :key='idx.id'>
                <CardAnalitic :listCard="true" :cardClass="`dashboard-main-card card ${idx.priceColor}`" :list="idx"
                    :NoCountUp="true" />
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">
                            Gastos de Combustible por Mes
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <!-- cambiar por 2 botones radio -->

                            <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
                                <input @click="cambiarDatos('litros')" type="radio" class="btn-check" name="filtro" id="btnradio1" checked>
                                <label class="btn btn-outline-primary" for="btnradio1">Litros</label>

                                <input @click="cambiarDatos('costo')" type="radio" class="btn-check" name="filtro" id="btnradio2">
                                <label class="btn btn-outline-primary" for="btnradio2">Costo</label>
                            </div>


                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div id="audience-metrics">
                            <Apexchart height="370px" type="line" :options="audienceoptions" :series="audienceseries" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

</template>
