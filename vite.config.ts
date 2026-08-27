import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.ts',
            ],
            refresh: true,
        }),

        vue(),
    ],

    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },

    server: {
        // NativePHP Jump proxies Vite through its LAN HTTP server. Keep the
        // upstream Vite server on plain HTTP so Jump can rewrite its URLs
        // for the phone; the Herd HTTPS hostname is not reachable there.
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        https: false,

        ws: {
            host: 'localhost',
            protocol: 'ws',
            port: 5173,
        },
    },
});
