import assert from 'node:assert/strict';
import { test } from 'node:test';
import initRequiredChoices, { validateRequiredChoices } from '../ui/required-choices.ts';

test('required checkbox groups accept any choice and preserve extension custom errors', () => {
  const first = {checked: false, validationMessage: '', validity: {valid: true, customError: false},
    setCustomValidity(message: string) {this.validationMessage = message; this.validity.valid = !message; this.validity.customError = !!message;}, getAttribute: () => 'true'};
  const second = {checked: false};
  let cleared = 0, shown = 0;
  (globalThis as any).FOSSBilling = {ui: {clearFieldError: () => cleared++, fieldError: () => shown++}};
  const group = {querySelectorAll: () => [first, second], dataset: {requiredMessage: 'Choose one or more options.'}};
  validateRequiredChoices(group as any);
  assert.equal(first.validity.valid, false);
  assert.equal(first.validationMessage, 'Choose one or more options.');
  second.checked = true; validateRequiredChoices(group as any);
  assert.equal(first.validity.valid, true);
  assert.equal(cleared, 1);
  second.checked = false; validateRequiredChoices(group as any);
  assert.equal(first.validity.valid, false);
  assert.equal(shown, 2);
  first.setCustomValidity('Extension rule');
  second.checked = true; validateRequiredChoices(group as any);
  assert.equal(first.validationMessage, 'Extension rule');
});

test('late extension forms validate before API submission and respect opted-out or canceled submissions', () => {
  const listeners = new Map<string, (event: any) => void>();
  (globalThis as any).document = {querySelectorAll: () => [], addEventListener: (name: string, handler: any) => listeners.set(name, handler)};
  (globalThis as any).FOSSBilling = {ui: {}};
  initRequiredChoices();
  let reports = 0;
  const first = {checked: false, validationMessage: '', validity: {valid: true, customError: false},
    setCustomValidity(message: string) {this.validationMessage = message; this.validity.valid = !message; this.validity.customError = !!message;},
    getAttribute: () => null, reportValidity() {reports++;}};
  const group = {querySelectorAll: () => [first], querySelector: () => first, dataset: {requiredMessage: 'Choose an option.'}};
  const form = {matches: () => true, noValidate: false, querySelectorAll: () => [group]};
  const event = () => ({target: form, defaultPrevented: false, preventDefault() {this.defaultPrevented = true;}});
  const invalid = event(); listeners.get('submit')!(invalid);
  assert.equal(invalid.defaultPrevented, true);
  assert.equal(reports, 1);
  first.checked = true;
  const valid = event(); listeners.get('submit')!(valid);
  assert.equal(valid.defaultPrevented, false);
  first.checked = false; form.noValidate = true;
  listeners.get('submit')!(event());
  assert.equal(reports, 1);
  form.noValidate = false;
  const canceled = event(); canceled.preventDefault(); listeners.get('submit')!(canceled);
  assert.equal(reports, 1);
});
