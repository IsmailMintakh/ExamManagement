import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
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

        // ────────────────────────────────────────────────────────────
        // Progressive Web App configuration
        //
        // - generateSW strategy: Workbox auto-builds the service worker
        //   each time we run `npm run build`. No manual SW code to maintain.
        // - registerType: 'prompt' shows the user a banner when a new
        //   version is available; we don't auto-reload silently.
        // - The manifest below is what makes the site installable on
        //   mobile + desktop. Icons, name, theme color are all here.
        // - Workbox runtime caching: every navigation HTML uses NetworkFirst
        //   so the user gets fresh content when online and a cached
        //   fallback when offline. Static assets (build/*, images) use
        //   CacheFirst for instant loads.
        // - The base URL (`scope`) is `/` so the SW controls the whole app.
        // ────────────────────────────────────────────────────────────
        VitePWA({
            registerType: 'prompt',
            injectRegister: 'auto',

            // Switched from generateSW → injectManifest in Phase 2 because
            // we need custom service-worker code for push notifications.
            // The plugin still injects the precache manifest at build time.
            strategies: 'injectManifest',
            srcDir: 'resources/js/sw',
            filename: 'sw.js',
            outDir: 'public',
            manifestFilename: 'manifest.webmanifest',

            // Don't cache the dev server's HMR endpoints.
            devOptions: {
                enabled: false, // PWA only active in production builds
            },

            includeAssets: [
                'favicon.ico',
                'apple-touch-icon-180x180.png',
                'pwa-64x64.png',
                'pwa-192x192.png',
                'pwa-512x512.png',
                'maskable-icon-512x512.png',
            ],

            // ──────────── App manifest ────────────
            manifest: {
                name: 'GBHSS Skardu — Exam Management',
                short_name: 'GBHSS Skardu',
                description: 'Government Boys Higher Secondary School No.1, Skardu — exam, marks, and student management.',
                lang: 'en-PK',
                start_url: '/',
                scope: '/',
                display: 'standalone',
                orientation: 'any',
                theme_color: '#047857',     // emerald-700, matches sidebar/primary
                background_color: '#0f172a', // slate-950, matches splash
                categories: ['education', 'productivity'],

                icons: [
                    { src: '/pwa-64x64.png',                 sizes: '64x64',   type: 'image/png', purpose: 'any' },
                    { src: '/pwa-192x192.png',               sizes: '192x192', type: 'image/png', purpose: 'any' },
                    { src: '/pwa-512x512.png',               sizes: '512x512', type: 'image/png', purpose: 'any' },
                    { src: '/maskable-icon-512x512.png',     sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                    { src: '/apple-touch-icon-180x180.png',  sizes: '180x180', type: 'image/png' },
                ],

                // Quick app-shortcut menu (long-press on home-screen icon)
                shortcuts: [
                    { name: 'Marks Entry',  short_name: 'Marks',     url: '/marks',    description: 'Enter or submit marks' },
                    { name: 'My Class',     short_name: 'Class',     url: '/my-class', description: 'Class teacher hub' },
                    { name: 'Students',     short_name: 'Students',  url: '/students', description: 'Manage students' },
                    { name: 'Notifications',short_name: 'Notifs',    url: '/notifications', description: 'View alerts' },
                ],
            },

            // ──────────── injectManifest config ────────────
            // The custom SW lives at resources/js/sw/sw.js. Workbox
            // injects the precache manifest (app shell file list) at
            // the `self.__WB_MANIFEST` placeholder during build.
            injectManifest: {
                globPatterns: ['**/*.{js,css,html,png,svg,jpg,webp,woff2,woff,ttf}'],
                maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
            },
        }),
    ],
});
