const fs = require("node:fs");
const Encore = require('@symfony/webpack-encore');
const SpriteLoaderPlugin = require('svg-sprite-loader/plugin');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
  .setOutputPath('./build/')
  .setPublicPath('/themes/huraga/build')

  .configureFilenames( {
    js: 'js/[name]-bundle.[contenthash:6].js',
    css: 'css/[name]-bundle.[contenthash:6].css'
  })

  .addEntry('huraga', './assets/huraga.js')
  .addStyleEntry('markdown', './assets/scss/markdown.scss')

  .autoProvidejQuery()
  .enableIntegrityHashes()
  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
  .configureBabel((config) => {
    config.plugins.push('@babel/plugin-proposal-class-properties');
    config.plugins.push('@babel/plugin-proposal-object-rest-spread');
  })
  .configureBabelPresetEnv((config) => {
    config.useBuiltIns = 'usage';
    config.corejs = 3;
  })
  .enableSassLoader()
  .enablePostCssLoader()
  .configureTerserPlugin((options) => {
    if (Encore.isProduction()) {
      options.extractComments = false;
      options.terserOptions = {
        format: {
          comments: false,
        },
      };
    }
  })
  .configureCssLoader((config) => {
    config.url = {
      filter: (url) => {
        if(!fs.existsSync(url)) {
          // replace css url path
          let path = url.replace(/^(\.\.\/){2}/g, './');
          // if still does not resolve, ignore that url
          return fs.existsSync(path) ? path : false;
        }
      }
    }
  })
;

module.exports = Encore.getWebpackConfig();
