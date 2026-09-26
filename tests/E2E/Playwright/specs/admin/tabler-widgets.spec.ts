import { test, expect, type Page } from '@playwright/test';
import { build } from 'esbuild';
import { resolve } from 'node:path';

const root = process.cwd();
let script: string;

test.beforeEach(async ({ page }) => {
  page.on('pageerror', error => { throw error; });
});

test.beforeAll(async () => {
  const result = await build({
    stdin: {
      contents: `
        import initDatepickers from './src/themes/default/admin/assets/js/datepicker.ts';
        import initClipboard from './src/themes/default/admin/assets/js/clipboard.ts';
        import TomSelect from 'tom-select';

        document.querySelectorAll('.test-select').forEach(el => new TomSelect(el));
        window.initDatepickers = initDatepickers;
        initDatepickers();
        initClipboard();
      `,
      resolveDir: root,
    },
    bundle: true,
    write: false,
    format: 'iife',
  });
  script = result.outputFiles[0].text;
});

async function mount(page: Page, markup: string) {
  await page.setContent(`<html data-bs-theme="light"><body class="p-4">${markup}</body></html>`);
  // Exercise the production CSS, including PurgeCSS's treatment of generated markup.
  await page.addStyleTag({ path: resolve(root, 'src/themes/default/admin/assets/build/css/vendor.css') });
  await page.addStyleTag({ path: resolve(root, 'src/themes/default/admin/assets/build/css/fossbilling.css') });
  await page.addScriptTag({ content: script });
}

const range = (value = '') => `<form><div class="input-icon" style="width: 320px"><input class="form-control datepicker" value="${value}" data-name-from="date_from" data-name-to="date_to"></div><button type="reset">Reset</button></form>`;

test('preserves prefilled ranges, scopes fields to forms, and clears submitted values', async ({ page }) => {
  await mount(page, range('2026-09-01 to 2026-09-03') + range('2026-08-01 to 2026-08-02'));
  const forms = page.locator('form');
  await expect(forms.nth(0).locator('[name="date_to"]')).toHaveValue('2026-09-03');
  await forms.nth(0).getByRole('button', { name: 'Clear date' }).click();
  await expect(forms.nth(0).locator('[name="date_from"]')).toHaveValue('');
  await expect(forms.nth(0).locator('[name="date_to"]')).toHaveValue('');
  await expect(forms.nth(1).locator('[name="date_from"]')).toHaveValue('2026-08-01');
  await forms.nth(0).getByRole('button', { name: 'Reset', exact: true }).click();
  await expect(forms.nth(0).locator('[name="date_to"]')).toHaveValue('2026-09-03');
});

test('selects a range and keeps only its endpoints in the API fields', async ({ page }) => {
  await mount(page, range('2026-09-01 to 2026-09-03'));
  await page.locator('.datepicker').click();
  await page.locator('[data-vc-date="2026-09-10"] button').click();
  await page.locator('[data-vc-date="2026-09-14"] button').click();
  await expect(page.locator('.datepicker')).toHaveValue('2026-09-10 to 2026-09-14');
  await expect(page.locator('[name="date_from"]')).toHaveValue('2026-09-10');
  await expect(page.locator('[name="date_to"]')).toHaveValue('2026-09-14');
});

test('accepts typed and open-ended ranges and rejects invalid dates', async ({ page }) => {
  await mount(page, range(' to 2026-09-03'));
  await expect(page.locator('[name="date_from"]')).toHaveValue('');
  await expect(page.locator('[name="date_to"]')).toHaveValue('2026-09-03');
  const input = page.locator('.datepicker');
  await input.fill('2026-02-30');
  await input.dispatchEvent('change');
  expect(await input.evaluate((el: HTMLInputElement) => el.checkValidity())).toBe(false);
  await input.fill('2026-09-05 to 2026-09-07');
  await input.dispatchEvent('change');
  await expect(page.locator('[name="date_to"]')).toHaveValue('2026-09-07');
  await input.fill('');
  await expect(page.locator('[name="date_from"]')).toHaveValue('');
  await expect(page.locator('[name="date_to"]')).toHaveValue('');
});

test('single dates use ISO values, retain calendar CSS and follow live theme changes', async ({ page }) => {
  await mount(page, '<form><div class="input-icon" style="width:320px"><input class="form-control datepicker" name="date" value="2026-09-05"></div></form>');
  await page.locator('.datepicker').focus();
  const calendar = page.locator('[data-vc="calendar"]');
  await expect(calendar).toBeVisible();
  expect((await calendar.boundingBox())!.width).toBeGreaterThan(200);
  await page.evaluate(() => document.documentElement.dataset.bsTheme = 'dark');
  await expect(calendar).toHaveAttribute('data-bs-theme', 'dark');
  await page.locator('[data-vc-date="2026-09-12"] button').click();
  await expect(page.locator('.datepicker')).toHaveValue('2026-09-12');
  await expect(calendar).toHaveAttribute('aria-hidden', 'true');
  await expect(calendar).toHaveCSS('opacity', '0');
  await page.evaluate(() => (window as any).initDatepickers());
  await expect(page.getByRole('button', { name: 'Clear date' })).toHaveCount(1);
});

