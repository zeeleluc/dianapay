import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'dark': '#1C1E23',
                'darker': '#0F1114',
                'darkest': '#080A0D',
                'soft-yellow': '#FACC14',
                'soft-green': '#069668',
                'soft-blue': '#2463EB',
            },
        },
    },

    plugins: [forms],
};
