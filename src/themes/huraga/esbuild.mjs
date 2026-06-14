import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve, join } from 'path';
import { access, readFile, writeFile } from 'fs/promises';
import {
  ensureDir,
  postprocessCssFile,
  purgeCssFile,
  removeDirContents,
  sassPlugin,
  sharedLoaders,
} from '../../../frontend/tools/esbuild-helpers.mjs';
import { generateIconSprite } from '../../../frontend/tools/icon-sprite.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');
const tablerIconsDir = (variant) => resolve(nodeModulesDir, '@tabler/icons/icons', variant);

async function fileExists(filePath) {
  try {
    await access(filePath);

    return true;
  } catch {
    return false;
  }
}

async function resolveIconFiles() {
  const manifest = JSON.parse(await readFile(resolve(__dirname, 'icon-manifest.json'), 'utf8'));
  const defaultVariant = manifest.defaultVariant || 'outline';
  const iconFiles = [];

  for (const entry of manifest.icons) {
    const name = typeof entry === 'string' ? entry : entry.name;
    const variant = (typeof entry === 'object' && entry.variant) || defaultVariant;
    const iconPath = resolve(tablerIconsDir(variant), `${name}.svg`);

    if (!await fileExists(iconPath)) {
      console.warn(`  Warning: Icon "${name}" not found in "${variant}" variant, skipping`);
      continue;
    }

    iconFiles.push({ name, path: iconPath });
  }

  return iconFiles;
}

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
    const symbolDir = join(buildDir, 'symbol');

    await ensureDir(jsDir);
    await ensureDir(cssDir);
    await ensureDir(symbolDir);

    console.log('Generating icon sprite...');
    await generateIconSprite({
      outputDir: symbolDir,
      iconFiles: await resolveIconFiles(),
    });

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

    const manifest = {
      'build/huraga.js': '/themes/huraga/assets/build/js/huraga.js',
      'build/vendor.css': '/themes/huraga/assets/build/css/vendor.css',
      'build/huraga.css': '/themes/huraga/assets/build/css/huraga.css',
      'build/symbol/icons-sprite.svg': '/themes/huraga/assets/build/symbol/icons-sprite.svg'
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

  await ensureDir(jsDir);
  await ensureDir(cssDir);

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
