import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            // Tiga family, masing-masing punya wilayahnya sendiri — lihat
            // docs/DESIGN-TOKENS.md §2. Bunny menyalin file font ke build,
            // jadi tidak ada request ke pihak ketiga saat runtime.
            fonts: [
                bunny('Inter', { weights: [400, 500, 600] }),
                bunny('Roboto', { weights: [400, 500, 700] }),
                bunny('Plus Jakarta Sans', { weights: [600, 700] }),
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
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
