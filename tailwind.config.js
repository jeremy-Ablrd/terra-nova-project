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
            // Polices du design, hébergées dans resources/fonts (aucune requête vers un site tiers).
            fontFamily: {
                sans: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Bricolage Grotesque"', '"Trebuchet MS"', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', ...defaultTheme.fontFamily.mono],
            },
            // Palette du design Terra Nova (docs/design) : les couleurs sont des variables CSS (app.css), ce qui
            // permet le thème « Contraste renforcé ». Les anciennes teintes Tailwind pointent vers ces variables.
            colors: {
                white: 'var(--surface-raised)',
                gray: {
                    50: 'var(--surface)',
                    100: 'var(--surface-sunken)',
                    200: 'var(--border-subtle)',
                    300: 'var(--border-strong)',
                    400: 'var(--ink-muted)',
                    500: 'var(--ink-muted)',
                    600: 'var(--ink-muted)',
                    700: 'var(--ink)',
                    800: 'var(--ink)',
                    900: 'var(--ink)',
                },
                indigo: {
                    50: 'var(--info-bg)',
                    100: 'var(--info-bg)',
                    400: 'var(--accent)',
                    500: 'var(--accent)',
                    600: 'var(--info-text)',
                    700: 'var(--info-text)',
                    800: 'var(--info-text)',
                },
                blue: { 100: 'var(--info-bg)', 800: 'var(--info-text)' },
                amber: { 100: 'var(--warn-bg)', 800: 'var(--warn-text)' },
                green: {
                    50: 'var(--success-bg)',
                    100: 'var(--success-bg)',
                    200: 'var(--success-border)',
                    600: 'var(--success-text)',
                    800: 'var(--success-text)',
                    900: 'var(--success-text)',
                },
                red: {
                    50: 'var(--danger-bg)',
                    200: 'var(--danger-border)',
                    500: 'var(--danger-border)',
                    600: 'var(--danger-text)',
                    700: 'var(--danger-border)',
                    800: 'var(--danger-border)',
                    900: 'var(--danger-text)',
                },
                brand: { DEFAULT: 'var(--brand)', text: 'var(--brand-text)', on: 'var(--on-brand)' },
                accent: 'var(--accent)',
            },
        },
    },

    plugins: [forms],
};
