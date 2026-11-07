
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/community-project.js', 'resources/js/project-phase-notices.js', 'resources/js/site-menu.js'],
            refresh: true,
        }),
    ],
});
