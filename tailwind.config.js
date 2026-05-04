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
                 * QUIZSNAP — white-first UI, deep green brand, soft sage structure, warm cream panels, rose danger.
                 * `accent` mirrors `primary` for legacy classnames (qs-accent → same token).
                 */
                qs: {
                    bg: '#FFFFFF',
                    primary: '#166534',
                    accent: '#166534',
                    soft: '#DCE8E0',
                    card: '#FAF7F2',
                    surface: '#FAF7F2',
                    text: '#0F2918',
                    muted: '#5C6B62',
                    danger: '#9F1239',
                    'danger-soft': '#FFE4E9',
                },
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
