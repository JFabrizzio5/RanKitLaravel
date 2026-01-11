import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import fs from 'fs';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // --- Plugin Personalizado para Copiar Archivos ---
        {
            name: 'copy-build-to-root',
            closeBundle() {
                // 1. Definir origen (public/build) y destino (carpeta 'build' en la raíz)
                const source = path.resolve(__dirname, 'public/build');
                const destination = path.resolve(__dirname, 'build'); 

                // 2. Verificar si se generó el build y copiarlo
                if (fs.existsSync(source)) {
                    // fs.cpSync copia carpetas de forma recursiva (requiere Node >= 16.7)
                    // Si usas una versión muy vieja de Node, avísame para darte otra opción.
                    try {
                        fs.cpSync(source, destination, { recursive: true, force: true });
                        console.log(`\n✅ [Vite Copy] Archivos copiados exitosamente a la raíz: ${destination}`);
                    } catch (err) {
                        console.error('\n❌ Error copiando los archivos al root:', err);
                    }
                }
            }
        }
    ],
});