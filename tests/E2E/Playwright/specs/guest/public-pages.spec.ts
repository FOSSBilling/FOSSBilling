import { expect, test } from '../../fixtures/e2e';

const pages = [
  { path: '/', text: null },
  { path: '/login', text: 'Login to Your Account' },
  { path: '/signup', text: 'Create a new account' },
  { path: '/password-reset', text: 'Reset Your Password' },
  { path: '/order', text: null },
];

for (const { path, text } of pages) {
  test(`loads ${path}`, async ({ incognitoPage }) => {
    await incognitoPage.goto(path);

    const body = incognitoPage.locator('body');
    await expect(body).toBeVisible();

    if (text) {
      await expect(body).toContainText(text);
    }
  });
}
