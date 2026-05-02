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
                /** QUIZSNAP tokens: navy text, blue-gray structure, gold accent, white-heavy surfaces */
                qs: {
                    bg: '#FFFFFF',
                    card: '#F4F7FB',
                    text: '#0F172A',
                    muted: '#64748B',
                    soft: '#CBD5E1',
                    accent: '#F2A650',
                    /** Semantic only: validation + destructive affordances */
                    danger: '#B42318',
                    'danger-soft': '#FBECEC',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
