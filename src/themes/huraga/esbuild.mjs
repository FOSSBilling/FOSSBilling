import autoprefixer from 'autoprefixer';
import * as esbuild from 'esbuild';
import postcss from 'postcss';
import * as sass from 'sass';
import { fileURLToPath } from 'url';
import { dirname, resolve, join } from 'path';
import { readdir, readFile, copyFile, mkdir, writeFile } from 'fs/promises';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');

function sassPlugin() {
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

async function postprocessCss(cssPath) {
  const css = await readFile(cssPath, 'utf8');
  const mapPath = `${cssPath}.map`;
  let prevMap;

  if (!isProduction) {
    try {
      prevMap = await readFile(mapPath, 'utf8');
    } catch (error) {}
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

async function ensureDir(dir) {
  await mkdir(dir, { recursive: true });
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

async function buildAssets() {
  console.log(`Building huraga theme (${isProduction ? 'production' : 'development'})...\n`);
  
  const startTime = Date.now();
  
  try {
    const buildDir = resolve(__dirname, 'build');
    const jsDir = join(buildDir, 'js');
    const cssDir = join(buildDir, 'css');
    const imagesDir = join(buildDir, 'images');
    
    await ensureDir(jsDir);
    await ensureDir(cssDir);
    await ensureDir(imagesDir);
    
    const jsResult = await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/huraga.js')],
      bundle: true,
      outfile: join(jsDir, 'huraga.js'),
      platform: 'browser',
      target: 'es2018',
      format: 'esm',
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
      logLevel: 'silent'
    });
    
    if (jsResult.errors.length > 0) throw new Error('JavaScript build failed');
    
    const themeCssResult = await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/scss/huraga.scss')],
      bundle: true,
      outfile: join(cssDir, 'huraga.css'),
      loader: {
        '.svg': 'dataurl',
        '.woff': 'file',
        '.woff2': 'file',
        '.ttf': 'file',
        '.eot': 'file'
      },
      plugins: [sassPlugin()],
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'silent'
    });

    if (themeCssResult.errors.length > 0) throw new Error('Theme CSS build failed');

    await postprocessCss(join(cssDir, 'huraga.css'));

    const vendorCssResult = await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/vendor.css')],
      bundle: true,
      outfile: join(cssDir, 'vendor.css'),
      loader: {
        '.svg': 'dataurl',
        '.woff': 'file',
        '.woff2': 'file',
        '.ttf': 'file',
        '.eot': 'file'
      },
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'silent'
    });

    if (vendorCssResult.errors.length > 0) throw new Error('Vendor CSS build failed');

    const markdownCssResult = await esbuild.build({
      entryPoints: [resolve(__dirname, 'assets/css/markdown.css')],
      bundle: true,
      outfile: join(cssDir, 'markdown.css'),
      loader: {
        '.svg': 'dataurl',
        '.woff': 'file',
        '.woff2': 'file',
        '.ttf': 'file',
        '.eot': 'file'
      },
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'silent'
    });

    if (markdownCssResult.errors.length > 0) throw new Error('Markdown CSS build failed');
    
    const cssSrc = resolve(__dirname, 'assets/css');
    const cssDest = join(buildDir, 'css');
    await copyAssets(cssSrc, cssDest, {
      exclude: new Set(['vendor.css', 'markdown.css'])
    });
    
    const imagesSrc = resolve(__dirname, 'assets/images');
    const imagesDest = imagesDir;
    await copyAssets(imagesSrc, imagesDest);
    
    const manifest = {
      'themes/huraga/build/huraga.js': '/themes/huraga/build/js/huraga.js',
      'themes/huraga/build/vendor.css': '/themes/huraga/build/css/vendor.css',
      'themes/huraga/build/huraga.css': '/themes/huraga/build/css/huraga.css',
      'themes/huraga/build/markdown.css': '/themes/huraga/build/css/markdown.css'
    };
    
    await writeFile(
      join(buildDir, 'manifest.json'),
      JSON.stringify(manifest, null, 2)
    );
    
    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`Build complete in ${duration}s\n`);
    
  } catch (error) {
    console.error('Build failed:', error);
    process.exit(1);
  }
}

const args = process.argv.slice(2);
if (args.includes('--watch')) {
  console.log('Watch mode not yet implemented for huraga');
  console.log('Building once...');
}

buildAssets();
