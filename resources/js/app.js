import '../css/app.css';
import '../css/style.scss';
import '../css/my-styles.css';
import './bootstrap';

import '@vueform/multiselect/themes/default.css'

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import { createPinia } from 'pinia';
import VueApexCharts from 'vue3-apexcharts';

import Vue3ColorPicker from 'vue3-colorpicker';
import 'vue3-colorpicker/style.css';
import 'vue3-toastify/dist/index.css';
import can from '@/Directives/can';
import Decimal from '@/Directives/Decimal';
import Entero from '@/Directives/Entero';
import MaxLength from '@/Directives/MaxLength';
import PageLoader from '@/Components/PageLoader.vue';
import { registerSW } from 'virtual:pwa-register';



const appName = import.meta.env.VITE_APP_NAME || 'Laravel';


registerSW({ immediate: true });

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // PageLoader se monta como hermano de <App>, fuera de cualquier
        // layout de página: así reacciona a las transiciones de Inertia
        // (router.on('start'/'finish')) sin importar si la navegación
        // entra o sale de un layout distinto (p.ej. Login -> Dashboard).
        return createApp({ render: () => [h(App, props), h(PageLoader)] })
            .use(plugin)
            .use(ZiggyVue)
            .use(createPinia())

            .use(Vue3ColorPicker)
            .component('Apexchart', VueApexCharts)
            .directive('can', can)
            .directive('decimal', Decimal)
            .directive('entero', Entero)
            .directive('max-length', MaxLength)
            .mount(el);
    },
    // El PageLoader reemplaza a la barra de progreso por defecto de Inertia.
    progress: false,
});
