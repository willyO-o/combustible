import '../css/app.css';
import '../css/style.scss';
import '../css/my-styles.css';
import './bootstrap';

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
        return createApp({ render: () => h(App, props) })
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
    progress: {
        color: '#4B5563',
    },
});
