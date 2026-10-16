import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ command, mode }) => {
    const isBuildWatch = process.argv.includes('--watch');
    const isDockerDevServer =
        command === 'serve' && process.env.SOAMCO_VITE_DOCKER === '1';
    const appUrl = isDockerDevServer
        ? new URL(loadEnv(mode, process.cwd(), 'APP_URL').APP_URL)
        : undefined;

    return {
        base: isDockerDevServer ? '/vite/' : undefined,
        server: appUrl
            ? {
                  host: '0.0.0.0',
                  port: 5173,
                  strictPort: true,
                  allowedHosts: [appUrl.hostname],
                  origin: appUrl.origin,
                  hmr: {
                      protocol: appUrl.protocol === 'https:' ? 'wss' : 'ws',
                      host: appUrl.hostname,
                      clientPort:
                          Number(appUrl.port) ||
                          (appUrl.protocol === 'https:' ? 443 : 80),
                  },
              }
            : undefined,
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
