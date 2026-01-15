const plugin = require('tailwindcss/plugin');

module.exports = {
    content: ["./**/*.php"],
    theme: {
        extend: {},
    },
    plugins: [
        require('@tailwindcss/scrollbar'),
    ],
};

module.exports = {
  content: [
    './**/*.php',
    './components/**/*.{js,ts,vue}',
    './admin/**/*.{php,html}',
    './user/**/*.{php,html}',
    './includes/**/*.{php,html}',
  ],
  safelist: [
    'w-6', 'h-6',
    'w-8', 'h-8',
    'w-12', 'h-12',
    'w-16', 'h-16',
    'w-24', 'h-24',
    'w-32', 'h-32',
    'w-40', 'h-40',
    'w-48', 'h-48',
    'min-h-[3.5rem]', 'min-h-[4rem]',
    'rounded-full',
    'object-cover',
    'shadow-sm', 'shadow-lg',
    'shrink-0',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: "#007AFF",
          hover: "#005FCC",
        },
        red: {
          DEFAULT: "#FF5630",
          light: "#FFE6E0",
        },
        green: {
          DEFAULT: "#38CB89",
          light: "#E5F6EF",
        },
        yellow: {
          DEFAULT: "#FFAB00",
          light: "#FDF4E0",
        },
        background: "#FAFAFA",
        body: "#18181C",
      },
    },
  },
  plugins: [
  plugin(function({ addUtilities }) {
    addUtilities({
      '.scrollbar-soft-blue': {
        'scrollbar-color': '#6CA7E5 #E2F0FC',
        'scrollbar-width': 'thin',
      },
      '.scrollbar-soft-blue::-webkit-scrollbar': {
        width: '6px',
        height: '6px',
      },
      '.scrollbar-soft-blue::-webkit-scrollbar-track': {
        backgroundColor: '#E2F0FC',
        borderRadius: '4px',
      },
      '.scrollbar-soft-blue::-webkit-scrollbar-thumb': {
        backgroundColor: '#6CA7E5',
        borderRadius: '4px',
        transition: 'background-color 0.3s ease',
      },
      '.scrollbar-soft-blue:hover::-webkit-scrollbar-thumb': {
        backgroundColor: '#3D7BCC',
      },
    }, ['responsive']);
  })
]
}