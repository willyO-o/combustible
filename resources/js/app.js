import '../css/app.css';
import '../css/style.scss';
import './bootstrap';

import '@vueform/multiselect/themes/default.css'
import 'daterange-picker-vue3/dist/daterange-picker-vue3.css'

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

import { createPinia } from 'pinia';
import VueApexCharts from 'vue3-apexcharts';

import Vue3ColorPicker from 'vue3-colorpicker';
import 'vue3-colorpicker/style.css';
import 'vue3-toastify/dist/index.css';

// Se importa después de los CSS de librerías de arriba para que sus
// overrides (multiselect, daterange-picker, etc.) tengan prioridad en el
// cascade sin depender de especificidad extra.
import '../css/my-styles.css';

import can from '@/Directives/can';
import Decimal from '@/Directives/Decimal';
import Entero from '@/Directives/Entero';
import MaxLength from '@/Directives/MaxLength';
import PageLoader from '@/Components/PageLoader.vue';
import InstallPwaPrompt from '@/Components/InstallPwaPrompt.vue';
import { registerSW } from 'virtual:pwa-register';
import { showToast } from '@/Utils/alertUtil';
import { APP_NAME } from '@/Data/app';



const appName = APP_NAME;


// registerType: 'autoUpdate' ya activa la versión nueva del Service Worker sin
// pedir confirmación; sólo se avisa cuando queda lista para trabajar offline
// o si falla el registro (no debe romper el arranque de la app en ese caso).
registerSW({
    immediate: true,
    onOfflineReady() {
        showToast('La aplicación ya está lista para funcionar sin conexión.', 'info');
    },
    onRegisterError(error) {
        console.error('Error al registrar el Service Worker de la PWA:', error);
    },
});

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // PageLoader e InstallPwaPrompt se montan como hermanos de <App>, fuera
        // de cualquier layout de página: así funcionan igual sin importar si la
        // navegación entra o sale de un layout distinto (p.ej. Login -> Dashboard).
        return createApp({ render: () => [h(App, props), h(PageLoader), h(InstallPwaPrompt)] })
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
