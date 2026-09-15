import { expect, test } from '../../fixtures/e2e';

const protectedPages = [
  '/client',
  '/client/profile',
  '/order/service',
  '/invoice',
  '/support',
  '/email',
];

for (const path of protectedPages) {
  test(`redirects logged-out requests for ${path} to the client login`, async ({ incognitoPage, request }) => {
    const response = await request.get(path, { maxRedirects: 0 });
    expect(response.status()).toBe(302);
    expect(response.headers()['location']).toMatch(/\/login\/?$/);

    await incognitoPage.goto(path);
    await expect(incognitoPage).toHaveURL((url) => /^\/login\/?$/.test(new URL(url).pathname));
    await expect(incognitoPage.locator('body')).toContainText('Login to Your Account');
  });
}
