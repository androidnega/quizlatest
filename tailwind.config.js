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
                 * Brand palette is resolved at runtime via CSS RGB-triplet variables
                 * defined in resources/css/app.css `:root` (and overridden under
                 * `prefers-color-scheme: dark`). The `<alpha-value>` placeholder
                 * lets Tailwind utilities keep using opacity modifiers like
                 * `bg-qs-primary/20` and `focus:ring-qs-danger/45` even though
                 * the underlying color is theme-aware.
                 */
                qs: {
                    bg: 'rgb(var(--qs-bg-rgb) / <alpha-value>)',
                    primary: 'rgb(var(--qs-primary-rgb) / <alpha-value>)',
                    accent: 'rgb(var(--qs-accent-rgb) / <alpha-value>)',
                    soft: 'rgb(var(--qs-soft-rgb) / <alpha-value>)',
                    card: 'rgb(var(--qs-card-rgb) / <alpha-value>)',
                    surface: 'rgb(var(--qs-surface-rgb) / <alpha-value>)',
                    text: 'rgb(var(--qs-text-rgb) / <alpha-value>)',
                    muted: 'rgb(var(--qs-muted-rgb) / <alpha-value>)',
                    danger: 'rgb(var(--qs-danger-rgb) / <alpha-value>)',
                    'danger-soft': 'rgb(var(--qs-danger-soft-rgb) / <alpha-value>)',
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
