module.exports = {
  plugins: {
    tailwindcss: {},
    autoprefixer: {
      browsers: [
        '>1%',
        'last 4 versions',
        'Firefox ESR',
        'not ie < 9',
      ],
      flexbox: true
    },
  },
}
