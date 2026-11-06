// vite.config.js
import { fileURLToPath, URL } from 'node:url';

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [
        vue(),
    ],
    build: {
        sourcemap: true,
        rollupOptions: {
            input: {
                config: 'src/pages/config/app.js',
                dashboard: 'src/pages/dashboard/app.js',
                report: 'src/pages/report/app.js',
                shipment: 'src/pages/shipment/app.js',
            },
            output: {
                entryFileNames: 'pages/[name].js',
                assetFileNames: `assets/[name][extname]`,
            },
        },
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url))
        }
    }
})