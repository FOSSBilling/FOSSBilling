import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, join, resolve } from 'path';
import { writeFile } from 'fs/promises';
import {
  ensureDir,
  postprocessCssFile,
  purgeCssFile,
  removeDirContents,
  sassPlugin,
  sharedLoaders,
} from '../../../frontend/tools/esbuild-helpers.mjs';
import { buildIconSprite } from '../../../frontend/tools/icon-sprite.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');
const adminLoaders = { ...sharedLoaders, '.svg': 'dataurl' };

async function generateManifest(buildDir) {
  const manifest = {
    'build/fossbilling.js': '/themes/admin_default/assets/build/js/fossbilling.js',
    'build/vendor.css': '/themes/admin_default/assets/build/css/vendor.css',
    'build/fossbilling.css': '/themes/admin_default/assets/build/css/fossbilling.css',
    'build/symbol/icons-sprite.svg': '/themes/admin_default/assets/build/symbol/icons-sprite.svg'
  };

  await writeFile(join(buildDir, 'manifest.json'), JSON.stringify(manifest, null, 2));
}

async function cleanBuild() {
  try {
    await removeDirContents(resolve(__dirname, 'assets/build'));
  } catch (error) {
    console.error('Failed to clean build directory:', error);
  }
}

async function build() {
  console.log(`Building admin_default theme (${isProduction ? 'production' : 'development'}) with esbuild ...`);

  const startTime = Date.now();

  try {
    await cleanBuild();

    const buildDir = resolve(__dirname, 'assets/build');
    const jsDir = join(buildDir, 'js');
    const cssDir = join(buildDir, 'css');
    const symbolDir = join(buildDir, 'symbol');

    await ensureDir(jsDir);
    await ensureDir(cssDir);
    await ensureDir(symbolDir);

    console.log('Generating icon sprite...');
    await buildIconSprite({
      manifestPath: resolve(__dirname, 'icon-manifest.json'),
      outputDir: symbolDir,
      sources: [
        { name: 'custom', dir: resolve(__dirname, 'custom-icons'), variant: 'custom' },
        { name: '@tabler/icons', dir: resolve(nodeModulesDir, '@tabler/icons/icons') },
      ],
    });

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/scss/fossbilling.scss')],
      bundle: true,
      outfile: join(cssDir, 'fossbilling.css'),
      plugins: [sassPlugin(nodeModulesDir, isProduction)],
      loader: adminLoaders,
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info'
    });

    await postprocessCssFile(join(cssDir, 'fossbilling.css'), isProduction);
    await purgeCssFile(join(cssDir, 'fossbilling.css'), {
      themePath: __dirname,
      enabled: isProduction,
      area: 'admin',
      additionalStandardSafelist: [/^flag-country-/, /^clr-/],
    });

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
      bundle: true,
      outfile: join(cssDir, 'vendor.css'),
      loader: adminLoaders,
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info'
    });

    await purgeCssFile(join(cssDir, 'vendor.css'), {
      themePath: __dirname,
      enabled: isProduction,
      area: 'admin',
      additionalStandardSafelist: [/^flag-country-/, /^clr-/],
    });

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/fossbilling.js')],
      bundle: true,
      outfile: join(jsDir, 'fossbilling.js'),
      platform: 'browser',
      target: 'es2018',
      loader: adminLoaders,
      define: {
        'process.env.NODE_ENV': isProduction ? '"production"' : '"development"'
      },
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info'
    });

    await generateManifest(buildDir);

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`Build complete in ${duration}s\n`);

  } catch (error) {
    console.error('Build failed:', error);
    process.exit(1);
  }
}

build();
