import { build } from 'esbuild';

export async function buildWidgetScript(root: string): Promise<string> {
  const result = await build({
    stdin: {
      contents: `
        import initDatepickers from './src/themes/default/admin/assets/js/datepicker.ts';
        import initClipboard from './src/themes/default/admin/assets/js/clipboard.ts';
        import TomSelect from 'tom-select';
        import { Clipboard } from '@tabler/core';

        document.querySelectorAll('.test-select').forEach(el => new TomSelect(el));
        window.initDatepickers = initDatepickers;
        initDatepickers();
        document.querySelectorAll('[data-test-initialized]').forEach(el => Clipboard.getOrCreateInstance(el));
        initClipboard();
      `,
      resolveDir: root,
    },
    bundle: true,
    write: false,
    format: 'iife',
  });
  return result.outputFiles[0].text;
}
