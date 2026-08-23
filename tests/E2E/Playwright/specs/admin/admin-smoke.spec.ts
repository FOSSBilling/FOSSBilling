import { expect, test } from '../../fixtures/e2e';

const pages = [
  '/admin',
  '/admin/client',
  '/admin/order',
  '/admin/invoice',
  '/admin/product',
  '/admin/system',
  '/admin/extension',
];

test('loads core admin pages successfully', async ({ adminPage }) => {
  for (const path of pages) {
    await adminPage.goto(path);
    await expect(adminPage).toHaveURL((url) => new URL(url).pathname === path);
    await expect(adminPage.locator('body')).toBeVisible();
  }
});
