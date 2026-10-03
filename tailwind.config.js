import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Classes écrites dans le PHP (badges des enums Statut, Niveau…) : sans cela elles seraient purgées du CSS.
        './app/**/*.php',
    ],

    theme: {
        extend: {
            // Polices du système (aucun fichier de police téléchargé, aucune requête vers un site tiers).
            fontFamily: {
                sans: defaultTheme.fontFamily.sans,
            },
        },
    },

    plugins: [forms],
};
