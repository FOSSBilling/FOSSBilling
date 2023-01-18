'use strict';

import gulp from 'gulp';
import clean from 'gulp-clean';
import yargs from 'yargs';
import upath from "upath";

const {argv} = yargs
  .options({
    nodeModulesPath: {
      'description': '<path> path to node_modules directory',
      type: 'string',
      requiresArgs: true,
      required: false,
    }
  });

const nodeModulesPath = upath.normalizeSafe(argv.nodeModulesPath);
export const copyCKEditorBuild = function copyCKEditorBuild() {
  return gulp.src(upath.joinSafe(nodeModulesPath, '@ckeditor/ckeditor5-build-classic/build/**/*'))
    .pipe(gulp.dest('./assets/ckeditor'));
}
copyCKEditorBuild.description = 'copying built files to assets.';

const cleanBuild = function cleanBuild() {
  return gulp.src('./assets/ckeditor/*', {read: false})
    .pipe(clean());
}

export const build = gulp.series(cleanBuild, copyCKEditorBuild);
build.description = 'Build assets.';

gulp.task('clean-build', cleanBuild);
gulp.task('ckeditor', copyCKEditorBuild);

export default build;