test('supports dates before 1970 and month/year navigation', async ({ page }) => {
  await mount(page, '<div class="input-icon"><input class="form-control datepicker" value="1950-06-15"></div>');
  await page.locator('.datepicker').focus();
  await expect(page.locator('[data-vc-date="1950-06-15"]')).toHaveAttribute('aria-selected', 'true');
  await page.getByRole('button', { name: 'Select year, current selected year: 1950', exact: true }).click();
  await expect(page.locator('[data-vc="years"]')).toBeVisible();
});

test('autosize grows plain-text notes when content changes', async ({ page }) => {
  await mount(page, '<textarea class="form-control" data-bs-toggle="autosize" rows="2"></textarea>');
  const textarea = page.locator('textarea');
  const before = (await textarea.boundingBox())!.height;
  await textarea.fill(Array(12).fill('An internal note').join('\n'));
  expect((await textarea.boundingBox())!.height).toBeGreaterThan(before);
  await textarea.fill('Short note');
  expect((await textarea.boundingBox())!.height).toBeLessThan(200);
});

for (const mode of ['success', 'unavailable', 'denied'] as const) {
  test(`clipboard supports ${mode} Clipboard API access with accessible feedback`, async ({ page }) => {
    await page.evaluate((mode) => {
      (window as any).copiedText = '';
      Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value: mode === 'unavailable' ? undefined : {
          writeText: async (text: string) => {
            if (mode === 'denied') throw new Error('Permission denied');
            (window as any).copiedText = text;
          },
        },
      });
      document.execCommand = (command) => {
        if (command !== 'copy') return false;
        (window as any).copiedText = (document.activeElement as HTMLTextAreaElement).value;
        return true;
      };
    }, mode);
    await mount(page, `<textarea id="snippet">Example snippet</textarea><button type="button" class="clipboard-copy" data-bs-toggle="clipboard" data-bs-target="#snippet"><span class="clipboard-label">Copy</span><span class="clipboard-feedback" hidden>Copied</span></button>`);
    const button = page.getByRole('button', { name: 'Copy', exact: true });
    await button.focus();
    await button.press('Enter');
    await expect(page.locator('.clipboard-feedback')).toBeVisible();
    expect(await page.evaluate(() => (window as any).copiedText)).toBe('Example snippet');
    await expect(page.locator('.clipboard-copy')).toBeFocused();
    await expect(page.locator('.clipboard-label')).toBeVisible({ timeout: 4000 });
  });
}

test('a same-day range preserves both endpoints', async ({ page }) => {
  await mount(page, range('2026-09-01 to 2026-09-03'));
  await page.locator('.datepicker').click();
  const day = page.locator('[data-vc-date="2026-09-10"] button');
  await day.click();
  await day.click();
  await expect(page.locator('[name="date_from"]')).toHaveValue('2026-09-10');
  await expect(page.locator('[name="date_to"]')).toHaveValue('2026-09-10');
});

test('strength feedback is translated and does not change password validity', async ({ page }) => {
  const config = encodeURIComponent(JSON.stringify({ input: '#password', messages: { weak: 'Faible', fair: 'Moyen', good: 'Bon', strong: 'Fort' } }));
  await mount(page, `<div><input class="form-control" id="password" type="password" required><div class="strength" data-bs-strength data-bs-config="${config}"><span class="strength-segment"></span><span class="strength-segment"></span><span class="strength-segment"></span><span class="strength-segment"></span></div><div class="strength-text form-text"></div></div>`);
  await page.locator('#password').fill('a');
  await expect(page.locator('.strength-text')).toHaveText('Faible');
  expect(await page.locator('#password').evaluate((el: HTMLInputElement) => el.checkValidity())).toBe(true);
  await page.locator('#password').fill('LongExample!9285_Another');
  await expect(page.locator('.strength')).toHaveAttribute('aria-valuetext', 'Fort');
  await page.locator('#password').fill('');
  await expect(page.locator('.strength')).toHaveAttribute('aria-valuenow', '0');
});

test('Tom Select follows Tabler input sizing and keeps keyboard focus visible', async ({ page }) => {
  await mount(page, '<div class="row"><div class="col"><input class="form-control" id="reference"></div><div class="col"><select class="form-select test-select"><option>English</option><option>French</option></select></div></div>');
  const nativeHeight = (await page.locator('#reference').boundingBox())!.height;
  const selectHeight = (await page.locator('.ts-wrapper').boundingBox())!.height;
  expect(Math.abs(nativeHeight - selectHeight)).toBeLessThanOrEqual(3);
  await page.locator('.ts-control input').focus();
  await expect(page.locator('.ts-control')).toHaveCSS('outline-style', 'solid');
});
