import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                '2xs': ['0.6875rem', { lineHeight: '1rem' }],
            },
            spacing: {
                '4.5': '1.125rem',
                '5.5': '1.375rem',
                '18': '4.5rem',
            },
            boxShadow: {
                'soft': '0 1px 2px 0 rgb(0 0 0 / 0.04), 0 1px 3px 0 rgb(0 0 0 / 0.08)',
                'card': '0 1px 3px 0 rgb(0 0 0 / 0.06), 0 1px 2px -1px rgb(0 0 0 / 0.04)',
                'elevated': '0 4px 12px -2px rgb(0 0 0 / 0.06), 0 2px 4px -1px rgb(0 0 0 / 0.04)',
                'lifted': '0 10px 25px -5px rgb(0 0 0 / 0.08), 0 4px 10px -3px rgb(0 0 0 / 0.04)',
            },
            animation: {
                'shimmer': 'shimmer 2s linear infinite',
                'fade-in': 'fadeIn 0.2s ease-out',
                'slide-up': 'slideUp 0.25s ease-out',
            },
            keyframes: {
                shimmer: {
                    '0%': { backgroundPosition: '-1000px 0' },
                    '100%': { backgroundPosition: '1000px 0' },
                },
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
        },
    },

    plugins: [forms, daisyui],

    daisyui: {
        themes: [
            {
                light: {
                    // ───── State-of-the-art education palette ─────
                    // Primary action: deep teal (#0d9488) — confident,
                    // distinct from generic blue, education-appropriate.
                    // Secondary: slate (#4b545c) — supporting structural
                    // colour for sidebar, headers, body text emphasis.
                    // Accent: amber (#f59e0b) — for highlights and gold rings.
                    "primary":           "#0d9488",
                    "primary-content":   "#ffffff",
                    "secondary":         "#4b545c",
                    "secondary-content": "#ffffff",
                    "accent":            "#f59e0b",
                    "accent-content":    "#1a1209",
                    "neutral":           "#1e293b",
                    "neutral-content":   "#f8fafc",
                    "base-100":          "#ffffff",
                    "base-200":          "#f8fafc",
                    "base-300":          "#e2e8f0",
                    "base-content":      "#0f172a",
                    "info":              "#0ea5e9",
                    "info-content":      "#ffffff",
                    "success":           "#10b981",
                    "success-content":   "#ffffff",
                    "warning":           "#f59e0b",
                    "warning-content":   "#1a1209",
                    "error":             "#dc2626",
                    "error-content":     "#ffffff",
                    "--rounded-box": "0.875rem",
                    "--rounded-btn": "0.5rem",
                    "--rounded-badge": "0.375rem",
                    "--tab-radius": "0.5rem",
                    "--btn-text-case": "none",
                    "--animation-btn": "0.15s",
                    "--animation-input": "0.15s",
                    "--border-btn": "1px",
                },
            },
            {
                dark: {
                    // Brighter teal so it pops on dark surfaces.
                    // Surfaces are warm-slate (not blue-tinted), echoing
                    // the secondary #4b545c family the user requested.
                    "primary":           "#2dd4bf",
                    "primary-content":   "#022c22",
                    "secondary":         "#94a3b8",
                    "secondary-content": "#0f172a",
                    "accent":            "#fbbf24",
                    "accent-content":    "#1a1209",
                    "neutral":           "#1e293b",
                    "neutral-content":   "#f1f5f9",
                    "base-100":          "#161b22",
                    "base-200":          "#1f242c",
                    "base-300":          "#2c333d",
                    "base-content":      "#f1f5f9",
                    "info":              "#38bdf8",
                    "info-content":      "#0c1d3d",
                    "success":           "#34d399",
                    "success-content":   "#022c22",
                    "warning":           "#fbbf24",
                    "warning-content":   "#451a03",
                    "error":             "#f87171",
                    "error-content":     "#450a0a",
                    "--rounded-box": "0.875rem",
                    "--rounded-btn": "0.5rem",
                    "--rounded-badge": "0.375rem",
                    "--tab-radius": "0.5rem",
                    "--btn-text-case": "none",
                    "--animation-btn": "0.15s",
                    "--animation-input": "0.15s",
                    "--border-btn": "1px",
                },
            },
        ],
        darkTheme: 'dark',
        base: true,
        styled: true,
        utils: true,
        logs: false,
    },
};
