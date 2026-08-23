import { expect, type Locator, type Page, type Response } from '@playwright/test';

/**
 * Waits for the next POST response whose URL path contains the given API path fragment.
 * Register the returned promise before triggering the action that fires the request.
 */
export function waitForApiResponse(page: Page, apiPathFragment: string): Promise<Response> {
  return page.waitForResponse(
    (response) =>
      response.request().method() === 'POST' &&
      new URL(response.url()).pathname.includes(apiPathFragment),
  );
}

/**
 * Programmatically submits a form while still firing its submit event listeners.
 */
export async function submitForm(form: Locator): Promise<void> {
  await form.evaluate((element) => (element as HTMLFormElement).requestSubmit());
}

/**
 * Fills an input and verifies the final value, honoring any maxlength truncation.
 */
export async function typeAndVerify(input: Locator, value: string): Promise<void> {
  await expect(input).toBeVisible();
  await expect(input).toBeEnabled();

  await input.fill(value);

  const maxLengthAttribute = await input.getAttribute('maxlength');
  const maxLength = maxLengthAttribute === null ? -1 : Number.parseInt(maxLengthAttribute, 10);
  const expectedValue = Number.isInteger(maxLength) && maxLength >= 0 ? value.slice(0, maxLength) : value;

  await expect(input).toHaveValue(expectedValue);
}

/**
 * Opens a Bootstrap modal by clicking its trigger and waits until it is fully shown.
 */
export async function openBootstrapModal(trigger: Locator, modal: Locator): Promise<void> {
  await trigger.click();
  await expect(modal).toBeVisible();
  await expect(modal).toHaveClass(/\bshow\b/);
}

/**
 * Fills a FOSSBilling rich text editor by typing into the visible CKEditor editable
 * area; the core runtime syncs typed content back to the underlying textarea.
 */
export async function setEditorContent(scope: Locator, content: string): Promise<void> {
  const editor = scope.locator('.ck-content[contenteditable="true"]').first();
  await expect(editor).toBeVisible();

  await editor.click();
  await editor.press('ControlOrMeta+a');
  await editor.pressSequentially(content);
}

export interface SignupClient {
  first_name: string;
  last_name: string;
  email: string;
  password: string;
}

/**
 * Heuristically fills the client signup form: known fields get their values,
 * any remaining empty required inputs are backfilled, selects are resolved,
 * and required checkboxes are checked.
 */
export async function fillClientSignupForm(page: Page, client: SignupClient): Promise<void> {
  const values: Record<string, string> = {
    company: 'FOSSBilling Test Company',
    birthday: '1990-01-01',
    address_1: '1 Playwright Street',
    address_2: 'Suite 2',
    city: 'Test City',
    state: 'Test State',
    postcode: '12345',
    phone_cc: '1',
    phone: '5551234567',
    ...client,
    password_confirm: client.password,
  };

  const form = page.locator('form[action*="/api/guest/client/create"]');

  for (const [name, value] of Object.entries(values)) {
    const field = form.locator(`[name="${name}"]`).first();

    if ((await field.count()) === 0) {
      continue;
    }

    const fieldType = ((await field.getAttribute('type')) ?? '').toLowerCase();
    if (fieldType === 'hidden') {
      continue;
    }

    await field.fill(value);
  }

  // Backfill any remaining empty required text/textarea inputs.
  const requiredFields = form.locator('input[required], textarea[required]');
  const requiredCount = await requiredFields.count();

  for (let index = 0; index < requiredCount; index++) {
    const field = requiredFields.nth(index);
    const name = await field.getAttribute('name');
    const type = ((await field.getAttribute('type')) ?? '').toLowerCase();

    if (!name || Object.prototype.hasOwnProperty.call(values, name)) {
      continue;
    }

    if (['checkbox', 'radio', 'hidden', 'password'].includes(type)) {
      continue;
    }

    if (!(await field.inputValue())) {
      await field.fill('Playwright test value');
    }
  }

  const gender = form.locator('select[name="gender"]').first();
  if ((await gender.count()) > 0) {
    await gender.selectOption('other');
  }

  const requiredSelects = form.locator('select[required]');
  const selectCount = await requiredSelects.count();

  for (let index = 0; index < selectCount; index++) {
    const select = requiredSelects.nth(index);

    if (!(await select.inputValue())) {
      const firstOption = select.locator('option[value!=""]').first();
      const optionValue = await firstOption.getAttribute('value');

      if (optionValue) {
        await select.selectOption(optionValue);
      }
    }
  }

  const country = form.locator('select[name="country"]').first();
  if ((await country.count()) > 0 && !(await country.inputValue())) {
    const firstCountry = await country.locator('option[value!=""]').first().getAttribute('value');

    if (firstCountry) {
      await country.selectOption(firstCountry);
    }
  }

  const checkboxes = form.locator('input[type="checkbox"][required]');
  const checkboxCount = await checkboxes.count();

  for (let index = 0; index < checkboxCount; index++) {
    await checkboxes.nth(index).setChecked(true, { force: true });
  }
}
