'use strict';

import gulp from 'gulp';
import webpack from 'webpack';
import webpackConfig from './webpack.config.js';

export const buildCKEditor = () => {
  return new Promise((resolve, reject) => {
    webpack(webpackConfig, (err, stats) => {
      if (err) {
        return reject(err)
      }
      if (stats.hasErrors()) {
        return reject(new Error(stats.compilation.errors.join('\n')))
      }
      resolve()
    })
  })
}
buildCKEditor.description = 'Build ckeditor';

export const build = gulp.series([buildCKEditor]);
build.description = 'Build editor';

gulp.task('ckeditor', buildCKEditor);

export default build;
