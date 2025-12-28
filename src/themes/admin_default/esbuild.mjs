import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve, relative, join } from 'path';
import { readFile, readdir, copyFile, mkdir, writeFile, stat } from 'fs/promises';
import { createRequire } from 'module';
import { pathToFileURL } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const require = createRequire(import.meta.url);

// Spinner utility
const spinner = {
  start: (text) => console.log(`⏳ ${text}`),
  success: (text) => console.log(`✓ ${text}`),
  error: (text) => console.error(`✗ ${text}`)
};

// Build configuration
const isProduction = process.env.NODE_ENV === 'production';

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
    // Ignore errors for missing directories
    if (error.code !== 'ENOENT') throw error;
  }
}

async function generateManifest() {
  spinner.start('Generating manifest...');
  const manifest = {};
  
  // Add main entry points (these will get versioned hashes in production)
  manifest['themes/admin_default/build/fossbilling.js'] = 
    '/themes/admin_default/build/js/fossbilling.js';
  manifest['themes/admin_default/build/fossbilling.css'] = 
    '/themes/admin_default/build/css/fossbilling.css';
  
  // Copy all images from assets to build and add to manifest
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
  
  // Add the sprite SVG
  manifest['themes/admin_default/build/symbol/icons-sprite.svg'] = 
    '/themes/admin_default/build/symbol/icons-sprite.svg';
  
  // Write manifest file
  const manifestPath = resolve(__dirname, 'build/manifest.json');
  await writeFile(manifestPath, JSON.stringify(manifest, null, 2));
  
  spinner.success('Manifest generated');
}

async function generateSvgSprite() {
  spinner.start('Generating SVG sprite...');
  
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
        generator: (name) => {
          // Convert filename to ID (e.g., 'arrow-up.svg' -> 'arrow-up')
          return relative(iconsDir, name).replace(/\.svg$/, '');
        }
      }
    }
  });
  
  try {
    // Add all SVG files
    const files = await readdir(iconsDir);
    const svgFiles = files.filter(f => f.endsWith('.svg'));
    
    if (svgFiles.length === 0) {
      spinner.error('No SVG files found in assets/icons');
      return;
    }
    
    for (const file of svgFiles) {
      const filePath = join(iconsDir, file);
      const content = await readFile(filePath, 'utf8');
      spriter.add(filePath, file, content);
    }
    
    // Compile sprite
    spriter.compile((error, result) => {
      if (error) {
        spinner.error(`Sprite generation failed: ${error.message}`);
        throw error;
      }
      
      const file = result.symbol.sprite;
      writeFile(join(outputDir, 'icons-sprite.svg'), file.contents);
    });
    
    spinner.success(`SVG sprite generated with ${svgFiles.length} icons`);
  } catch (error) {
    if (error.code === 'ENOENT') {
      spinner.error('Icons directory not found');
    } else {
      throw error;
    }
  }
}

async function buildScss() {
  spinner.start('Building SCSS...');
  
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
    spinner.error('SCSS build failed');
    console.error(result.errors);
    throw new Error('SCSS build failed');
  }
  
  spinner.success('SCSS built successfully');
}

async function buildJavaScript() {
  spinner.start('Building JavaScript...');
  
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
      js: `
import jQuery from 'jquery';
globalThis.$ = globalThis.jQuery = jQuery;
`
    },
    minify: isProduction,
    sourcemap: !isProduction,
    external: [
      // Keep these as external for huraga theme compatibility
      '../../admin_default/assets/js/tomselect',
      '../../admin_default/assets/js/fossbilling'
    ],
    logLevel: 'silent'
  });
  
  if (result.errors.length > 0) {
    spinner.error('JavaScript build failed');
    console.error(result.errors);
    throw new Error('JavaScript build failed');
  }
  
  spinner.success('JavaScript built successfully');
}

async function copyStaticAssets() {
  spinner.start('Copying static assets...');
  
  // Copy font files if they exist
  const fontsSrc = resolve(__dirname, 'assets/fonts');
  const fontsDest = resolve(__dirname, 'build/fonts');
  await copyAssets(fontsSrc, fontsDest);
  
  // Copy any images from source
  const imagesSrc = resolve(__dirname, 'assets/images');
  const imagesDest = resolve(__dirname, 'build/images');
  await copyAssets(imagesSrc, imagesDest);
  
  spinner.success('Static assets copied');
}

async function cleanBuild() {
  spinner.start('Cleaning build directory...');
  
  const { execSync } = await import('child_process');
  try {
    execSync(`rm -rf ${resolve(__dirname, 'build')}/*`);
    spinner.success('Build directory cleaned');
  } catch (error) {
    // If build directory doesn't exist, that's fine
    spinner.success('Build directory ready');
  }
}

// Main build function
async function build() {
  console.log(`\n🔨 Building admin_default theme (${isProduction ? 'production' : 'development'})...\n`);
  
  const startTime = Date.now();
  
  try {
    // Clean build directory
    await cleanBuild();
    
    // Create build directories
    await ensureDir(resolve(__dirname, 'build/js'));
    await ensureDir(resolve(__dirname, 'build/css'));
    await ensureDir(resolve(__dirname, 'build/symbol'));
    await ensureDir(resolve(__dirname, 'build/images'));
    
    // Run builds in parallel where possible
    await Promise.all([
      generateSvgSprite(),
      copyStaticAssets()
    ]);
    
    // Build CSS and JS
    await Promise.all([
      buildScss(),
      buildJavaScript()
    ]);
    
    // Generate manifest last
    await generateManifest();
    
    const duration = ((Date.now() - startTime) / 1000).toFixed(2);
    console.log(`\n✅ Build complete in ${duration}s\n`);
    
  } catch (error) {
    spinner.error('Build failed');
    console.error(error);
    process.exit(1);
  }
}

// Watch mode
async function watch() {
  console.log('👀 Starting watch mode...\n');
  
  // Initial build
  await build();
  
  // Watch for changes
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
    console.log(`\n📝 File changed: ${relative(__dirname, path)}`);
    try {
      await build();
    } catch (error) {
      console.error('Rebuild failed:', error);
    }
  });
  
  console.log('Watching for changes...');
}

// CLI
const args = process.argv.slice(2);
if (args.includes('--watch')) {
  watch();
} else {
  build();
}
