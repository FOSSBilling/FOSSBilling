import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import {
  buildCssFile,
  buildJsFile,
  ensureDir,
  sharedLoaders,
  writeAssetManifest,
} from './tools/esbuild-helpers.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const rootDir = resolve(__dirname, '..');
const outputDir = resolve(rootDir, 'src/public/assets');
const nodeModulesDir = resolve(rootDir, 'node_modules');
const isProduction = process.env.NODE_ENV === 'production';

async function build() {
  await ensureDir(resolve(outputDir, 'js'));
  await ensureDir(resolve(outputDir, 'css'));
  await ensureDir(resolve(outputDir, 'editor'));

  await buildJsFile({
    entryPoint: resolve(__dirname, 'core/fossbilling.js'),
    outfile: resolve(outputDir, 'js/fossbilling.js'),
    isProduction,
    drop: [],
  });

  await buildJsFile({
    entryPoint: resolve(__dirname, 'core/api.js'),
    outfile: resolve(outputDir, 'js/api.js'),
    isProduction,
    drop: [],
  });

  await buildJsFile({
    entryPoint: resolve(__dirname, 'editor/ckeditor.js'),
    outfile: resolve(outputDir, 'editor/ckeditor.js'),
    isProduction,
    drop: [],
    loader: {
      ...sharedLoaders,
      '.svg': 'dataurl',
    },
  });

  await buildCssFile({
    entryPoint: resolve(__dirname, 'styles/markdown.css'),
    outfile: resolve(outputDir, 'css/markdown.css'),
    nodeModulesDir,
    isProduction,
  });

  const manifest = {
    'js/fossbilling.js': '/public/assets/js/fossbilling.js',
    'js/api.js': '/public/assets/js/api.js',
    'css/markdown.css': '/public/assets/css/markdown.css',
    'editor/ckeditor.js': '/public/assets/editor/ckeditor.js',
    'editor/ckeditor.css': '/public/assets/editor/ckeditor.css',
  };

  await writeAssetManifest(outputDir, manifest);
}

build().catch((error) => {
  console.error('Core frontend build failed:', error);
  process.exit(1);
});
