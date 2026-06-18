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
const purgeSafelist = [/^hide-/];
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');

async function build() {
  console.log(`Building huraga theme (${isProduction ? 'production' : 'development'}) with esbuild ...`);

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

    await buildJsFile({
      entryPoint: resolve(__dirname, 'assets/huraga.js'),
      outdir: paths.jsDir,
      entryNames: '[name]',
      chunkNames: 'chunks/[name]-[hash]',
      isProduction,
      loader: sharedLoaders,
      splitting: true,
      drop: isProduction ? ['console', 'debugger'] : []
    });

    await buildCssFile({
      entryPoint: resolve(__dirname, 'assets/scss/huraga.scss'),
      outfile: join(paths.cssDir, 'huraga.css'),
      nodeModulesDir,
      isProduction,
      loader: sharedLoaders,
      themePath: __dirname,
      purge: {
        area: 'client',
        additionalStandardSafelist: purgeSafelist,
      },
    });

    await buildCssFile({
      entryPoint: resolve(__dirname, 'assets/css/vendor.css'),
      outfile: join(paths.cssDir, 'vendor.css'),
      nodeModulesDir,
      isProduction,
      loader: sharedLoaders,
      themePath: __dirname,
      purge: {
        area: 'client',
        additionalStandardSafelist: purgeSafelist,
      },
    });

    await writeAssetManifest(paths.buildDir, {
      'build/huraga.js': '/themes/huraga/assets/build/js/huraga.js',
      'build/vendor.css': '/themes/huraga/assets/build/css/vendor.css',
      'build/huraga.css': '/themes/huraga/assets/build/css/huraga.css',
      'build/symbol/icons-sprite.svg': '/themes/huraga/assets/build/symbol/icons-sprite.svg',
    });

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`✓ Build complete in ${duration}s\n`);

  } catch (error) {
    console.error('✗ Build failed:', error);
    process.exit(1);
  }
}

build();
