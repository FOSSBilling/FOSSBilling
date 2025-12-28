import autoprefixer from 'autoprefixer';
import * as esbuild from 'esbuild';
import postcss from 'postcss';
import * as sass from 'sass';
import { fileURLToPath, pathToFileURL } from 'url';
import { dirname, resolve, join } from 'path';
import { readdir, readFile, copyFile, mkdir, writeFile } from 'fs/promises';

const __dirname = dirname(fileURLToPath(import.meta.url));
const isProduction = process.env.NODE_ENV === 'production';
const rootDir = resolve(__dirname, '../../..');
const nodeModulesDir = resolve(rootDir, 'node_modules');
const nodeModulesUrl = pathToFileURL(`${nodeModulesDir}/`);

const sassImporter = {
  findFileUrl(url) {
    if (!url.startsWith('~')) return null;
    return new URL(url.slice(1), nodeModulesUrl);
  }
};

function sassPlugin() {
  return {
    name: 'sass',
    setup(build) {
      build.onLoad({ filter: /\.scss$/ }, async (args) => {
        const result = await sass.compileAsync(args.path, {
          loadPaths: [nodeModulesDir],
          importers: [sassImporter],
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

function tildeResolverPlugin() {
  return {
    name: 'tilde-resolver',
    setup(build) {
      build.onResolve({ filter: /^~(.+)/ }, (args) => ({
        path: resolve(nodeModulesDir, args.path.slice(1))
      }));
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

async function copyAssets(srcDir, destDir) {
  try {
    const entries = await readdir(srcDir, { withFileTypes: true });
    for (const entry of entries) {
      const srcPath = join(srcDir, entry.name);
      const destPath = join(destDir, entry.name);
      
      if (entry.isDirectory()) {
        await ensureDir(destPath);
        await copyAssets(srcPath, destPath);
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
    
    const cssResult = await esbuild.build({
      entryPoints: [
        resolve(__dirname, 'assets/scss/huraga.scss'),
        resolve(__dirname, 'assets/scss/markdown.scss')
      ],
      bundle: true,
      outdir: cssDir,
      loader: {
        '.svg': 'dataurl',
        '.woff': 'file',
        '.woff2': 'file',
        '.ttf': 'file',
        '.eot': 'file'
      },
      plugins: [sassPlugin(), tildeResolverPlugin()],
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'silent'
    });
    
    if (cssResult.errors.length > 0) throw new Error('CSS build failed');

    await Promise.all([
      postprocessCss(join(cssDir, 'huraga.css')),
      postprocessCss(join(cssDir, 'markdown.css'))
    ]);
    
    const cssSrc = resolve(__dirname, 'assets/css');
    const cssDest = join(buildDir, 'css');
    await copyAssets(cssSrc, cssDest);
    
    const imagesSrc = resolve(__dirname, 'assets/images');
    const imagesDest = imagesDir;
    await copyAssets(imagesSrc, imagesDest);
    
    const manifest = {
      'themes/huraga/build/huraga.js': '/themes/huraga/build/js/huraga.js',
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
