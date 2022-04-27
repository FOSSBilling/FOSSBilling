import gulp from 'gulp';
import chug from 'gulp-chug';
import yargs from 'yargs';

const { argv } = yargs
  .options({
    nodeModulesPath: {
      'description': '<path> pathe to node_modules directory',
      type: 'string',
      requiresArgs: true,
      required: false,
    }
  });

const config = [
  '--node-modules-path',
  argv.nodeModulesPath || '../../../node_modules',
];

export const buildThemeBootstrap = function buildThemeBootstrap() {
  return gulp.src('src/bb-themes/bootstrap/gulpfile.babel.js', { read: false })
    .pipe(chug({ args: config, tasks: 'build' }));
}
buildThemeBootstrap.description = 'Build theme Bootstrap assets.';

export const build = gulp.parallel(buildThemeBootstrap);
build.description = 'Build assets.';

gulp.task('bootstrap', buildThemeBootstrap);

export default build;
