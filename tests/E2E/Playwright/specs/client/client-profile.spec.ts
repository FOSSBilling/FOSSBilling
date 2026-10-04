import { openClientSession, expect, test } from '../../fixtures/e2e';
import { submitForm, waitForApiResponse } from '../../helpers/forms';

test('updates profile details', async ({ clientPage }) => {
  const updatedProfile = {
    firstName: 'Updated',
    lastName: 'Profile',
    company: 'Updated Playwright Company',
    country: 'GB',
    phoneCountryCode: '44',
    phone: '7700900123',
    // The stored number is raw, but the input renders it formatted.
    phoneDisplay: '7700 900123',
    address: '42 Updated Road',
    city: 'Updated City',
    state: 'Updated State',
    postcode: 'UP123',
  };

  await clientPage.goto('/client/profile');
  await expect(clientPage.locator('body')).toContainText('Update Details');

  const form = clientPage.locator('form#profile-update');
  await form.locator('input[name="first_name"]').fill(updatedProfile.firstName);
  await form.locator('input[name="last_name"]').fill(updatedProfile.lastName);
  await form.locator('input[name="company"]').fill(updatedProfile.company);
  await form.locator('select[name="country"]').selectOption(updatedProfile.country);
  await form.locator('input[name="phone"]').fill(updatedProfile.phone);
  await form.locator('input[name="address_1"]').fill(updatedProfile.address);
  await form.locator('input[name="city"]').fill(updatedProfile.city);
  await form.locator('input[name="state"]').fill(updatedProfile.state);
  await form.locator('input[name="postcode"]').fill(updatedProfile.postcode);

  const profileUpdate = waitForApiResponse(clientPage, '/api/client/profile/update');
  await submitForm(form);

  // The reloaded form values below prove the update was persisted server-side.
  expect((await profileUpdate).status()).toBe(200);

  await clientPage.reload();
  await expect(form.locator('input[name="first_name"]')).toHaveValue(updatedProfile.firstName);
  await expect(form.locator('input[name="last_name"]')).toHaveValue(updatedProfile.lastName);
  await expect(form.locator('input[name="company"]')).toHaveValue(updatedProfile.company);
  await expect(form.locator('select[name="country"]')).toHaveValue(updatedProfile.country);
  await expect(form.locator('input[name="phone_cc"]')).toHaveValue(updatedProfile.phoneCountryCode);
  await expect(form.locator('input[name="phone"]')).toHaveValue(updatedProfile.phoneDisplay);
  await expect(form.locator('input[name="address_1"]')).toHaveValue(updatedProfile.address);
  await expect(form.locator('input[name="city"]')).toHaveValue(updatedProfile.city);
  await expect(form.locator('input[name="state"]')).toHaveValue(updatedProfile.state);
  await expect(form.locator('input[name="postcode"]')).toHaveValue(updatedProfile.postcode);
});

test('changes the client password', async ({ browser, clientPage, testClient }) => {
  const oldPassword = testClient.password;
  const newPassword = 'PlaywrightClient2!';

  await clientPage.goto('/client/profile');
  await clientPage.locator('#pass-tab').click();

  const paneForm = clientPage.locator('#pass-tab-pane form');
  await paneForm.locator('input[name="current_password"]').fill(oldPassword);
  await paneForm.locator('input[name="new_password"]').fill(newPassword);
  await paneForm.locator('input[name="confirm_password"]').fill(newPassword);

  const passwordChange = waitForApiResponse(clientPage, '/api/client/profile/change_password');
  await submitForm(paneForm);

  // The stale-login and fresh-login checks below prove the password change server-side.
  expect((await passwordChange).status()).toBe(200);

  const staleLogin = await clientPage.context().request.post('/api/guest/client/login', {
    data: {
      email: testClient.email,
      password: oldPassword,
    },
  });
  const staleLoginBody = await staleLogin.json();
  expect(staleLoginBody.result).toBeNull();
  expect(staleLoginBody.error.message).toBe('Please check your login details.');

  const context = await openClientSession(browser, { ...testClient, password: newPassword });
  await context.close();
});
