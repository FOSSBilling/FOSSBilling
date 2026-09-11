import { fileURLToPath } from 'url';
import { dirname } from 'path';
import { buildTheme } from '../../../../frontend/tools/theme-build.mts';

const __dirname = dirname(fileURLToPath(import.meta.url));

buildTheme({
  themeDir: __dirname,
  label: 'huraga',
  area: 'client',
  jsEntry: 'assets/huraga.ts',
  cssEntry: 'assets/scss/huraga.scss',
});
