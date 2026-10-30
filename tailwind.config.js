/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ['./*.php', './includes/*.php'],
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['Manrope', 'sans-serif'],
                display: ['Space Grotesk', 'sans-serif'],
                mono: ['JetBrains Mono', 'monospace'],
            },
            colors: {
                emerald: { 500: '#10B981' },
            },
        },
    },
};
