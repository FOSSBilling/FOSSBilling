import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve, relative, join } from 'path';
import { readFile, readdir, copyFile, mkdir, writeFile, rm } from 'fs/promises';
import { createRequire } from 'module';

const __dirname = dirname(fileURLToPath(import.meta.url));
const require = createRequire(import.meta.url);
const isProduction = process.env.NODE_ENV === 'production';

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
    // Ignore errors when the directory does not exist or cannot be read
  }
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

async function generateManifest() {
  const manifest = {};
  
  manifest['themes/admin_default/build/fossbilling.js'] = 
    '/themes/admin_default/build/js/fossbilling.js';
  manifest['themes/admin_default/build/fossbilling.css'] = 
    '/themes/admin_default/build/css/fossbilling.css';
  
  const imagesSrc = resolve(__dirname, 'assets/images');
  const imagesDest = resolve(__dirname, 'build/images');
  
  try {
    await ensureDir(imagesDest);
    const imageFiles = await readdir(imagesSrc);
    
    for (const file of imageFiles) {
      if (file.match(/\.(svg|png|jpg|jpeg|gif|ico)$/)) {
        await copyFile(
          join(imagesSrc, file),
          join(imagesDest, file)
        );
        
        const outputPath = `themes/admin_default/build/images/${file}`;
        const publicPath = `/themes/admin_default/build/images/${file}`;
        manifest[outputPath] = publicPath;
      }
    }
  } catch (error) {
    if (error.code !== 'ENOENT') throw error;
  }
  
  manifest['themes/admin_default/build/symbol/icons-sprite.svg'] = 
    '/themes/admin_default/build/symbol/icons-sprite.svg';
  
  const manifestPath = resolve(__dirname, 'build/manifest.json');
  await writeFile(manifestPath, JSON.stringify(manifest, null, 2));
}

async function generateSvgSprite() {
  const { default: SVGSpriter } = await import('svg-sprite');
  
  const iconsDir = resolve(__dirname, 'assets/icons');
  const outputDir = resolve(__dirname, 'build/symbol');
  
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
        generator: (name) => relative(iconsDir, name).replace(/\.svg$/, '')
      }
    }
  });
  
  try {
    const files = await readdir(iconsDir);
    const svgFiles = files.filter(f => f.endsWith('.svg'));
    
    if (svgFiles.length === 0) {
      console.error('No SVG files found in assets/icons');
      return;
    }
    
    for (const file of svgFiles) {
      const filePath = join(iconsDir, file);
      const content = await readFile(filePath, 'utf8');
      spriter.add(filePath, file, content);
    }
    
    spriter.compile((error, result) => {
      if (error) throw error;
      writeFile(join(outputDir, 'icons-sprite.svg'), result.symbol.sprite.contents);
    });
  } catch (error) {
    if (error.code === 'ENOENT') {
      console.error('Icons directory not found');
    } else {
      throw error;
    }
  }
}

async function buildScss() {
  const result = await esbuild.build({
    entryPoints: [resolve(__dirname, 'assets/scss/fossbilling.scss')],
    bundle: true,
    outfile: resolve(__dirname, 'build/css/fossbilling.css'),
    loader: {
      '.scss': 'css'
    },
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'silent'
  });
  
  if (result.errors.length > 0) {
    console.error(result.errors);
    throw new Error('SCSS build failed');
  }
}

async function buildJavaScript() {
  const result = await esbuild.build({
    entryPoints: [resolve(__dirname, 'assets/fossbilling.js')],
    bundle: true,
    outfile: resolve(__dirname, 'build/js/fossbilling.js'),
    platform: 'browser',
    target: 'es2018',
    loader: {
      '.svg': 'dataurl',
      '.woff': 'file',
      '.woff2': 'file',
      '.ttf': 'file',
      '.eot': 'file',
      '.scss': 'css'
    },
    define: {
      'globalThis.jQuery': 'jQuery',
      'globalThis.$': 'jQuery',
      'process.env.NODE_ENV': isProduction ? '"production"' : '"development"'
    },
    banner: {
      js: `import jQuery from 'jquery'; globalThis.$ = globalThis.jQuery = jQuery;`
    },
    minify: isProduction,
    sourcemap: !isProduction,
    external: [
      '../../admin_default/assets/js/tomselect',
      '../../admin_default/assets/js/fossbilling'
    ],
    logLevel: 'silent'
  });
  
  if (result.errors.length > 0) {
    console.error(result.errors);
    throw new Error('JavaScript build failed');
  }
}

async function copyStaticAssets() {
  const fontsSrc = resolve(__dirname, 'assets/fonts');
  const fontsDest = resolve(__dirname, 'build/fonts');
  await copyAssets(fontsSrc, fontsDest);
  
  const imagesSrc = resolve(__dirname, 'assets/images');
  const imagesDest = resolve(__dirname, 'build/images');
  await copyAssets(imagesSrc, imagesDest);
}

async function cleanBuild() {
  try {
    const buildDir = resolve(__dirname, 'build');
    await removeDirContents(buildDir);
  } catch (error) {}
}

async function build() {
  console.log(`Building admin_default theme (${isProduction ? 'production' : 'development'})...\n`);
  
  const startTime = Date.now();
  
  try {
    await cleanBuild();
    
    await ensureDir(resolve(__dirname, 'build/js'));
    await ensureDir(resolve(__dirname, 'build/css'));
    await ensureDir(resolve(__dirname, 'build/symbol'));
    await ensureDir(resolve(__dirname, 'build/images'));
    
    await Promise.all([
      generateSvgSprite(),
      copyStaticAssets()
    ]);
    
    await Promise.all([
      buildScss(),
      buildJavaScript()
    ]);
    
    await generateManifest();
    
    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`Build complete in ${duration}s\n`);
    
  } catch (error) {
    console.error('Build failed:', error);
    process.exit(1);
  }
}

async function watch() {
  console.log('Starting watch mode...\n');
  
  await build();
  
  const chokidar = await import('chokidar');
  
  const watcher = chokidar.watch([
    resolve(__dirname, 'assets/**/*.js'),
    resolve(__dirname, 'assets/**/*.scss'),
    resolve(__dirname, 'assets/icons/*.svg')
  ], {
    ignored: /node_modules/,
    persistent: true
  });
  
  watcher.on('change', async (path) => {
    console.log(`File changed: ${relative(__dirname, path)}`);
    await build().catch(error => console.error('Rebuild failed:', error));
  });
  
  console.log('Watching for changes...');
}

const args = process.argv.slice(2);
if (args.includes('--watch')) {
  watch();
} else {
  build();
}
