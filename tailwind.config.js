export default {
  content: ['./resources/views/web/dashboard.blade.php'],
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#4f6df5',
          light: '#7b91f7',
          dark: '#3b57d4',
          faint: '#eef1fe',
        },
      },
      fontFamily: {
        sans: ['DM Sans', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Menlo', 'monospace'],
      },
    },
  },
  safelist: [
    'text-red-600',
    'text-amber-600',
    'text-emerald-600',
    'text-gray-500',
  ],
};
