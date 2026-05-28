import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve, join } from 'path';
import { writeFile } from 'fs/promises';
import {
  copyAssets,
  ensureDir,
  postprocessCssFile,
  purgeCssFile,
  removeDirContents,
  sassPlugin,
  sharedLoaders,
} from '../../../frontend/tools/esbuild-helpers.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');

async function cleanBuild() {
  try {
    await removeDirContents(resolve(__dirname, 'assets/build'));
  } catch (error) {
    console.error('Failed to clean build directory:', error);
  }
}

async function build() {
  console.log(`Building huraga theme (${isProduction ? 'production' : 'development'}) with esbuild ...`);

  const startTime = Date.now();

  try {
    await cleanBuild();

    const buildDir = resolve(__dirname, 'assets/build');
    const jsDir = join(buildDir, 'js');
    const cssDir = join(buildDir, 'css');
    const imgDir = join(buildDir, 'img');

    await ensureDir(jsDir);
    await ensureDir(cssDir);
    await ensureDir(imgDir);

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/huraga.js')],
      bundle: true,
      outfile: join(jsDir, 'huraga.js'),
      platform: 'browser',
      target: 'es2018',
      loader: sharedLoaders,
      define: { 'process.env.NODE_ENV': isProduction ? '"production"' : '"development"' },
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info',
      treeShaking: true,
      legalComments: 'none',
      drop: isProduction ? ['console', 'debugger'] : []
    });

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/scss/huraga.scss')],
      bundle: true,
      outfile: join(cssDir, 'huraga.css'),
      loader: sharedLoaders,
      plugins: [sassPlugin(nodeModulesDir, isProduction)],
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info',
      define: { 'process.env.NODE_ENV': isProduction ? '"production"' : '"development"' },
      treeShaking: true,
      legalComments: 'none'
    });

    await postprocessCssFile(join(cssDir, 'huraga.css'), isProduction);
    await purgeCssFile(join(cssDir, 'huraga.css'), {
      themePath: __dirname,
      enabled: isProduction,
      area: 'client',
    });

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
      bundle: true,
      outfile: join(cssDir, 'vendor.css'),
      loader: sharedLoaders,
      plugins: [sassPlugin(nodeModulesDir, isProduction)],
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info'
    });

    await purgeCssFile(join(cssDir, 'vendor.css'), {
      themePath: __dirname,
      enabled: isProduction,
      area: 'client',
    });

    await copyAssets(resolve(__dirname, 'assets/css'), cssDir, { exclude: new Set(['vendor.css']) });
    await copyAssets(resolve(__dirname, 'assets/img'), imgDir);

    const manifest = {
      'build/huraga.js': '/themes/huraga/assets/build/js/huraga.js',
      'build/vendor.css': '/themes/huraga/assets/build/css/vendor.css',
      'build/huraga.css': '/themes/huraga/assets/build/css/huraga.css'
    };

    await writeFile(join(buildDir, 'manifest.json'), JSON.stringify(manifest, null, 2));

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`✓ Build complete in ${duration}s\n`);

  } catch (error) {
    console.error('✗ Build failed:', error);
    process.exit(1);
  }
}

async function watch() {
  console.log('Starting watch mode ...\n');

  const buildDir = resolve(__dirname, 'assets/build');
  const jsDir = join(buildDir, 'js');
  const cssDir = join(buildDir, 'css');
  const imgDir = join(buildDir, 'img');

  await ensureDir(jsDir);
  await ensureDir(cssDir);
  await ensureDir(imgDir);

  await copyAssets(resolve(__dirname, 'assets/css'), cssDir, { exclude: new Set(['vendor.css']) });
  await copyAssets(resolve(__dirname, 'assets/img'), imgDir);

  const jsContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/huraga.js')],
    bundle: true,
    outfile: join(jsDir, 'huraga.js'),
    platform: 'browser',
    target: 'es2018',
    loader: sharedLoaders,
    define: { 'process.env.NODE_ENV': isProduction ? '"production"' : '"development"' },
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  const themeCssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/scss/huraga.scss')],
    bundle: true,
    outfile: join(cssDir, 'huraga.css'),
    loader: sharedLoaders,
    plugins: [sassPlugin(nodeModulesDir, isProduction)],
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  const vendorCssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
    bundle: true,
    outfile: join(cssDir, 'vendor.css'),
    loader: sharedLoaders,
    plugins: [sassPlugin(nodeModulesDir, isProduction)],
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  await Promise.all([
    jsContext.watch(),
    themeCssContext.watch(),
    vendorCssContext.watch()
  ]);

  await postprocessCssFile(join(cssDir, 'huraga.css'), isProduction);

  console.log('✓ Watching for changes ...\n');
  process.stdin.resume();
}

const args = process.argv.slice(2);
if (args.includes('--watch')) {
  watch();
} else {
  build();
}
