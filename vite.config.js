import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
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
