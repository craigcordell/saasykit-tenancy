import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/*.css',
                'resources/js/*.js',
                'resources/css/filament/admin/theme.css',
                'resources/css/filament/dashboard/theme.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
