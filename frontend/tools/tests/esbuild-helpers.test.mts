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
        '.fb-request-loader{animation:none}',
      ].join(''),
    );

    await purgeCssFile(cssPath, { themePath, enabled: true, area: 'client' });

    const purged = await readFile(cssPath, 'utf8');
    assert.match(purged, /\.page\{/);
    assert.match(purged, /\.tab-content>\.tab-pane/);
    assert.doesNotMatch(purged, /never-referenced/);
    assert.match(purged, /\.fb-request-loader/);
  });

  test('keeps runtime calendar attribute states with a theme-specific safelist', async () => {
    const themePath = await mkdtemp(join(tmpdir(), 'fb-calendar-'));
    await mkdir(join(themePath, 'html'), { recursive: true });
    await writeFile(join(themePath, 'html', 'layout.twig'), '<input class="datepicker">');
    const cssPath = join(themePath, 'vendor.css');
    await writeFile(cssPath, '[data-vc="calendar"][data-vc-calendar-hidden]{display:none}.unused{color:red}');

    await purgeCssFile(cssPath, {
      themePath,
      enabled: true,
      area: 'admin',
      additionalGreedySafelist: [/data-vc/],
    });

    const purged = await readFile(cssPath, 'utf8');
    assert.match(purged, /data-vc-calendar-hidden/);
    assert.doesNotMatch(purged, /\.unused/);
  });

  for (const area of ['client', 'admin'] as const) {
    test(`keeps PHP-generated avatar geometry and surfaces for ${area}`, async () => {
      const themePath = await mkdtemp(join(tmpdir(), 'fb-avatar-'));
      await mkdir(join(themePath, 'html'), { recursive: true });
      await writeFile(join(themePath, 'html', 'layout.twig'), "{{ avatar(profile.email) }}");
      const cssPath = join(themePath, 'theme.css');
      await writeFile(cssPath, '.db-avatar{flex-shrink:0;background-color:var(--tblr-secondary-bg)}.unused-avatar-example{color:red}');

      await purgeCssFile(cssPath, { themePath, enabled: true, area });

      const purged = await readFile(cssPath, 'utf8');
      assert.match(purged, /\.db-avatar\{/);
      assert.match(purged, /flex-shrink:0/);
      assert.doesNotMatch(purged, /unused-avatar-example/);
    });
  }

});
