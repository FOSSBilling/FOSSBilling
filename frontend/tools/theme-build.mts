import { join, resolve } from 'path';
import {
  buildCssFile,
  buildJsFile,
  defaultIconSources,
  getThemeBuildPaths,
  prepareThemeBuildDirs,
  svgDataUrlLoaders,
  themeAssetManifest,
  writeAssetManifest,
} from './esbuild-helpers.mts';
import { buildIconSprite } from './icon-sprite.mts';

export interface ThemeBuildConfig {
  // Directory containing the theme's esbuild.mts.
  themeDir: string;
  // Human-readable label for the log line: 'admin_default' or 'huraga'.
  label: string;
  // Theme area, doubling as the manifest prefix: 'admin' or 'client'.
  area: 'admin' | 'client';
  // Theme entry points, e.g. 'assets/fossbilling.ts'. Bundle names derive
  // from the entry filenames, matching esbuild's entryNames: '[name]'.
  jsEntry: string;
  cssEntry: string;
}

// Per-area PurgeCSS safelist entries. Kept centralized but NOT unified:
// each area's values guard classes only that area renders.
const areaSafelist = {
  admin: [/^flag-country-/, /^clr-/],
  client: [/^hide-/, /^iti/],
};

function bundleName(entry: string): string {
  return (entry.split('/').pop() ?? entry).replace(/\.(ts|mts|js|scss|css)$/, '');
}

/**
 * Standard build for a default theme: icon sprite, theme CSS, vendor CSS,
 * JS bundle, and the build manifest. Both shipped themes share this flow;
 * only entries, purge area, and log labels differ.
 */
export async function buildTheme(config: ThemeBuildConfig): Promise<void> {
  const { themeDir, label, area, jsEntry, cssEntry } = config;
  const jsBundle = bundleName(jsEntry);
  const cssBundle = bundleName(cssEntry);
  const isProduction = process.env.NODE_ENV === 'production';
  const rootDir = resolve(themeDir, '../../../..');
  const nodeModulesDir = resolve(rootDir, 'node_modules');

  console.log(`Building ${label} theme (${isProduction ? 'production' : 'development'}) with esbuild ...`);

  const startTime = Date.now();

  try {
    const paths = getThemeBuildPaths(themeDir);
    await prepareThemeBuildDirs(paths);

    console.log('Generating icon sprite...');
    await buildIconSprite({
      manifestPath: resolve(themeDir, 'icon-manifest.json'),
      outputDir: paths.symbolDir,
      sources: defaultIconSources(themeDir, nodeModulesDir),
    });

    await buildCssFile({
      entryPoint: resolve(themeDir, cssEntry),
      outfile: join(paths.cssDir, `${cssBundle}.css`),
      nodeModulesDir,
      isProduction,
      loader: svgDataUrlLoaders,
      themePath: themeDir,
      purge: {
        area,
        additionalStandardSafelist: areaSafelist[area],
      },
    });

    await buildCssFile({
      entryPoint: resolve(themeDir, 'assets/css/vendor.css'),
      outfile: join(paths.cssDir, 'vendor.css'),
      nodeModulesDir,
      isProduction,
      loader: svgDataUrlLoaders,
      themePath: themeDir,
      purge: {
        area,
        additionalStandardSafelist: areaSafelist[area],
      },
    });

    await buildJsFile({
      entryPoint: resolve(themeDir, jsEntry),
      outdir: paths.jsDir,
      entryNames: '[name]',
      chunkNames: 'chunks/[name]-[hash]',
      isProduction,
      loader: svgDataUrlLoaders,
      splitting: true,
    });

    await writeAssetManifest(paths.buildDir, themeAssetManifest(area, jsBundle, cssBundle));

    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`Build complete in ${duration}s\n`);
  } catch (error) {
    console.error('Build failed:', error);
    process.exit(1);
  }
}
