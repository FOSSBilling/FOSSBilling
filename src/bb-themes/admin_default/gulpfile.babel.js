'use strict';

import gulp from 'gulp';
import concat from 'gulp-concat';
import uglify from 'gulp-uglify';
import uglifycss from 'gulp-uglifycss';
import yargs from 'yargs';
import upath from 'upath';
import spritesmith from 'gulp.spritesmith';

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

export const buildThemeAdminSpriteDark = function buildThemeAdminSpriteDark() {
  const sprite = gulp.src('images/icons/dark/*.png')
    .pipe(spritesmith({
      imgName: 'dark-icons-sprite.png',
      imgPath: '../sprites/dark-icons-sprite.png',
      cssName: 'dark-icons-sprite.css',
      cssOpts: {
        cssSelector: image => {
          return '.sprite-' + image.name;
        }
      }
    }));

  sprite.img.pipe(gulp.dest('build/sprites'));
  sprite.css.pipe(gulp.dest('build/css'));

  return sprite;
}
buildThemeAdminSpriteDark.description = 'Build theme Admin sprite assets.';

export const buildThemeAdminSpriteTop = function buildThemeAdminSpriteTop() {
  const sprite = gulp.src('images/icons/topnav/*.png')
    .pipe(spritesmith({
      imgName: 'topnav-sprite.png',
      imgPath: '../sprites/topnav-sprite.png',
      cssName: 'topnav-sprite.css',
      cssOpts: {
        cssSelector: image => {
          return '.sprite-topnav-' + image.name;
        }
      }
    }));

  sprite.img.pipe(gulp.dest('build/sprites'));
  sprite.css.pipe(gulp.dest('build/css'));

  return sprite;
}
buildThemeAdminSpriteTop.description = 'Build theme Admin sprite assets.';

export const buildThemeAdminSpriteMiddle = function buildThemeAdminSpriteMiddle() {
  const sprite = gulp.src('images/icons/middlenav/used/*.png')
    .pipe(spritesmith({
      imgName: 'dark-icons-23-sprite.png',
      imgPath: '../sprites/dark-icons-23-sprite.png',
      cssName: 'dark-icons-23-sprite.css',
      cssOpts: {
        cssSelector: image => {
          return '.sprite-23-' + image.name;
        }
      }
    }));

  sprite.img.pipe(gulp.dest('build/sprites'));
  sprite.css.pipe(gulp.dest('build/css'));

  return sprite;
}
buildThemeAdminSpriteTop.description = 'Build theme Admin sprite assets.';

export const buildThemeAdminJs = function buildThemeAdminJs() {
  const files = [
    'assets/js/jquery.min.js',
    'assets/js/ui/jquery.alerts.js',
    'assets/js/ui/jquery.tipsy.js',
    'assets/js/jquery.collapsible.min.js',
    'assets/js/forms/forms.js',
    'assets/js/jquery.ToTop.js',
    'assets/js/jquery.scrollTo-min.js',
    'assets/js/jquery-ui.js',
  ];

  return gulp.src(files)
    .pipe(concat('boxbilling-bundle.min.js'))
    .pipe(uglify())
    .pipe(gulp.dest('build/js'));
}
buildThemeAdminJs.description = 'Build Admin theme JS assets.';

export const buildThemeAdminCSS = function buildThemeAdminCSS() {
  const files = [
    'assets/css/reset.css',
    'assets/css/jquery-ui.css',
    'assets/css/bb.css',
    'assets/css/dataTable.css',
    'assets/css/ui_custom.css',
    'assets/css/main.css',
    'build/css/dark-icons-sprite.css',
    'build/css/dark-icons-23-sprite.css',
    'build/css/topnav-sprite.css',
  ];

  return gulp.src(files)
    .pipe(concat('boxbilling-bundle.min.css'))
    .pipe(uglifycss())
    .pipe(gulp.dest('build/css'));
}
buildThemeAdminCSS.description = 'Build Bootstrap theme CSS assets.';

export const buildThemeAdminSprite = gulp.parallel(buildThemeAdminSpriteDark, buildThemeAdminSpriteTop, buildThemeAdminSpriteMiddle);
buildThemeAdminSprite.description = 'Build sprites.';

export const build = gulp.series(buildThemeAdminSprite, buildThemeAdminJs, buildThemeAdminCSS);
build.description = 'Build assets.';

gulp.task('admin-sprite', buildThemeAdminSprite);
gulp.task('admin-js', buildThemeAdminJs);
gulp.task('admin-css', buildThemeAdminCSS);

export default build;
