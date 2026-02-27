import * as esbuild from 'esbuild';
import autoprefixer from 'autoprefixer';
import postcss from 'postcss';
import * as sass from 'sass';
import { PurgeCSS } from 'purgecss';
import { fileURLToPath } from 'url';
import { dirname, resolve, join } from 'path';
import { readFile, writeFile, mkdir, rm, readdir, copyFile } from 'fs/promises';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');

function sassPlugin(nodeModulesDir, isProduction) {
  return {
    name: 'sass',
    setup(build) {
      build.onLoad({ filter: /\.scss$/ }, async (args) => {
        const result = await sass.compileAsync(args.path, {
          loadPaths: [nodeModulesDir],
          style: 'expanded',
          sourceMap: !isProduction,
          sourceMapIncludeSources: !isProduction
        });

        return {
          contents: result.css,
          loader: 'css',
          resolveDir: dirname(args.path)
        };
      });
    }
  };
}

async function ensureDir(dir) {
  await mkdir(dir, { recursive: true });
}

async function removeDirContents(dir) {
  try {
    const entries = await readdir(dir, { withFileTypes: true });
    for (const entry of entries) {
      const entryPath = join(dir, entry.name);
      await rm(entryPath, { recursive: true, force: true });
    }
  } catch (error) {
    if (error.code !== 'ENOENT') {
      throw error;
    }
  }
}

async function copyAssets(srcDir, destDir, options = {}) {
  const exclude = options.exclude || new Set();
  try {
    const entries = await readdir(srcDir, { withFileTypes: true });
    for (const entry of entries) {
      if (exclude.has(entry.name)) continue;
      const srcPath = join(srcDir, entry.name);
      const destPath = join(destDir, entry.name);

      if (entry.isDirectory()) {
        await ensureDir(destPath);
        await copyAssets(srcPath, destPath, options);
      } else {
        await copyFile(srcPath, destPath);
      }
    }
  } catch (error) {
    if (error.code !== 'ENOENT') throw error;
  }
}

async function postprocessCssFile(cssPath, isProduction) {
  const css = await readFile(cssPath, 'utf8');
  const mapPath = `${cssPath}.map`;
  let prevMap;

  if (!isProduction) {
    try {
      prevMap = await readFile(mapPath, 'utf8');
    } catch (error) {
      if (error.code !== 'ENOENT') {
        throw error;
      }
    }
  }

  const result = await postcss([autoprefixer]).process(css, {
    from: cssPath,
    to: cssPath,
    map: isProduction ? false : { inline: false, annotation: true, prev: prevMap || undefined }
  });

  await writeFile(cssPath, result.css);
  if (result.map) {
    await writeFile(mapPath, result.map.toString());
  }
}

