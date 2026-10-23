import { expect, test } from '../../fixtures/e2e';

const pages = [
  { path: '/', text: null },
  { path: '/login', text: 'Sign In' },
  { path: '/signup', text: 'Create an Account' },
  { path: '/password-reset', text: 'Reset Password' },
  { path: '/order', text: null },
];

for (const { path, text } of pages) {
  test(`loads ${path}`, async ({ incognitoPage }) => {
    await incognitoPage.goto(path);

    const body = incognitoPage.locator('body');
    await expect(body).toBeVisible();

    if (text) {
      await expect(incognitoPage.getByRole('heading', { name: text, level: 1 })).toBeVisible();
    }
  });
}
