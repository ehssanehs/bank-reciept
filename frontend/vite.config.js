import { defineConfig } from 'vite';

// The Blade views reference asset('css/app.css'). This pipeline compiles the
// design-system CSS and writes it to backend/public/css/app.css so the Laravel
// app works both from the committed stylesheet and from this source.
export default defineConfig({
    build: {
        outDir: '../backend/public',
        emptyOutDir: false,
        rollupOptions: {
            input: {
                app: 'src/main.js',
            },
            output: {
                entryFileNames: 'js/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) return 'css/[name][extname]';
                    return 'assets/[name][extname]';
                },
            },
        },
    },
});
