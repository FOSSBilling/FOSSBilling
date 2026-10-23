import assert from 'node:assert/strict';
import { test } from 'node:test';
import initAuthForms from '../auth-forms.ts';

class Input extends EventTarget {
  value = '';
  type = 'password';
  checked = false;
  validity = { valid: true };
  validations = 0;
  reports = 0;
  attrs = new Map<string, string>();
  getAttribute(name: string) { return this.attrs.get(name) ?? null; }
  setCustomValidity(message: string) { this.validity.valid = !message; this.validations++; }
  reportValidity() { this.reports++; }
}

function fixture() {
  const password = new Input();
  const confirmation = new Input();
  const toggle = new Input();
  const form = Object.assign(new EventTarget(), {
    dataset: { passwordMismatch: 'Passwords do not match' },
    querySelector(selector: string) {
      if (selector === '[data-auth-new-password]') return password;
      if (selector === '[data-auth-confirm-password]') return confirmation;
      return toggle;
    },
    querySelectorAll() { return [password, confirmation]; },
  });
  (globalThis as any).document = { querySelectorAll: () => [form] };
  return { form, password, confirmation, toggle };
}

test('password validation catches prefilled mismatches and clears when corrected', () => {
  const { password, confirmation } = fixture();
  password.value = 'First-password'; confirmation.value = 'Different-password';
  initAuthForms();
  assert.equal(confirmation.validity.valid, false);
  confirmation.value = password.value;
  confirmation.dispatchEvent(new Event('input'));
  assert.equal(confirmation.validity.valid, true);
});

test('password validation cancels programmatic mismatched submissions and binds once', () => {
  const { form, password, confirmation, toggle } = fixture();
  initAuthForms(); initAuthForms();
  const before = confirmation.validations;
  password.value = 'New-password'; confirmation.value = 'Other-password';
  const event = new Event('submit', { cancelable: true });
  form.dispatchEvent(event);
  assert.equal(event.defaultPrevented, true);
  assert.equal(confirmation.reports, 1);
  assert.equal(confirmation.validations, before + 1);
  toggle.checked = true; toggle.dispatchEvent(new Event('change'));
  assert.equal(password.type, 'text'); assert.equal(confirmation.type, 'text');
  toggle.checked = false; toggle.dispatchEvent(new Event('change'));
  assert.equal(password.type, 'password'); assert.equal(confirmation.type, 'password');
});

test('editing the original password clears or updates existing confirmation feedback without premature errors', () => {
  const {password, confirmation} = fixture();
  let cleared = 0, updated = 0;
  (globalThis as any).FOSSBilling = {ui: {
    clearFieldError(field: unknown) {assert.equal(field, confirmation); cleared++; confirmation.attrs.delete('aria-invalid');},
    fieldError(field: unknown) {assert.equal(field, confirmation); updated++;},
  }};
  password.value = 'Original123'; confirmation.value = 'Different123';
  initAuthForms();
  assert.equal(updated, 0);
  confirmation.attrs.set('aria-invalid', 'true');
  password.value = confirmation.value; password.dispatchEvent(new Event('input'));
  assert.equal(confirmation.validity.valid, true);
  assert.equal(cleared, 1);
  confirmation.attrs.set('aria-invalid', 'true');
  password.value = 'Other123'; password.dispatchEvent(new Event('change'));
  assert.equal(confirmation.validity.valid, false);
  assert.equal(updated, 1);
});

test('silent password-manager fills refresh validity before tap and Enter submission without intercepting them', () => {
  const {form, password, confirmation} = fixture();
  (globalThis as any).FOSSBilling = {ui: {}};
  password.value = 'Original123'; confirmation.value = 'Different123';
  initAuthForms();
  assert.equal(confirmation.validity.valid, false);
  confirmation.value = password.value;
  const click = new Event('click', {cancelable: true});
  form.dispatchEvent(click);
  assert.equal(confirmation.validity.valid, true);
  assert.equal(click.defaultPrevented, false);
  confirmation.value = 'Mismatch123'; form.dispatchEvent(new Event('focusin'));
  assert.equal(confirmation.validity.valid, false);
  password.value = confirmation.value;
  const enter = Object.assign(new Event('keydown', {cancelable: true}), {key: 'Enter'});
  form.dispatchEvent(enter);
  assert.equal(confirmation.validity.valid, true);
  assert.equal(enter.defaultPrevented, false);
});
