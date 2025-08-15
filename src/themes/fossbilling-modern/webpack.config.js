const fs = require("node:fs");
const path = require("node:path");
const Encore = require('@symfony/webpack-encore');
const SpriteLoaderPlugin = require('svg-sprite-loader/plugin');
const { GenerateSW } = require('workbox-webpack-plugin');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
  .setOutputPath('./build/')
  .setPublicPath('/themes/fossbilling-modern/build')

  .configureFilenames( {
    js: 'js/[name]-bundle.[contenthash:6].js',
    css: 'css/[name]-bundle.[contenthash:6].css'
  })

  .addEntry('fossbilling-modern', './assets/fossbilling-modern.js')
  .addStyleEntry('markdown', './assets/scss/markdown.scss')

  .autoProvidejQuery()
  .enableIntegrityHashes()
  .disableSingleRuntimeChunk()
  .cleanupOutputBeforeBuild()
  .enableSourceMaps(!Encore.isProduction())
  .enableVersioning(Encore.isProduction())
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

const webpackConfig = Encore.getWebpackConfig();

// Add PWA Service Worker plugin for production builds
if (Encore.isProduction()) {
  webpackConfig.plugins.push(
    new GenerateSW({
      clientsClaim: true,
      skipWaiting: true,
      runtimeCaching: [
        {
          urlPattern: /^https:\/\/fonts\.googleapis\.com/,
          handler: 'StaleWhileRevalidate',
          options: {
            cacheName: 'google-fonts-stylesheets',
          },
        },
        {
          urlPattern: /^https:\/\/fonts\.gstatic\.com/,
          handler: 'CacheFirst',
          options: {
            cacheName: 'google-fonts-webfonts',
            expiration: {
              maxEntries: 30,
              maxAgeSeconds: 60 * 60 * 24 * 365, // 1 year
            },
          },
        },
        {
          urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp)$/,
          handler: 'CacheFirst',
          options: {
            cacheName: 'images',
            expiration: {
              maxEntries: 60,
              maxAgeSeconds: 60 * 60 * 24 * 30, // 30 days
            },
          },
        },
        {
          urlPattern: /\.(?:css|js)$/,
          handler: 'StaleWhileRevalidate',
          options: {
            cacheName: 'static-resources',
          },
        },
      ],
    })
  );
}

module.exports = webpackConfig;