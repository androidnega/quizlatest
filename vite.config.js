import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/student-dashboard.css',
                'resources/css/student-assignment-take.css',
                'resources/css/student-exam-arena.css',
                'resources/js/app.js',
                'resources/js/studentExamRuntime.js',
                'resources/js/studentExamArena.js',
                'resources/js/studentProfilePhotoCrop.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'http://0.0.0.0:5173',
        hmr: {
            host: '0.0.0.0',
            port: 5173,
            protocol: 'ws',
        },
        cors: {
            origin: [
                'http://0.0.0.0:8000',
                'http://localhost:8000',
                'http://[::1]:8000',
            ],
        },
    },
});
