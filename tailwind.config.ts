import type { Config } from 'tailwindcss';

export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.ts',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#2563eb',
                    700: '#1d4ed8',
                },
            },
            boxShadow: {
                auth: '0 24px 60px rgba(15, 23, 42, 0.18)',
                card: '0 1px 2px rgba(15, 23, 42, 0.08)',
                soft: '0 8px 20px rgba(37, 99, 235, 0.24)',
            },
        },
    },
    plugins: [],
} satisfies Config;
