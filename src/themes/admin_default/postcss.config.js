const autoprefixer = require('autoprefixer');

module.exports = {
  plugins: [
    autoprefixer({
      overrideBrowserslist: [
        '>0.5%',
        'last 4 versions',
        'Firefox ESR',
        'not ie < 11',
      ]
    }),
  ],
}
