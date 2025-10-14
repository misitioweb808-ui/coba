import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import obfuscatorPlugin from 'vite-plugin-javascript-obfuscator';
import path from 'path';

export default defineConfig(({ command, mode }) => {
  const isProduction = mode === 'production';

  return {
    plugins: [
      vue(),
      laravel({
        input: ['resources/css/app.css', 'resources/js/app.js'],
        refresh: true,
      }),
      tailwindcss(),
      // Aplicar ofuscación solo en build de producción
      ...(isProduction
        ? [
            obfuscatorPlugin({
              options: {
                compact: true,
                controlFlowFlattening: true,
                controlFlowFlatteningThreshold: 0.3,
                deadCodeInjection: true,
                deadCodeInjectionThreshold: 0.2,
                debugProtection: false, // Deshabilitado para evitar problemas
                disableConsoleOutput: true,
                identifierNamesGenerator: 'mangled',
                identifiersPrefix: '',
                log: false,
                numbersToExpressions: true,
                renameGlobals: false,
                selfDefending: false, // Deshabilitado para estabilidad
                simplify: true,
                splitStrings: true,
                splitStringsChunkLength: 3,
                stringArray: true,
                stringArrayCallsTransform: true,
                stringArrayCallsTransformThreshold: 0.8,
                stringArrayEncoding: ['base64'],
                stringArrayIndexShift: true,
                stringArrayRotate: true,
                stringArrayShuffle: true,
                stringArrayWrappersCount: 3,
                stringArrayWrappersChainedCalls: true,
                stringArrayWrappersParametersMaxCount: 5,
                stringArrayWrappersType: 'function',
                stringArrayThreshold: 0.9,
                transformObjectKeys: true,
                unicodeEscapeSequence: false,
                // Preservar nombres críticos
                reservedNames: [
                  'Vue',
                  'createApp',
                  'mount',
                  'render',
                  'h',
                  'Inertia',
                  'createInertiaApp',
                  'router',
                  'visit',
                  'route',
                  'Ziggy',
                  'ZiggyVue',
                  'resolve',
                  'setup',
                  'el',
                  'App',
                  'props',
                  'plugin',
                ],
                reservedStrings: [
                  // Preservar strings críticos de Inertia
                  './Pages/',
                  '.vue',
                  'render',
                  'color',
                  'includeCSS',
                  'showSpinner',
                ],
              },
            }),
          ]
        : []),
    ],
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'resources'),
      },
    },
    // Configuración adicional para producción
    build: {
      // Deshabilitar sourcemaps en producción para evitar conflictos con ofuscación
      sourcemap: !isProduction,
      // Minificar con terser en producción
      minify: 'terser',
      terserOptions: {
        compress: {
          drop_console: true,
          drop_debugger: true,
        },
      },
      // Dividir chunks para mejor ofuscación
      rollupOptions: {
        output: {
          manualChunks: {
            'vue-vendor': ['vue', '@inertiajs/vue3'],
            utils: ['axios', 'platform', 'is-mobile'],
          },
        },
      },
    },
  };
});
