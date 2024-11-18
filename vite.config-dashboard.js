// vite.config-index.js
import { fileURLToPath, URL } from 'node:url';

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
    plugins: [
        vue(),
    ],
    build: {
        emptyOutDir: false,
        rollupOptions: {
            input: {
                'dashboard': 'src/pages/dashboard/app.js'
            },
            output: {
                entryFileNames: 'pages/[name].js',
                assetFileNames: 'assets/[name][extname]'
            }
        }
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url))
        }
    }
})