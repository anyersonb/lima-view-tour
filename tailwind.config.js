import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Albert Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Hedvig Letters Serif"', 'serif'],
                price: ['"Instrument Serif"', 'serif'],
            },
            colors: {
                teal: {
                    50:  '#f0f7f7',
                    100: '#dcecec',
                    200: '#b9d9da',
                    300: '#8ebdbf',
                    400: '#5e9b9e',
                    500: '#357277',
                    600: '#1f5b5f',
                    700: '#15474b',
                    800: '#0f3438',
                    900: '#0a2326',
                },
                orange: {
                    50:  '#fdf6ee',
                    100: '#faead2',
                    200: '#f4d2a4',
                    300: '#edb56d',
                    400: '#e8a05a',
                    500: '#e29347',
                    600: '#c87530',
                    700: '#9f5a26',
                    800: '#7d4720',
                    900: '#623820',
                },
                cream: {
                    50:  '#ffffff',
                    100: '#f5f0ed',
                    200: '#ece6e2',
                    300: '#ddd2cb',
                    400: '#c5b6ac',
                    500: '#a89588',
                },
                state: {
                    error: '#c81f21',
                    success: '#145212',
                },
            },
            container: {
                center: true,
                padding: {
                    DEFAULT: '1rem',
                    sm: '1.5rem',
                    lg: '2rem',
                    xl: '3rem',
                },
                screens: {
                    sm: '640px',
                    md: '768px',
                    lg: '1024px',
                    xl: '1280px',
                    '2xl': '1536px',
                    '3xl': '1716px',
                },
            },
            screens: {
                '3xl': '1800px',
            },
            maxWidth: {
                'container': '1716px',
            },
            borderRadius: {
                pill: '9999px',
            },
        },
    },
    plugins: [forms, typography],
};
