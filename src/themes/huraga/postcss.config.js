const autoprefixer = require('autoprefixer');

module.exports = {
  plugins: [
    autoprefixer({
      overrideBrowserslist: [
        '>0.5%',
        'last 4 versions',
        'Firefox ESR',
        'not dead',
        'not and_qq >0',
        'not Android >0',
        'not OperaMini all',
        'not kaios>0'
      ]
    }),
  ],
}
