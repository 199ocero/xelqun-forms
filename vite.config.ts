import { fileURLToPath } from 'node:url';
import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite-plus';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            // Lets the example plugin import the package by name, like a real plugin.
            '@xelqun/forms/testing': fileURLToPath(
                new URL('./resources/js/testing.ts', import.meta.url),
            ),
            '@xelqun/forms': fileURLToPath(new URL('./resources/js/index.ts', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        include: ['tests/js/**/*.test.ts'],
    },
    lint: {
        ignorePatterns: ['vendor/**', 'node_modules/**'],
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 100,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: [
            'vendor/**',
            '.claude/**',
            'composer.json',
            'docs/**',
            'tests/Fixtures/**',
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/xelqun.css',
        },
    },
});
