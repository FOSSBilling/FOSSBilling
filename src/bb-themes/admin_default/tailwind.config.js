module.exports = {
  mode: 'jit',
  content: ['./html/**/*.html.twig'],
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/forms')
  ],
}
