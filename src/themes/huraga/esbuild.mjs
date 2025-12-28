import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve, join } from 'path';
import { readdir, copyFile, mkdir, writeFile } from 'fs/promises';

const __dirname = dirname(fileURLToPath(import.meta.url));

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
    if (error.code !== 'ENOENT') throw error;
  }
}

async function buildAssets() {
  console.log(`\n🔨 Building huraga theme (${isProduction ? 'production' : 'development'})...\n`);
  
  const startTime = Date.now();
  
  try {
    // Create build directories
    const buildDir = resolve(__dirname, 'build');
    const jsDir = join(buildDir, 'js');
    const cssDir = join(buildDir, 'css');
    const imagesDir = join(buildDir, 'images');
    
    await ensureDir(jsDir);
    await ensureDir(cssDir);
    await ensureDir(imagesDir);
    
    console.log('⏳ Building JavaScript...');
    
    // Bundle JavaScript (huraga.js)
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
        '.eot': 'file',
        '.scss': 'css'
      },
      define: {
        'process.env.NODE_ENV': isProduction ? '"production"' : '"development"'
      },
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'silent'
    });
    
    if (jsResult.errors.length > 0) {
      console.error('✗ JavaScript build failed:', jsResult.errors);
      throw new Error('JavaScript build failed');
    }
    
    console.log('✓ JavaScript built successfully');
    
    console.log('⏳ Building CSS...');
    
    // Compile SCSS and markdown CSS
    const cssResult = await esbuild.build({
      entryPoints: [
        resolve(__dirname, 'assets/scss/huraga.scss'),
        resolve(__dirname, 'assets/scss/markdown.scss')
      ],
      bundle: true,
      outdir: cssDir,
      loader: {
        '.scss': 'css',
        '.css': 'css'
      },
      // Resolve node_modules imports (~ syntax)
      nodePaths: [resolve(__dirname, 'node_modules'), resolve(__dirname, '../../node_modules')],
      absWorkingDir: resolve(__dirname),
      minify: isProduction,
      sourcemap: !isProduction,
      logLevel: 'silent'
    });
    
    if (cssResult.errors.length > 0) {
      console.error('✗ CSS build failed:', cssResult.errors);
      throw new Error('CSS build failed');
    }
    
    console.log('✓ CSS built successfully');
    
    console.log('⏳ Copying assets...');
    
    // Copy CSS assets (font-awesome)
    const cssSrc = resolve(__dirname, 'assets/css');
    const cssDest = join(buildDir, 'css');
    await copyAssets(cssSrc, cssDest);
    
    // Copy images
    const imagesSrc = resolve(__dirname, 'assets/images');
    const imagesDest = imagesDir;
    await copyAssets(imagesSrc, imagesDest);
    
    console.log('✓ Assets copied');
    
    // Generate a simple manifest
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
    console.log(`\n✅ Build complete in ${duration}s\n`);
    
  } catch (error) {
    console.error('✗ Build failed:', error);
    process.exit(1);
  }
}

// CLI
const args = process.argv.slice(2);
if (args.includes('--watch')) {
  console.log('👀 Watch mode not yet implemented for huraga');
  console.log('Building once...');
}

buildAssets();
