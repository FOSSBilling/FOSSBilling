import assert from 'node:assert/strict';
import { mkdtemp, mkdir, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, test } from 'node:test';

import { purgeCssFile } from '../esbuild-helpers.mts';

describe('purgeCssFile', () => {
  test('keeps rules for classes that only module templates use', async () => {
    const themePath = await mkdtemp(join(tmpdir(), 'fb-theme-'));
    await mkdir(join(themePath, 'html'), { recursive: true });
    await writeFile(join(themePath, 'html', 'layout.twig'), '<div class="page"></div>');

    const cssPath = join(themePath, 'vendor.css');
    await writeFile(
      cssPath,
      [
        '.page{margin:0}',
        '.tab-content>.tab-pane{display:none}',
        '.never-referenced{color:red}',
      ].join(''),
    );

    await purgeCssFile(cssPath, { themePath, enabled: true, area: 'client' });

    const purged = await readFile(cssPath, 'utf8');
    assert.match(purged, /\.page\{/);
    assert.match(purged, /\.tab-content>\.tab-pane/);
    assert.doesNotMatch(purged, /never-referenced/);
  });
});
