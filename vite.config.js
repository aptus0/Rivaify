import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/main.tsx',
                'resources/js/dashboard/main.tsx',
                'resources/js/internal-admin/main.tsx',
                'resources/js/storefront/main.tsx',
            ],
            refresh: false,
            fonts: [
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700, 800],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
