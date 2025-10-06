import * as esbuild from 'esbuild';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));

console.log(`Building CKEditor with esbuild ...`);
await esbuild.build({
  bundle: true,
  entryPoints: [resolve(__dirname, 'src/ckeditor.js')],
  outfile: resolve(__dirname, 'assets/ckeditor.js'),
  allowOverwrite: true,
  globalName: 'CKEditor',
  platform: 'browser',
  minify: true,
  treeShaking: true,
  logLevel: 'info'
}).then(() => {
    console.log('✓ CKEditor build successful.');
}).catch((error) => {
    console.error('✗ CKEditor build failed:', error);
    process.exit(1);
});
