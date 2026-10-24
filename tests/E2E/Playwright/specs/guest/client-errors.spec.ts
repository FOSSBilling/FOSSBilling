import { expect, test } from '@playwright/test';
import { build } from 'esbuild';

let script: string;

test.beforeAll(async () => {
  const result = await build({
    stdin: {
      contents: `import './frontend/core/fossbilling.ts';
        import './frontend/core/api.ts';
        import './src/themes/default/client/assets/huraga.ts';`,
      resolveDir: process.cwd(),
    },
    bundle: true,
    write: false,
    format: 'iife',
  });
  script = result.outputFiles[0].text;
});

test.beforeEach(async ({ page }) => {
  await page.route('https://billing.example/test.js', route => route.fulfill({
    contentType: 'text/javascript; charset=utf-8', body: script,
  }));
  await page.route('https://billing.example/', route => route.fulfill({
    contentType: 'text/html; charset=utf-8',
    body: `<div class="toast-container"></div>
      <form action="/api/client/profile/update" method="post" data-fb-api='{"message":"Saved"}'>
        <button>Save</button>
      </form><script src="/test.js"></script>`,
  }));
  await page.goto('https://billing.example/');
});

for (const kind of ['exception', 'rejection']) {
  test(`unrelated ${kind} stays in developer diagnostics without a toast`, async ({ page }) => {
    const diagnostic = page.waitForEvent('pageerror');
    await page.evaluate(kind => {
      if (kind === 'exception') {
        setTimeout(() => { throw new Error('Unrelated script failure'); }, 0);
      } else {
        void Promise.reject(new Error('Unrelated script failure'));
      }
    }, kind);
    expect((await diagnostic).message).toContain('Unrelated script failure');
    // Check immediately so an unwanted toast cannot auto-hide before the assertion passes.
    expect(await page.locator('.toast').count()).toBe(0);
    await page.evaluate(() => (window as any).FOSSBilling.message('Application notice'));
    await expect(page.locator('.toast-body')).toHaveText('Application notice');
  });
}

test('API form failures display one error and restore the submit button', async ({ page }) => {
  await page.route('**/api/client/profile/update', route => route.fulfill({
    json: { error: { message: 'Invalid email address', code: 422 } },
  }));
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(page.locator('.toast-body')).toHaveText('Invalid email address (422)');
  await expect(page.getByRole('button', { name: 'Save' })).toBeEnabled();
});

test('successful API forms retain their notification', async ({ page }) => {
  await page.route('**/api/client/profile/update', route => route.fulfill({ json: { result: true } }));
  await page.getByRole('button', { name: 'Save' }).click();
  await expect(page.locator('.toast-body')).toHaveText('Saved');
});
