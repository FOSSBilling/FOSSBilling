import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve, join } from 'path';
import { writeFile, copyFile } from 'fs/promises';
import { sassPlugin, postprocessCssFile } from '@fossbilling/frontend-build-utils/plugins';
import { ensureDir, copyAssets, removeDirContents } from '@fossbilling/frontend-build-utils/helpers';
import { purgeCssFile } from '@fossbilling/frontend-build-utils/purgecss-plugin.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');

async function cleanBuild() {
  try {
    const buildDir = resolve(__dirname, 'assets/build');
    await removeDirContents(buildDir);
  } catch (error) {
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

    // Build JavaScript with improved optimization
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/huraga.js')],
      bundle: true,
      outfile: join(jsDir, 'huraga.js'),
      platform: 'browser',
      target: 'es2018',
      loader: {
        '.svg': 'file',
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
      logLevel: 'info',
      // Add tree-shaking and optimization settings
      treeShaking: true,
      legalComments: 'none',
      drop: isProduction ? ['console', 'debugger'] : []
    });

    // Build theme SCSS with improved optimization
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/scss/huraga.scss')],
      bundle: true,
      outfile: join(cssDir, 'huraga.css'),
      loader: {
        '.svg': 'file',
        '.woff': 'file',
        '.woff2': 'file',
        '.ttf': 'file',
        '.eot': 'file'
      },
      plugins: [sassPlugin(nodeModulesDir, isProduction)],
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info',
      // Add CSS optimization settings
      define: {
        'process.env.NODE_ENV': isProduction ? '"production"' : '"development"'
      }
    });

    await postprocessCssFile(join(cssDir, 'huraga.css'), isProduction);
    await purgeCssFile(join(cssDir, 'huraga.css'), __dirname, isProduction, true);

    // Build vendor CSS
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
      bundle: true,
      outfile: join(cssDir, 'vendor.css'),
      loader: {
        '.svg': 'file',
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

    await purgeCssFile(join(cssDir, 'vendor.css'), __dirname, isProduction, true);

    // Build markdown CSS
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/markdown.css')],
      bundle: true,
      outfile: join(cssDir, 'markdown.css'),
      loader: {
        '.svg': 'file',
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
    await copyAssets(resolve(__dirname, 'assets/img'), imgDir);

    // Copy favicon
    await copyFile(
      resolve(__dirname, 'assets/favicon.ico'),
      join(buildDir, 'favicon.ico')
    );

    // Generate manifest
    const manifest = {
      'build/huraga.js': '/themes/huraga/assets/build/js/huraga.js',
      'build/vendor.css': '/themes/huraga/assets/build/css/vendor.css',
      'build/huraga.css': '/themes/huraga/assets/build/css/huraga.css',
      'build/markdown.css': '/themes/huraga/assets/build/css/markdown.css'
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

  const buildDir = resolve(__dirname, 'assets/build');
  const jsDir = join(buildDir, 'js');
  const cssDir = join(buildDir, 'css');
  const imgDir = join(buildDir, 'img');

  await ensureDir(jsDir);
  await ensureDir(cssDir);
  await ensureDir(imgDir);

  // Copy static assets initially
  const cssSrc = resolve(__dirname, 'assets/css');
  const cssDest = join(buildDir, 'css');
  await copyAssets(cssSrc, cssDest, {
    exclude: new Set(['vendor.css', 'markdown.css'])
  });

  await copyAssets(resolve(__dirname, 'assets/img'), imgDir);

  // Copy favicon
  await copyFile(
    resolve(__dirname, 'assets/favicon.ico'),
    join(buildDir, 'favicon.ico')
  );

  // Create esbuild contexts for incremental rebuilds
  const jsContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/huraga.js')],
    bundle: true,
    outfile: join(jsDir, 'huraga.js'),
    platform: 'browser',
    target: 'es2018',
    loader: {
      '.svg': 'file',
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
      '.svg': 'file',
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
      '.svg': 'file',
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

  const markdownCssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/css/markdown.css')],
    bundle: true,
    outfile: join(cssDir, 'markdown.css'),
    loader: {
      '.svg': 'file',
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

  // Keep the process running while in watch mode
  process.stdin.resume();
}

const args = process.argv.slice(2);
if (args.includes('--watch')) {
  watch();
} else {
  build();
}
