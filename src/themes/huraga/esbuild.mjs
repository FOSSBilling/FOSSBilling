import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve, join } from 'path';
import { writeFile } from 'fs/promises';
import { sassPlugin, postprocessCssFile } from '@fossbilling/frontend-build-utils/plugins';
import { ensureDir, copyAssets } from '@fossbilling/frontend-build-utils/helpers';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');

async function build() {
  console.log(`Building huraga theme (${isProduction ? 'production' : 'development'}) with esbuild ...`);

  const startTime = Date.now();

  try {
    const buildDir = resolve(__dirname, 'build');
    const jsDir = join(buildDir, 'js');
    const cssDir = join(buildDir, 'css');
    const imagesDir = join(buildDir, 'images');

    await ensureDir(jsDir);
    await ensureDir(cssDir);
    await ensureDir(imagesDir);

    // Build JavaScript
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/huraga.js')],
      bundle: true,
      outfile: join(jsDir, 'huraga.js'),
      platform: 'browser',
      target: 'es2018',
      format: 'esm',
      loader: {
        '.svg': 'dataurl',
        '.woff': 'file',
        '.woff2': 'file',
        '.ttf': 'file',
        '.eot': 'file'
      },
      define: {
        'process.env.NODE_ENV': isProduction ? '"production"' : '"development"'
      },
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info'
    });

    // Build theme SCSS
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/scss/huraga.scss')],
      bundle: true,
      outfile: join(cssDir, 'huraga.css'),
      loader: {
        '.svg': 'dataurl',
        '.woff': 'file',
        '.woff2': 'file',
        '.ttf': 'file',
        '.eot': 'file'
      },
      plugins: [sassPlugin(nodeModulesDir, isProduction)],
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info'
    });

    await postprocessCssFile(join(cssDir, 'huraga.css'), isProduction);

    // Build vendor CSS
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
      bundle: true,
      outfile: join(cssDir, 'vendor.css'),
      loader: {
        '.svg': 'dataurl',
        '.woff': 'file',
        '.woff2': 'file',
        '.ttf': 'file',
        '.eot': 'file'
      },
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info'
    });

    // Build markdown CSS
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/markdown.css')],
      bundle: true,
      outfile: join(cssDir, 'markdown.css'),
      loader: {
        '.svg': 'dataurl',
        '.woff': 'file',
        '.woff2': 'file',
        '.ttf': 'file',
        '.eot': 'file'
      },
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info'
    });

    // Copy static CSS assets (excluding vendor.css and markdown.css which are built above)
    const cssSrc = resolve(__dirname, 'assets/css');
    const cssDest = join(buildDir, 'css');
    await copyAssets(cssSrc, cssDest, {
      exclude: new Set(['vendor.css', 'markdown.css'])
    });

    // Copy image assets
    const imagesSrc = resolve(__dirname, 'assets/images');
    const imagesDest = imagesDir;
    await copyAssets(imagesSrc, imagesDest);

    // Generate manifest
    const manifest = {
      'themes/huraga/build/huraga.js': '/themes/huraga/build/js/huraga.js',
      'themes/huraga/build/vendor.css': '/themes/huraga/build/css/vendor.css',
      'themes/huraga/build/huraga.css': '/themes/huraga/build/css/huraga.css',
      'themes/huraga/build/markdown.css': '/themes/huraga/build/css/markdown.css'
    };

    await writeFile(
      join(buildDir, 'manifest.json'),
      JSON.stringify(manifest, null, 2)
    );

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`✓ Build complete in ${duration}s\n`);

  } catch (error) {
    console.error('✗ Build failed:', error);
    process.exit(1);
  }
}

async function watch() {
  console.log('Starting watch mode ...\n');

  const buildDir = resolve(__dirname, 'build');
  const jsDir = join(buildDir, 'js');
  const cssDir = join(buildDir, 'css');
  const imagesDir = join(buildDir, 'images');

  await ensureDir(jsDir);
  await ensureDir(cssDir);
  await ensureDir(imagesDir);

  // Copy static assets initially
  const cssSrc = resolve(__dirname, 'assets/css');
  const cssDest = join(buildDir, 'css');
  await copyAssets(cssSrc, cssDest, {
    exclude: new Set(['vendor.css', 'markdown.css'])
  });

  const imagesSrc = resolve(__dirname, 'assets/images');
  const imagesDest = imagesDir;
  await copyAssets(imagesSrc, imagesDest);

  // Create esbuild contexts for incremental rebuilds
  const jsContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/huraga.js')],
    bundle: true,
    outfile: join(jsDir, 'huraga.js'),
    platform: 'browser',
    target: 'es2018',
    format: 'esm',
    loader: {
      '.svg': 'dataurl',
      '.woff': 'file',
      '.woff2': 'file',
      '.ttf': 'file',
      '.eot': 'file'
    },
    define: {
      'process.env.NODE_ENV': isProduction ? '"production"' : '"development"'
    },
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  const themeCssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/scss/huraga.scss')],
    bundle: true,
    outfile: join(cssDir, 'huraga.css'),
    loader: {
      '.svg': 'dataurl',
      '.woff': 'file',
      '.woff2': 'file',
      '.ttf': 'file',
      '.eot': 'file'
    },
    plugins: [sassPlugin(nodeModulesDir, isProduction)],
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  const vendorCssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
    bundle: true,
    outfile: join(cssDir, 'vendor.css'),
    loader: {
      '.svg': 'dataurl',
      '.woff': 'file',
      '.woff2': 'file',
      '.ttf': 'file',
      '.eot': 'file'
    },
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  const markdownCssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/css/markdown.css')],
    bundle: true,
    outfile: join(cssDir, 'markdown.css'),
    loader: {
      '.svg': 'dataurl',
      '.woff': 'file',
      '.woff2': 'file',
      '.ttf': 'file',
      '.eot': 'file'
    },
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  // Watch for changes using esbuild's native watch
  await Promise.all([
    jsContext.watch(),
    themeCssContext.watch(),
    vendorCssContext.watch(),
    markdownCssContext.watch()
  ]);

  // Post-process CSS after theme CSS builds
  themeCssContext.watch().then(() => {
    postprocessCssFile(join(cssDir, 'huraga.css'), isProduction);
  });

  console.log('✓ Watching for changes ...\n');

  // Keep the process running
  await new Promise(() => {});
}

const args = process.argv.slice(2);
if (args.includes('--watch')) {
  watch();
} else {
  build();
}
