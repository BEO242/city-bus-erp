/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './views/**/*.php',
    './public/**/*.php',
    './src/**/*.php',
  ],
  // Pas de darkMode "class" — notre theme-cockpit.css gère le dark via [data-theme="dark"]
  theme: {
    extend: {
      colors: {
        cb: {
          primary:   '#C62828',
          secondary: '#8E0000',
          accent:    '#F9A825',
          dark:      '#6A0000',
          bg:        '#FFEBEE',
          red:       '#C62828',
          red2:      '#8E0000',
          red3:      '#B71C1C',
          redlt:     '#FFEBEE',
          yellow:    '#F9A825',
          yellowdk:  '#E65100',
          yellowlt:  '#FFF8E1',
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'Consolas', 'monospace'],
      },
      boxShadow: {
        soft: '0 8px 32px rgba(198,40,40,.08)',
      },
    },
  },
  plugins: [],
}
