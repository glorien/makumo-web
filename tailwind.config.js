/** @type {import('tailwindcss').Config} */
// Farben aus dem Design-System "Luminous Flux", Schrift wie das Outfit-Theme der App
// (makumo App-Repo, ui-shared/.../theme/LuminousType.kt)
module.exports = {
    content: ['./*.php', './includes/*.php'],
    theme: {
        extend: {
            colors: {
                'background':                '#190047',
                'surface-container-lowest':  '#13003a',
                'surface-container-low':     '#23005c',
                'surface-container':         '#280066',
                'surface-container-high':    '#340081',
                'surface-container-highest': '#41009c',
                'on-surface':                '#e9ddff',
                'on-surface-variant':        '#d8c3ad',
                'outline-variant':           '#534434',
                'primary':                   '#ffc174',
                'primary-fixed':             '#ffddb8',
                'primary-container':         '#f59e0b',
                'on-primary':                '#472a00',
                'secondary':                 '#4edea3',
                'on-secondary':              '#003824',
                'tertiary':                  '#54ddfc',
                'tertiary-container':        '#29c1df',
                'on-tertiary':               '#003640',
                'error':                     '#ffb4ab',
                'error-container':           '#93000a',
            },
            fontFamily: {
                sans:     ['Outfit', 'sans-serif'],
                headline: ['Outfit', 'sans-serif'],
                label:    ['Outfit', 'sans-serif'],
            },
        },
        // Wie LuminousType.w() im Outfit-Theme: nichts fetter als 500,
        // Bold/SemiBold → Medium, Medium → Regular
        fontWeight: {
            light:    '300',
            normal:   '400',
            medium:   '400',
            semibold: '500',
            bold:     '500',
        },
    },
};
