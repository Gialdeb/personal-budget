import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig(() => {
    const isBuildWatch = process.argv.includes('--watch');

    return {
        build: isBuildWatch
            ? {
                  watch: {
                      exclude: [
                          'resources/js/actions/**',
                          'resources/js/routes/**',
                          'resources/js/wayfinder/**',
                      ],
                  },
              }
            : undefined,
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                ssr: 'resources/js/ssr.ts',
                refresh: true,
            }),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
            wayfinder({
                formVariants: true,
            }),
        ],
    };
});
