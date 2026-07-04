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
const purgeSafelist = [/^hide-/, /^iti/];
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');

async function build() {
  console.log(`Building tenantninja theme (${isProduction ? 'production' : 'development'}) with esbuild ...`);

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
        // lucide-static ships a flat icons/ directory (no per-variant subfolders like
        // @tabler/icons has), so we mark this source with a truthy `variant` too -
        // that tells resolveIconFiles() to look up `${icon.name}.svg` directly
        // instead of `${icon.variant}/${icon.name}.svg`.
        { name: 'lucide-static', dir: resolve(nodeModulesDir, 'lucide-static/icons'), variant: 'lucide' },
      ],
    });

    await buildJsFile({
      entryPoint: resolve(__dirname, 'assets/tenantninja.js'),
      outdir: paths.jsDir,
      entryNames: '[name]',
      chunkNames: 'chunks/[name]-[hash]',
      isProduction,
      loader: sharedLoaders,
      splitting: true,
      drop: isProduction ? ['console', 'debugger'] : []
    });

    await buildCssFile({
      entryPoint: resolve(__dirname, 'assets/scss/tenantninja.scss'),
      outfile: join(paths.cssDir, 'tenantninja.css'),
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
      'build/tenantninja.js': '/themes/tenantninja/assets/build/js/tenantninja.js',
      'build/vendor.css': '/themes/tenantninja/assets/build/css/vendor.css',
      'build/tenantninja.css': '/themes/tenantninja/assets/build/css/tenantninja.css',
      'build/symbol/icons-sprite.svg': '/themes/tenantninja/assets/build/symbol/icons-sprite.svg',
    });

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`✓ Build complete in ${duration}s\n`);

  } catch (error) {
    console.error('✗ Build failed:', error);
    process.exit(1);
  }
}

build();
