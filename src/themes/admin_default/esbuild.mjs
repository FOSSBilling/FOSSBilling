import * as esbuild from 'esbuild';
import autoprefixer from 'autoprefixer';
import postcss from 'postcss';
import * as sass from 'sass';
import { PurgeCSS } from 'purgecss';
import { fileURLToPath } from 'url';
import { dirname, resolve, join, basename } from 'path';
import { readFile, readdir, writeFile, mkdir, rm } from 'fs/promises';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');

const sharedLoaders = {
  '.svg': 'dataurl',
  '.woff': 'file',
  '.woff2': 'file',
  '.ttf': 'file',
  '.eot': 'file'
};

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
  } catch {}
}

async function postprocessCssFile(cssPath, isProduction) {
  const css = await readFile(cssPath, 'utf8');
  const mapPath = `${cssPath}.map`;
  let prevMap;

  if (!isProduction) {
    try {
      prevMap = await readFile(mapPath, 'utf8');
    } catch {}
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

async function purgeCssFile(cssFilePath, themePath, enabled = false) {
  if (!enabled) return;

  try {
    const css = await readFile(cssFilePath, 'utf8');
    const modulesPath = resolve(themePath, '../../modules');

    const purgeCSSResult = await new PurgeCSS().purge({
      content: [
        `${themePath}/html/**/*.twig`,
        `${themePath}/assets/**/*.js`,
        `${modulesPath}/*/html_admin/**/*.twig`,
      ],
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

async function generateManifest() {
  const manifest = {
    'build/fossbilling.js': '/themes/admin_default/assets/build/js/fossbilling.js',
    'build/vendor.css': '/themes/admin_default/assets/build/css/vendor.css',
    'build/fossbilling.css': '/themes/admin_default/assets/build/css/fossbilling.css',
    'build/symbol/icons-sprite.svg': '/themes/admin_default/assets/build/symbol/icons-sprite.svg'
  };

  await writeFile(resolve(__dirname, 'assets/build/manifest.json'), JSON.stringify(manifest, null, 2));
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
  const svgFiles = files.filter(file => file.endsWith('.svg'));

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

    await ensureDir(resolve(__dirname, 'assets/build/js'));
    await ensureDir(resolve(__dirname, 'assets/build/css'));
    await ensureDir(resolve(__dirname, 'assets/build/symbol'));

    await generateSvgSprite();

    await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/scss/fossbilling.scss')],
      bundle: true,
      outfile: resolve(__dirname, 'assets/build/css/fossbilling.css'),
      plugins: [sassPlugin(nodeModulesDir, isProduction)],
      loader: sharedLoaders,
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
      loader: sharedLoaders,
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
      loader: sharedLoaders,
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
