'use strict';

import gulp from 'gulp';
import concat from 'gulp-concat';
import uglify from 'gulp-uglify';
import uglifycss from 'gulp-uglifycss';
import yargs from 'yargs';
import upath from 'upath';
import cheerio from 'gulp-cheerio';
import svgSprite from 'gulp-svg-sprite';
import dartSass from 'sass';
import gulpSass from 'gulp-sass';
import postcss from 'gulp-postcss';
import autoprefixer from 'autoprefixer';

const sass = gulpSass(dartSass);

const { argv } = yargs
  .options({
    nodeModulesPath: {
      'description': '<path> pathe to node_modules directory',
      type: 'string',
      requiresArgs: true,
      required: false,
    }
  });

const nodeModulesPath = upath.normalizeSafe(argv.nodeModulesPath);

export const buildThemeAdminSvgSprite = function buildThemeAdminSvgSprite() {
  return gulp.src('assets/icons/*.svg')
    .pipe(cheerio({
      run: function ($) {
        $('[class]').removeAttr('class');
      },
      parserOptions: { xmlMode: true }
    }))

    .pipe(svgSprite({
      mode: {
        symbol: {
          sprite: "icons-sprite.svg"
        },
      }
    }))
    .pipe(gulp.dest('build/'));
}
buildThemeAdminSvgSprite.description = 'Build admin_default theme SVG sprite assets.';

export const buildThemeAdminJs = function buildThemeAdminJs() {
  const files = [
    'assets/js/jquery.min.js',
    'assets/js/ui/jquery.alerts.js',
    'assets/js/forms/forms.js',
    'assets/js/jquery.scrollTo-min.js',
    'assets/js/jquery-ui.js',
    upath.joinSafe(nodeModulesPath, '@tabler/core/dist/js/tabler.js'),
    upath.joinSafe(nodeModulesPath, 'apexcharts/dist/apexcharts.js'),
    upath.joinSafe(nodeModulesPath, 'tom-select/dist/js/tom-select.base.js'),
    'assets/js/fossbilling.js',
    'assets/js/ui/backToTop.js',
  ];

  return gulp.src(files)
    .pipe(concat('fossbilling-bundle.min.js'))
    .pipe(uglify())
    .pipe(gulp.dest('build/js'));
}
buildThemeAdminJs.description = 'Build admin_default theme JS assets.';

export const buildThemeAdminCSS = function buildThemeAdminCSS() {
  const files = [
    upath.joinSafe(nodeModulesPath, '@tabler/core/dist/css/tabler.css'),
    upath.joinSafe(nodeModulesPath, 'tom-select/dist/css/tom-select.bootstrap5.css'),
    'assets/scss/**/*.scss',
    // 'build/css/dark-icons-sprite.css',
    // 'build/css/dark-icons-23-sprite.css',
    // 'build/css/topnav-sprite.css',
  ];

  return gulp.src(files)
    .pipe(concat('fossbilling-bundle.min.css'))
    .pipe(postcss([
      autoprefixer()
    ]))
    .pipe(sass().on('error', sass.logError))
    .pipe(uglifycss())
    .pipe(gulp.dest('build/css'));
}
buildThemeAdminCSS.description = 'Build admin_default theme CSS assets.';

export const buildThemeAdminSprite = gulp.parallel(buildThemeAdminSvgSprite);
buildThemeAdminSprite.description = 'Build sprites.';

export const build = gulp.series(buildThemeAdminSprite, buildThemeAdminJs, buildThemeAdminCSS);
build.description = 'Build assets.';

gulp.task('admin-sprite', buildThemeAdminSprite);
gulp.task('admin-js', buildThemeAdminJs);
gulp.task('admin-css', buildThemeAdminCSS);

export default build;