async function purgeCssFile(cssFilePath, themePath, enabled = false, client = false) {
  if (!enabled) return;

  try {
    const css = await readFile(cssFilePath, 'utf8');
    const modulesPath = resolve(themePath, '../../modules');

    const contentPaths = client
      ? [
          `${themePath}/html/**/*.twig`,
          `${themePath}/assets/**/*.js`,
          `${modulesPath}/*/html_client/**/*.twig`,
        ]
      : [
          `${themePath}/html/**/*.twig`,
          `${themePath}/assets/**/*.js`,
          `${modulesPath}/*/html_admin/**/*.twig`,
        ];

    const purgeCSSResult = await new PurgeCSS().purge({
      content: contentPaths,
      css: [{ raw: css, extension: 'css' }],
      safelist: {
        standard: [
          /^fi-/, /^toast/, /^modal/, /^dropdown/, /^collapse/, /^alert/, /^spinner/,
          /^active$/, /^show$/, /^fade$/, /^nav-/, /^data-bs-/, /^btn-/, /^card-/,
          /^badge-/, /^form-/, /^text-/, /^bg-/, /^d-/, /^m-/, /^p-/, /^w-/, /^h-/,
          /^border-/, /^flex-/, /^justify-/, /^align-/, /^offcanvas-/, /^accordion-/, /^carousel-/,
        ],
        deep: [/tom-select/, /ts-/],
        greedy: [/^theme-/]
      },
      defaultExtractor: content => content.match(/[A-Za-z0-9-_:/]+/g) || [],
    });

    if (purgeCSSResult?.[0]) {
      await writeFile(cssFilePath, purgeCSSResult[0].css);
      console.log(`✓ PurgeCSS applied to ${cssFilePath.split('/').pop()}`);
    }
  } catch (error) {
    console.warn(`⚠ PurgeCSS failed for ${cssFilePath.split('/').pop()}:`, error.message);
  }
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
    const imgDir = join(buildDir, 'img');

    await ensureDir(jsDir);
    await ensureDir(cssDir);
    await ensureDir(imgDir);

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/huraga.js')],
      bundle: true,
      outfile: join(jsDir, 'huraga.js'),
      platform: 'browser',
      target: 'es2018',
      loader: { '.svg': 'file', '.woff': 'file', '.woff2': 'file', '.ttf': 'file', '.eot': 'file' },
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
      loader: { '.svg': 'file', '.woff': 'file', '.woff2': 'file', '.ttf': 'file', '.eot': 'file' },
      plugins: [sassPlugin(nodeModulesDir, isProduction)],
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info',
      define: { 'process.env.NODE_ENV': isProduction ? '"production"' : '"development"' },
      treeShaking: true,
      legalComments: 'none'
    });

    await postprocessCssFile(join(cssDir, 'huraga.css'), isProduction);
    await purgeCssFile(join(cssDir, 'huraga.css'), __dirname, isProduction, true);

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
      bundle: true,
      outfile: join(cssDir, 'vendor.css'),
      loader: { '.svg': 'file', '.woff': 'file', '.woff2': 'file', '.ttf': 'file', '.eot': 'file' },
      plugins: [sassPlugin(nodeModulesDir, isProduction)],
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info'
    });

    await purgeCssFile(join(cssDir, 'vendor.css'), __dirname, isProduction, true);

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/markdown.css')],
      bundle: true,
      outfile: join(cssDir, 'markdown.css'),
      loader: { '.svg': 'file', '.woff': 'file', '.woff2': 'file', '.ttf': 'file', '.eot': 'file' },
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'info'
    });

    await copyAssets(resolve(__dirname, 'assets/css'), cssDir, { exclude: new Set(['vendor.css', 'markdown.css']) });
    await copyAssets(resolve(__dirname, 'assets/img'), imgDir);
    await copyFile(resolve(__dirname, 'assets/favicon.ico'), join(buildDir, 'favicon.ico'));

    const manifest = {
      'build/huraga.js': '/themes/huraga/assets/build/js/huraga.js',
      'build/vendor.css': '/themes/huraga/assets/build/css/vendor.css',
      'build/huraga.css': '/themes/huraga/assets/build/css/huraga.css',
      'build/markdown.css': '/themes/huraga/assets/build/css/markdown.css'
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
  const imgDir = join(buildDir, 'img');

  await ensureDir(jsDir);
  await ensureDir(cssDir);
  await ensureDir(imgDir);

  await copyAssets(resolve(__dirname, 'assets/css'), cssDir, { exclude: new Set(['vendor.css', 'markdown.css']) });
  await copyAssets(resolve(__dirname, 'assets/img'), imgDir);
  await copyFile(resolve(__dirname, 'assets/favicon.ico'), join(buildDir, 'favicon.ico'));

  const loaders = { '.svg': 'file', '.woff': 'file', '.woff2': 'file', '.ttf': 'file', '.eot': 'file' };

  const jsContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/huraga.js')],
    bundle: true,
    outfile: join(jsDir, 'huraga.js'),
    platform: 'browser',
    target: 'es2018',
    loader: loaders,
    define: { 'process.env.NODE_ENV': isProduction ? '"production"' : '"development"' },
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  const themeCssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/scss/huraga.scss')],
    bundle: true,
    outfile: join(cssDir, 'huraga.css'),
    loader: loaders,
    plugins: [sassPlugin(nodeModulesDir, isProduction)],
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  const vendorCssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
    bundle: true,
    outfile: join(cssDir, 'vendor.css'),
    loader: loaders,
    plugins: [sassPlugin(nodeModulesDir, isProduction)],
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  const markdownCssContext = await esbuild.context({
    entryPoints: [resolve(__dirname, 'assets/css/markdown.css')],
    bundle: true,
    outfile: join(cssDir, 'markdown.css'),
    loader: loaders,
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info'
  });

  await Promise.all([
    jsContext.watch(),
    themeCssContext.watch(),
    vendorCssContext.watch(),
    markdownCssContext.watch()
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
