import { expect, test } from '../../fixtures/e2e';

test('logs in as the installed administrator', async ({ adminPage }) => {
  await expect(adminPage).toHaveTitle(/Dashboard/);
  await expect(adminPage.locator('body')).toContainText('Clients');
});
