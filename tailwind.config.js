import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                /**
                 * Must stay in sync with resources/css/app.css `:root` — Tailwind compiles
                 * `text-qs-primary`, `bg-qs-card`, etc. from here; arbitrary `var(--qs-*)` uses those variables.
                 */
                qs: {
                    bg: '#ffffff',
                    primary: '#56aebb',
                    accent: '#56aebb',
                    soft: '#d5e7ea',
                    card: '#ffffff',
                    surface: '#ffffff',
                    text: '#15343a',
                    muted: '#5f7478',
                    danger: '#e46f2e',
                    'danger-soft': '#fff0e7',
                },
            },
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', ...defaultTheme.fontFamily.sans],
                brand: ['Antonio', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'sans-serif'],
            },
        },
    },

    plugins: [forms],
};
