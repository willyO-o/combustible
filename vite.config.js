import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    build: {
        // 'hidden': genera los .map en public/build para poder depurar errores
        // de producción localmente (mapear un stack minificado a su archivo
        // .vue real), pero SIN el comentario //# sourceMappingURL al final del
        // .js — el navegador del usuario final nunca los pide ni los descarga.
        sourcemap: 'hidden',
    },
    server: {
        // host: '0.0.0.0',
        watch: {
            usePolling: true,
            interval: 300,
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            // laravel-vite-plugin fija el `base` de Vite en "/build/" (ahí viven los
            // assets compilados). vite-plugin-pwa, si no se le indica lo contrario,
            // reutiliza ESE MISMO base como `scope` del Service Worker al registrarlo
            // — es decir, sin esto el SW quedaba con scope "/build/" y nunca llegaba a
            // controlar el resto del sitio ("/", "/dashboard", etc.), rompiendo el
            // soporte offline y el criterio de instalabilidad de Chrome (el scope
            // efectivo del SW debe cubrir el `start_url`, "/"). Se fija explícitamente
            // a la raíz del sitio. NO se toca `base` (sigue en "/build/", el de Vite):
            // el archivo sw.js se sigue sirviendo físicamente desde /build/sw.js —de
            // ahí dependen, relativas a esa carpeta, las rutas del precache— y para que
            // el navegador acepte un scope de registro ("/") más amplio que la carpeta
            // donde vive el script ("/build/") el servidor debe responder ese request
            // con la cabecera `Service-Worker-Allowed: /` (ver public/.htaccess y la
            // nota para Nginx en .ai/rules).
            scope: '/',
            includeAssets: [
                'favicon.ico',
                'favicon-16x16.png',
                'favicon-32x32.png',
                'apple-touch-icon.png',
            ],
            manifest: {
                id: '/',
                name: 'SIGET · Sistema Integral de Gestión de Maquinaria y Operaciones',
                short_name: 'SIGET',
                description: 'Gestión integral de maquinaria y operaciones: vehículos, conductores, combustible y mantenimiento.',
                lang: 'es',
                theme_color: '#162450',
                background_color: '#162450',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                icons: [
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/pwa-512x512-maskable.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                // El default de generateSW ("index.html") es para SPAs con un único
                // shell estático: no aplica acá, cada ruta la renderiza Laravel/Inertia
                // en el servidor con props distintas por página. Con scope "/" activo
                // (ver arriba), dejar el default haría que el SW intente servir un
                // "/index.html" que no existe ante cualquier navegación offline no
                // cacheada, en vez de dejar que el navegador la reporte sin conexión.
                navigateFallback: null,
                navigateFallbackDenylist: [/^\/api/],
                cleanupOutdatedCaches: true,
                // Los íconos PWA/favicons y las fotos (vehículos, conductores, logo)
                // viven en public/ o storage/ y no pasan por el pipeline de Vite, así
                // que no quedan precacheados: se cachean en tiempo de ejecución la
                // primera vez que se piden, para que también estén disponibles offline.
                runtimeCaching: [
                    {
                        urlPattern: ({ request, url }) => request.destination === 'image' && url.origin === self.location.origin,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'imagenes',
                            expiration: {
                                maxEntries: 200,
                                maxAgeSeconds: 60 * 60 * 24 * 30, // 30 días
                            },
                            cacheableResponse: { statuses: [0, 200] },
                        },
                    },
                ],
            },
        }),
    ],
});
