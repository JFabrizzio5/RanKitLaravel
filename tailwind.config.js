import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // AGREGA ESTA LÍNEA AQUÍ:
    darkMode: 'class', 

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
                chakra: ['Chakra Petch', 'sans-serif'],
                block: ['Black Ops One', 'impact', 'sans-serif'],
            },
            colors: {
                fnDark: '#030108',
                fnDeepPurple: '#0a0514',
                fnCard: '#0f081c',
                fnBrightPurple: '#9b30ff',
                fnMagenta: '#ff3df1',
                fnGold: '#f1c40f',
                fnNeonCyan: '#00e5ff',
                fnEmerald: '#00b074',
                fnCrimson: '#d7263d'
            },
            boxShadow: {
                glowPurple: '0 0 40px -10px rgba(155, 48, 255, 0.5)',
                glowMagenta: '0 0 40px -10px rgba(255, 61, 241, 0.5)',
                glowCyan: '0 0 40px -10px rgba(0, 229, 255, 0.5)',
                glowGold: '0 0 40px -10px rgba(241, 196, 15, 0.5)',
                glowEmerald: '0 0 40px -10px rgba(0, 176, 116, 0.55)',
                glowCrimson: '0 0 40px -10px rgba(215, 38, 61, 0.55)',
            },
            backgroundImage: {
                'premium-gradient': 'linear-gradient(180deg, #0a0514 0%, #030108 100%)',
            }
        },
    },

    plugins: [forms],
};