import { fileURLToPath } from 'url';
import { dirname } from 'path';
import { buildTheme } from '../../../../frontend/tools/theme-build.mts';

const __dirname = dirname(fileURLToPath(import.meta.url));

buildTheme({
  themeDir: __dirname,
  label: 'admin_default',
  area: 'admin',
  jsEntry: 'assets/fossbilling.ts',
  cssEntry: 'assets/scss/fossbilling.scss',
});
