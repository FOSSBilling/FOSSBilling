'use strict';

import gulp from 'gulp';
import chug from 'gulp-chug';
import yargs from 'yargs';

const { argv } = yargs
  .options({
    nodeModulesPath: {
      'description': '<path> path to node_modules directory',
      type: 'string',
      requiresArgs: true,
      required: false,
    }
  });

const config = [
  '--node-modules-path',
  argv.nodeModulesPath || '../../../node_modules',
];

export const buildThemeAdmin = function buildThemeAdmin() {
  return gulp.src('src/themes/admin_default/gulpfile.babel.js', { read: false })
    .pipe(chug({ args: config, tasks: 'build' }));
}
buildThemeAdmin.description = 'Build theme Admin assets.';

export const buildWysiwyg = function buildWysiwyg() {
  return gulp.src('src/modules/Wysiwyg/gulpfile.babel.js', { read: false })
    .pipe(chug({ args: config, tasks: 'build' }));
}
buildWysiwyg.description = 'Build Wysiwyg assets.';

export const build = gulp.parallel(buildThemeAdmin);
build.description = 'Build assets.';

gulp.task('admin_default', buildThemeAdmin);
gulp.task('wysiwyg', buildWysiwyg);

export default build;
