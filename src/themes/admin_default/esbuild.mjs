import { fileURLToPath } from 'url';
import { dirname, join, resolve } from 'path';
import {
  buildCssFile,
  buildJsFile,
  getThemeBuildPaths,
  prepareThemeBuildDirs,
  sharedLoaders,
  writeAssetManifest,
} from '../../../frontend/tools/esbuild-helpers.mjs';
import { buildIconSprite } from '../../../frontend/tools/icon-sprite.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');
const adminLoaders = { ...sharedLoaders, '.svg': 'dataurl' };

async function build() {
  console.log(`Building admin_default theme (${isProduction ? 'production' : 'development'}) with esbuild ...`);

  const startTime = Date.now();

  try {
    const paths = getThemeBuildPaths(__dirname);
    await prepareThemeBuildDirs(paths);

    console.log('Generating icon sprite...');
    await buildIconSprite({
      manifestPath: resolve(__dirname, 'icon-manifest.json'),
      outputDir: paths.symbolDir,
      sources: [
        { name: 'custom', dir: resolve(__dirname, 'custom-icons'), variant: 'custom' },
        { name: '@tabler/icons', dir: resolve(nodeModulesDir, '@tabler/icons/icons') },
      ],
    });

    await buildCssFile({
      entryPoint: resolve(__dirname, 'assets/scss/fossbilling.scss'),
      outfile: join(paths.cssDir, 'fossbilling.css'),
      nodeModulesDir,
      isProduction,
      loader: adminLoaders,
      themePath: __dirname,
      purge: {
        area: 'admin',
        additionalStandardSafelist: [/^flag-country-/, /^clr-/],
      },
    });

    await buildCssFile({
      entryPoint: resolve(__dirname, 'assets/css/vendor.css'),
      outfile: join(paths.cssDir, 'vendor.css'),
      nodeModulesDir,
      isProduction,
      loader: adminLoaders,
      themePath: __dirname,
      purge: {
        area: 'admin',
        additionalStandardSafelist: [/^flag-country-/, /^clr-/],
      },
    });

    await buildJsFile({
      entryPoint: resolve(__dirname, 'assets/fossbilling.js'),
      outdir: paths.jsDir,
      entryNames: '[name]',
      chunkNames: 'chunks/[name]-[hash]',
      isProduction,
      loader: adminLoaders,
      splitting: true,
    });

    await writeAssetManifest(paths.buildDir, {
      'build/fossbilling.js': '/themes/admin_default/assets/build/js/fossbilling.js',
      'build/vendor.css': '/themes/admin_default/assets/build/css/vendor.css',
      'build/fossbilling.css': '/themes/admin_default/assets/build/css/fossbilling.css',
      'build/symbol/icons-sprite.svg': '/themes/admin_default/assets/build/symbol/icons-sprite.svg',
    });

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`Build complete in ${duration}s\n`);

  } catch (error) {
    console.error('Build failed:', error);
    process.exit(1);
  }
}

build();
