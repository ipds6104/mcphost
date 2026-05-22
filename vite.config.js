import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        // HMR: browser terhubung ke localhost:5173 dari Windows host
        // (port 5173 di-expose dari container ke host)
        hmr: {
            host: 'localhost',
            port: 5173,
        },
        // usePolling wajib di WSL2/Docker volume mount karena
        // inotify tidak bekerja dengan Windows filesystem
        watch: {
            usePolling: true,
            interval: 300,
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            // refresh: true = Blade & PHP file changes auto-reload browser
            refresh: [
                'resources/views/**',
                'routes/**',
                'app/**',
            ],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
