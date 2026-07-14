import '../css/app.css';
import '../css/style.scss';
// import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import { createPinia } from 'pinia';
import VueApexCharts from 'vue3-apexcharts';

import Vue3ColorPicker from 'vue3-colorpicker';
import 'vue3-colorpicker/style.css';
import 'vue3-toastify/dist/index.css';


const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

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
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
