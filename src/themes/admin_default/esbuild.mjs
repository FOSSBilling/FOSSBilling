import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve, relative, join, basename } from 'path';
import { readFile, readdir, copyFile, writeFile } from 'fs/promises';
import { sassPlugin, postprocessCssFile } from '@fossbilling/frontend-build-utils/plugins';
import { ensureDir, copyAssets, removeDirContents } from '@fossbilling/frontend-build-utils/helpers';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');
const jqueryShim = resolve(rootDir, 'frontend-build-utils/jquery-shim.js');

async function generateManifest() {
  const manifest = {};

  manifest['build/fossbilling.js'] =
    '/themes/admin_default/assets/build/js/fossbilling.js';
  manifest['build/vendor.css'] =
    '/themes/admin_default/assets/build/css/vendor.css';
  manifest['build/fossbilling.css'] =
    '/themes/admin_default/assets/build/css/fossbilling.css';

  const imagesSrc = resolve(__dirname, 'assets/images');
  const imagesDest = resolve(__dirname, 'assets/build/images');

  try {
    await ensureDir(imagesDest);
    const imageFiles = await readdir(imagesSrc);

    for (const file of imageFiles) {
      if (file.match(/\.(svg|png|jpg|jpeg|gif|ico)$/)) {
        await copyFile(
          join(imagesSrc, file),
          join(imagesDest, file)
        );

        const outputPath = `build/images/${file}`;
        const publicPath = `/themes/admin_default/assets/build/images/${file}`;
        manifest[outputPath] = publicPath;
      }
    }
  } catch (error) {
    if (error.code !== 'ENOENT') throw error;
  }

  manifest['build/symbol/icons-sprite.svg'] =
    '/themes/admin_default/assets/build/symbol/icons-sprite.svg';

  const manifestPath = resolve(__dirname, 'assets/build/manifest.json');
  await writeFile(manifestPath, JSON.stringify(manifest, null, 2));
}

async function generateSvgSprite() {
  const { default: SVGSpriter } = await import('svg-sprite');

  const iconsDir = resolve(__dirname, 'assets/icons');
  const outputDir = resolve(__dirname, 'assets/build/symbol');

  await ensureDir(outputDir);

  const spriter = new SVGSpriter({
    mode: {
      symbol: {
        dest: '.',
        sprite: 'icons-sprite.svg',
        example: false
      }
    },
    shape: {
      id: {
        generator: (name) => basename(name, '.svg')
      }
    }
  });

  try {
    const files = await readdir(iconsDir);
    const svgFiles = files.filter(f => f.endsWith('.svg'));

    if (svgFiles.length === 0) {
      console.error('✗ No SVG files found in assets/icons');
      return;
    }

    for (const file of svgFiles) {
      const filePath = join(iconsDir, file);
      const content = await readFile(filePath, 'utf8');
      spriter.add(filePath, file, content);
    }

    spriter.compile((error, result) => {
      if (error) throw error;
      writeFile(join(outputDir, 'icons-sprite.svg'), result.symbol.sprite.contents);
    });
  } catch (error) {
    if (error.code === 'ENOENT') {
      console.error('✗ Icons directory not found');
    } else {
      throw error;
    }
  }
}

async function copyStaticAssets() {
  const fontsSrc = resolve(__dirname, 'assets/fonts');
  const fontsDest = resolve(__dirname, 'assets/build/fonts');
  await copyAssets(fontsSrc, fontsDest);

  const imagesSrc = resolve(__dirname, 'assets/images');
  const imagesDest = resolve(__dirname, 'assets/build/images');
  await copyAssets(imagesSrc, imagesDest);
}

async function cleanBuild() {
  try {
    const buildDir = resolve(__dirname, 'assets/build');
    await removeDirContents(buildDir);
  } catch (error) {
    // Ignore errors
  }
}

async function build() {
  console.log(`Building admin_default theme (${isProduction ? 'production' : 'development'}) with esbuild ...`);

  const startTime = Date.now();

  try {
    await cleanBuild();

    await ensureDir(resolve(__dirname, 'assets/build/js'));
    await ensureDir(resolve(__dirname, 'assets/build/css'));
    await ensureDir(resolve(__dirname, 'assets/build/symbol'));
    await ensureDir(resolve(__dirname, 'assets/build/images'));

    await Promise.all([
      generateSvgSprite(),
      copyStaticAssets()
    ]);

    // Build SCSS
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/scss/fossbilling.scss')],
      bundle: true,
      outfile: resolve(__dirname, 'assets/build/css/fossbilling.css'),
      plugins: [sassPlugin(nodeModulesDir, isProduction)],
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

    await postprocessCssFile(resolve(__dirname, 'assets/build/css/fossbilling.css'), isProduction);

    // Build vendor CSS
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
      bundle: true,
      outfile: resolve(__dirname, 'assets/build/css/vendor.css'),
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

    // Build JavaScript
    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/fossbilling.js')],
      bundle: true,
      outfile: resolve(__dirname, 'assets/build/js/fossbilling.js'),
      platform: 'browser',
      target: 'es2018',
      inject: [jqueryShim],
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
      external: [
        '../../admin_default/assets/js/tomselect',
        '../../admin_default/assets/js/fossbilling'
      ],
      logLevel: 'info'
    });

    await generateManifest();

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`✓ Build complete in ${duration}s\n`);

  } catch (error) {
    console.error('✗ Build failed:', error);
    process.exit(1);
  }
}

async function watch() {
  console.log('Starting watch mode ...\n');

  // Create esbuild contexts for incremental rebuilds
  const scssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/scss/fossbilling.scss')],
    bundle: true,
    outfile: resolve(__dirname, 'assets/build/css/fossbilling.css'),
    plugins: [sassPlugin(nodeModulesDir, isProduction)],
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

  const vendorCssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
    bundle: true,
    outfile: resolve(__dirname, 'assets/build/css/vendor.css'),
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

  const jsContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/fossbilling.js')],
    bundle: true,
    outfile: resolve(__dirname, 'assets/build/js/fossbilling.js'),
    platform: 'browser',
    target: 'es2018',
    inject: [jqueryShim],
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
    external: [
      '../../admin_default/assets/js/tomselect',
      '../../admin_default/assets/js/fossbilling'
    ],
    logLevel: 'info'
  });

  // Initial build to set up static assets
  await cleanBuild();
  await ensureDir(resolve(__dirname, 'assets/build/js'));
  await ensureDir(resolve(__dirname, 'assets/build/css'));
  await ensureDir(resolve(__dirname, 'assets/build/symbol'));
  await ensureDir(resolve(__dirname, 'assets/build/images'));

  await Promise.all([
    generateSvgSprite(),
    copyStaticAssets()
  ]);

  // Watch for changes using esbuild's native watch
  await Promise.all([
    scssContext.watch(),
    vendorCssContext.watch(),
    jsContext.watch()
  ]);

  // Post-process CSS after SCSS builds
  scssContext.watch().then(() => {
    postprocessCssFile(resolve(__dirname, 'assets/build/css/fossbilling.css'), isProduction);
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
