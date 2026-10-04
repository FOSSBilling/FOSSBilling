import { authenticatedGet, expect, test } from '../../fixtures/e2e';
import { defaultClientPassword, uniqueSuffix } from '../../helpers/client-factory';
import { fillClientSignupForm, submitForm, waitForApiResponse } from '../../helpers/forms';

test('creates a new client account and automatically logs in', async ({ incognitoPage }) => {
  const client = {
    first_name: 'Playwright',
    last_name: 'Signup',
    email: `playwright-signup-${uniqueSuffix()}@example.com`,
    password: defaultClientPassword,
  };

  await incognitoPage.goto('/signup');
  await expect(incognitoPage.locator('body')).toContainText('Create a new account');

  const signupResponse = waitForApiResponse(incognitoPage, '/api/guest/client/create');

  await fillClientSignupForm(incognitoPage, client);
  await submitForm(incognitoPage.locator('form[action*="/api/guest/client/create"]'));

  const response = await signupResponse;
  // The response body cannot be read after the auto-login redirect navigates away;
  // the profile request below proves both account creation and the logged-in session.
  expect(response.status()).toBe(200);

  await expect(incognitoPage).toHaveURL((url) => new URL(url).pathname === '/', { timeout: 10_000 });

  const profileResponse = await authenticatedGet(incognitoPage.context(), '/api/client/profile/get');
  expect(profileResponse.status()).toBe(200);

  const profile = await profileResponse.json();
  expect(profile.error).toBeNull();
  expect(profile.result.email).toBe(client.email);
});
