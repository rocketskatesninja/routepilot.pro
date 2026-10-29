import path from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        // The same "@/…" imports as the app (vite.config.ts).
        alias: { '@': path.resolve(import.meta.dirname, './resources/js') },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
        environment: 'node',
    },
});
