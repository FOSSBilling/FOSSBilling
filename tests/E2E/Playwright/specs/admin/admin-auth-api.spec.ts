import { adminCredentials, authenticatedGet, expect, test } from '../../fixtures/e2e';

test('redirects logged-out admin pages to the staff login', async ({ incognitoPage }) => {
  await incognitoPage.goto('/admin/client');

  await expect(incognitoPage).toHaveURL(
    (url) => /^\/admin\/staff\/login\/?$/.test(new URL(url).pathname),
  );
  await expect(incognitoPage.locator('body')).toContainText('Login');
});

test('allows authenticated admin profile API requests', async ({ adminContext }) => {
  const response = await authenticatedGet(adminContext, '/api/admin/profile/get');
  expect(response.status()).toBe(200);

  const body = await response.json();
  expect(body.error).toBeNull();
  expect(body.result.id, 'admin id').toBeTruthy();
  expect(body.result.email, 'admin email').toBe(adminCredentials().email);
});

test('rejects admin API POST requests without a CSRF token', async ({ adminContext }) => {
  const response = await adminContext.request.post('/api/admin/profile/get');
  expect(response.status()).toBe(403);

  const body = await response.json();
  expect(body.result).toBeNull();
  expect(body.error.message).toBe('CSRF token invalid');
});
