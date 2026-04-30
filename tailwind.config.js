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
                    "primary": "#4f46e5",
                    "primary-content": "#ffffff",
                    "secondary": "#7c3aed",
                    "secondary-content": "#ffffff",
                    "accent": "#06b6d4",
                    "accent-content": "#ffffff",
                    "neutral": "#1f2937",
                    "neutral-content": "#f9fafb",
                    "base-100": "#ffffff",
                    "base-200": "#f8fafc",
                    "base-300": "#e2e8f0",
                    "base-content": "#0f172a",
                    "info": "#3b82f6",
                    "info-content": "#ffffff",
                    "success": "#10b981",
                    "success-content": "#ffffff",
                    "warning": "#f59e0b",
                    "warning-content": "#ffffff",
                    "error": "#ef4444",
                    "error-content": "#ffffff",
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
                    "primary": "#818cf8",
                    "primary-content": "#1e1b4b",
                    "secondary": "#a78bfa",
                    "secondary-content": "#2e1065",
                    "accent": "#22d3ee",
                    "accent-content": "#083344",
                    "neutral": "#1e293b",
                    "neutral-content": "#e2e8f0",
                    "base-100": "#0b1120",
                    "base-200": "#0f172a",
                    "base-300": "#1e293b",
                    "base-content": "#e2e8f0",
                    "info": "#60a5fa",
                    "info-content": "#0c1d3d",
                    "success": "#34d399",
                    "success-content": "#022c22",
                    "warning": "#fbbf24",
                    "warning-content": "#451a03",
                    "error": "#f87171",
                    "error-content": "#450a0a",
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
