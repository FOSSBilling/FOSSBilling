import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve, join, basename } from 'path';
import { readFile, readdir, writeFile } from 'fs/promises';
import { sassPlugin, postprocessCssFile } from '@fossbilling/frontend-build-utils/plugins';
import { ensureDir, removeDirContents } from '@fossbilling/frontend-build-utils/helpers';
import { purgeCssFile } from '@fossbilling/frontend-build-utils/purgecss-plugin.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');

async function generateManifest() {
  const manifest = {};

  manifest['build/fossbilling.js'] =
    '/themes/admin_default/assets/build/js/fossbilling.js';
  manifest['build/vendor.css'] =
    '/themes/admin_default/assets/build/css/vendor.css';
  manifest['build/fossbilling.css'] =
    '/themes/admin_default/assets/build/css/fossbilling.css';

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

  const files = await readdir(iconsDir);
  const svgFiles = files.filter(f => f.endsWith('.svg'));

  if (svgFiles.length === 0) {
    console.error('No SVG files found in assets/icons');
    return;
  }

  for (const file of svgFiles) {
    const filePath = join(iconsDir, file);
    const content = await readFile(filePath, 'utf8');
    spriter.add(filePath, file, content);
  }

  const result = await new Promise((resolve, reject) => {
    spriter.compile((error, result) => {
      if (error) reject(error);
      else resolve(result);
    });
  });

  await writeFile(join(outputDir, 'icons-sprite.svg'), result.symbol.sprite.contents);
}

async function cleanBuild() {
  try {
    const buildDir = resolve(__dirname, 'assets/build');
    await removeDirContents(buildDir);
  } catch (error) {
    // Cleanup failures are non-fatal, but we log them for debugging purposes.
    console.error('Failed to clean build directory:', error);
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

    await generateSvgSprite();

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
    await purgeCssFile(resolve(__dirname, 'assets/build/css/fossbilling.css'), __dirname, isProduction);

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

    await purgeCssFile(resolve(__dirname, 'assets/build/css/vendor.css'), __dirname, isProduction);

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/fossbilling.js')],
      bundle: true,
      outfile: resolve(__dirname, 'assets/build/js/fossbilling.js'),
      platform: 'browser',
      target: 'es2018',
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

    await generateManifest();

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`Build complete in ${duration}s\n`);

  } catch (error) {
    console.error('Build failed:', error);
    process.exit(1);
  }
}

build();
