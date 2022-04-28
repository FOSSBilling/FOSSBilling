'use strict';

import gulp from 'gulp';
import concat from 'gulp-concat';
import uglify from 'gulp-uglify';
import uglifycss from 'gulp-uglifycss';
import yargs from 'yargs';
import upath from 'upath';

const { argv } = yargs
  .options({
    nodeModulesPath: {
      'description': '<path> path to node_modules directory',
      type: 'string',
      requiresArgs: true,
      required: false,
    }
  });

const nodeModulesPath = upath.normalizeSafe(argv.nodeModulesPath);

export const buildThemeBootstrapJs = function buildThemeBootstrapJs() {
  const files = [
    upath.joinSafe(nodeModulesPath, 'jquery/dist/jquery.js'),
    upath.joinSafe(nodeModulesPath, '/bootstrap/dist/js/bootstrap.js'),
    upath.joinSafe(nodeModulesPath, '/bootstrap-markdown/js/bootstrap-markdown.js'),
    'assets/js/boxbilling.js'
  ];

  return gulp.src(files)
    .pipe(concat('boxbilling-bundle.min.js'))
    .pipe(uglify())
    .pipe(gulp.dest('assets/js'));
}
buildThemeBootstrapJs.description = 'Build theme Bootstrap JS assets.';

export const buildThemeBootstrapCSS = function buildThemeBootstrapCSS() {
  const files = [
    upath.joinSafe(nodeModulesPath, 'bootstrap/dist/css/bootstrap.css'),
    upath.joinSafe(nodeModulesPath, '@fortawesome/fontawesome-free/css/fontawesome.css'),
    upath.joinSafe(nodeModulesPath, '@fortawesome/fontawesome-free/css/solid.css'),
    upath.joinSafe(nodeModulesPath, 'bootstrap-markdown/css/bootstrap-markdown.min.css')
  ];

  return gulp.src(files)
    .pipe(concat('boxbilling-bundle.min.css'))
    .pipe(uglifycss())
    .pipe(gulp.dest('assets/css'));
}
buildThemeBootstrapCSS.description = 'Build theme Bootstrap CSS assets.';

export const buildThemeBootstrapFonts = function buildThemeBootstrapFonts() {
  return gulp.src('../../../node_modules/@fortawesome/fontawesome-free/webfonts/*')
    .pipe(gulp.dest('assets/webfonts'));
}
buildThemeBootstrapCSS.description = 'Build theme Bootstrap fonts.';

export const build = gulp.parallel(buildThemeBootstrapJs, buildThemeBootstrapCSS, buildThemeBootstrapFonts);
build.description = 'Build assets.';

gulp.task('bootstrap-js', buildThemeBootstrapJs);
gulp.task('bootstrap-css', buildThemeBootstrapCSS);
gulp.task('bootstrap-fonts', buildThemeBootstrapFonts);

export default build;
