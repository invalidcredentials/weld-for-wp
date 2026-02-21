import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    build: {
        lib: {
            entry: resolve(__dirname, 'frontend/main.js'),
            name: 'WeldPress',
            formats: ['iife'],
            fileName: () => 'js/weldpress.js',
        },
        outDir: 'assets',
        emptyOutDir: false,
        sourcemap: false,
        cssCodeSplit: false,
        rollupOptions: {
            output: {
                assetFileNames: 'css/[name][extname]',
            },
        },
    },
});
