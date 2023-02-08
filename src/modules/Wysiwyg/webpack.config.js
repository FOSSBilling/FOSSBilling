'use strict';

const path = require('node:path');
const {styles} = require('@ckeditor/ckeditor5-dev-utils');
const TerserWebpackPlugin = require('terser-webpack-plugin');
const {CKEditorTranslationsPlugin} = require('@ckeditor/ckeditor5-dev-translations');

module.exports = {
  performance: {hints: false},
  entry: path.resolve(__dirname, 'src', 'ckeditor.js'),
  output: {
    library: 'CKEditor',
    path: path.resolve(__dirname, 'assets', 'ckeditor'),
    filename: 'ckeditor.js',
    libraryTarget: 'umd',
    libraryExport: 'default',
    clean: true,
  },

  optimization: {
    minimizer: [
      new TerserWebpackPlugin({
        terserOptions: {
          output: {
            comments: /^!/,
          },
        },
        extractComments: false,
      }),
    ],
  },

  plugins: [
    new CKEditorTranslationsPlugin({
      language: 'en',
      additionalLanguages: 'all',
    }),
  ],

  module: {
    rules: [
      {
        test: /\.svg$/,
        use: ['raw-loader'],
      },
      {
        test: /\.css$/,
        use: [
          {
            loader: 'style-loader',
            options: {
              injectType: 'singletonStyleTag',
              attributes: {
                'data-cke': true,
              },
            },
          },
          {
            loader: 'css-loader',
          },
          {
            loader: 'postcss-loader',
            options: {
              postcssOptions: styles.getPostCssConfig({
                themeImporter: {
                  themePath: require.resolve('@ckeditor/ckeditor5-theme-lark'),
                },
                minify: true,
              }),
            },
          },
        ],
      },
    ],
  },
};
