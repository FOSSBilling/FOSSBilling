const autoprefixer = require('autoprefixer');
const purgeCss = require('@fullhuman/postcss-purgecss');
const Encore = require('@symfony/webpack-encore');

module.exports = {
  plugins: [
    Encore.isProduction() ? purgeCss({
      content: [
        '../../**/*.html.twig',
        'assets/**/*.js',
      ],
      safelist: {
        standard: [/flag(-.*)?/],
      }
    }) : false,
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
