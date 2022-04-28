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
  return gulp.src('src/bb-themes/admin_default/gulpfile.babel.js', { read: false })
    .pipe(chug({ args: config, tasks: 'build' }));
}
buildThemeAdmin.description = 'Build theme Admin assets.';

export const buildThemeBootstrap = function buildThemeBootstrap() {
  return gulp.src('src/bb-themes/bootstrap/gulpfile.babel.js', { read: false })
    .pipe(chug({ args: config, tasks: 'build' }));
}
buildThemeBootstrap.description = 'Build theme Bootstrap assets.';

export const build = gulp.parallel(buildThemeAdmin, buildThemeBootstrap);
build.description = 'Build assets.';

gulp.task('bootstrap', buildThemeAdmin);
gulp.task('bootstrap', buildThemeBootstrap);

export default build;
