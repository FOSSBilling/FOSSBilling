import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';
import { mkdir, writeFile } from 'fs/promises';

const __dirname = dirname(fileURLToPath(import.meta.url));
const rootDir = resolve(__dirname, '..');
const outputDir = resolve(rootDir, 'src/public/assets');
const isProduction = process.env.NODE_ENV === 'production';

const sharedOptions = {
  bundle: true,
  platform: 'browser',
  target: 'es2018',
  minify: isProduction,
  sourcemap: !isProduction,
  logLevel: 'info',
  legalComments: 'none',
};

async function build() {
  await mkdir(resolve(outputDir, 'js'), { recursive: true });
  await mkdir(resolve(outputDir, 'css'), { recursive: true });
  await mkdir(resolve(outputDir, 'editor'), { recursive: true });

  await esbuild.build({
    ...sharedOptions,
    entryPoints: [resolve(__dirname, 'core/fossbilling.js')],
    outfile: resolve(outputDir, 'js/fossbilling.js'),
  });

  await esbuild.build({
    ...sharedOptions,
    entryPoints: [resolve(__dirname, 'core/api.js')],
    outfile: resolve(outputDir, 'js/api.js'),
  });

  await esbuild.build({
    ...sharedOptions,
    entryPoints: [resolve(__dirname, 'editor/ckeditor.js')],
    outfile: resolve(outputDir, 'editor/ckeditor.js'),
    loader: {
      '.svg': 'dataurl',
      '.woff': 'file',
      '.woff2': 'file',
      '.ttf': 'file',
      '.eot': 'file',
    },
  });

  await esbuild.build({
    ...sharedOptions,
    entryPoints: [resolve(__dirname, 'styles/markdown.css')],
    outfile: resolve(outputDir, 'css/markdown.css'),
  });

  const manifest = {
    'js/fossbilling.js': '/public/assets/js/fossbilling.js',
    'js/api.js': '/public/assets/js/api.js',
    'css/markdown.css': '/public/assets/css/markdown.css',
    'editor/ckeditor.js': '/public/assets/editor/ckeditor.js',
    'editor/ckeditor.css': '/public/assets/editor/ckeditor.css',
  };

  await writeFile(resolve(outputDir, 'manifest.json'), JSON.stringify(manifest, null, 2));
}

build().catch((error) => {
  console.error('Core frontend build failed:', error);
  process.exit(1);
});
