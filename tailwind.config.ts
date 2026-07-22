import type { Config } from 'tailwindcss';

export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.ts',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {},
    },
    plugins: [],
} satisfies Config;
