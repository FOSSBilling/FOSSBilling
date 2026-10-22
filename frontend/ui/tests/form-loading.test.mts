import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createFormLoading } from '../form-loading.ts';

function button(disabled = false, loading = false) {
  const classes = new Set(loading ? ['btn-loading'] : []);
  return {disabled, classes, textContent: 'Save Details', matches: () => true,
    classList: {contains: (name: string) => classes.has(name), add: (name: string) => classes.add(name), remove: (name: string) => classes.delete(name)}};
}

function fixture(run: (state: ReturnType<typeof setup>) => void, modal = false) {
  const oldDocument = globalThis.document;
  const oldRuntime = (globalThis as any).FOSSBilling;
  const state = setup(modal);
  globalThis.document = state.document as any;
  (globalThis as any).FOSSBilling = {ui: {}};
  try {run(state);} finally {globalThis.document = oldDocument; (globalThis as any).FOSSBilling = oldRuntime;}
}

function setup(modal: boolean) {
  const buttons = [button(), button(), button(true), button(false, true)];
  const statuses: any[] = [];
  const document = {
    querySelector: () => ({dataset: {loading: 'Translated Submitting'}}),
    createElement: () => ({className: '', textContent: '', attrs: new Map<string, string>(),
      setAttribute(name: string, value: string) {this.attrs.set(name, value);},
      remove() {statuses.splice(statuses.indexOf(this), 1);}}),
  };
  const dialog = {append: (status: unknown) => statuses.push(status)};
  const form = {elements: buttons, closest: () => modal ? dialog : null, before: (status: unknown) => statuses.push(status)};
  const presenter = createFormLoading();
  return {buttons, statuses, document, dialog, form, presenter};
}

test('only the submitted action spins while labels and prior disabled/loading states survive', () => fixture(f => {
  f.presenter.setFormLoading(f.form as any, true, f.buttons[1] as any);
  f.presenter.setFormLoading(f.form as any, true, f.buttons[0] as any);
  assert.equal(f.statuses.length, 1);
  assert.equal(f.statuses[0].textContent, 'Translated Submitting');
  assert.equal(f.statuses[0].attrs.get('role'), 'status');
  assert.equal(f.statuses[0].attrs.get('aria-atomic'), 'true');
  assert.deepEqual(f.buttons.map(b => b.classes.has('btn-loading')), [false, true, false, true]);
  assert.equal(f.buttons[1].textContent, 'Save Details');
  f.presenter.setFormLoading(f.form as any, false);
  f.presenter.setFormLoading(f.form as any, false);
  assert.deepEqual(f.buttons.map(b => b.classes.has('btn-loading')), [false, false, false, true]);
  assert.equal(f.statuses.length, 0);
}));

test('programmatic submissions keep the existing fallback and can retry with a different submitter', () => fixture(f => {
  f.presenter.setFormLoading(f.form as any, true);
  assert.deepEqual(f.buttons.map(b => b.classes.has('btn-loading')), [true, true, false, true]);
  f.presenter.setFormLoading(f.form as any, false);
  f.presenter.setFormLoading(f.form as any, true, f.buttons[0] as any);
  assert.equal(f.statuses.length, 1);
  assert.deepEqual(f.buttons.map(b => b.classes.has('btn-loading')), [true, false, false, true]);
  f.presenter.setFormLoading(f.form as any, false);
}));

test('native input submitters get progress text without assigning their spinner to another action', () => fixture(f => {
  const input = {matches: () => false, disabled: false, value: 'Save Details'};
  f.form.elements.push(input as any);
  f.presenter.setFormLoading(f.form as any, true, input as any);
  assert.equal(f.statuses.length, 1);
  assert.deepEqual(f.buttons.slice(0, 4).map(b => b.classes.has('btn-loading')), [false, false, false, true]);
  assert.equal(input.value, 'Save Details');
  f.presenter.setFormLoading(f.form as any, false);
}));

test('external form-associated submit buttons receive the same presentation', () => fixture(f => {
  const external = button();
  f.form.elements.push(external);
  f.presenter.setFormLoading(f.form as any, true, external as any);
  assert.equal(external.classes.has('btn-loading'), true);
  f.presenter.setFormLoading(f.form as any, false);
  assert.equal(external.classes.has('btn-loading'), false);
}));

test('modal status stays outside the busy form without changing the dialog layout', () => fixture(f => {
  f.form.before = () => assert.fail('Do not insert siblings beside modal-content');
  f.presenter.setFormLoading(f.form as any, true);
  assert.equal(f.statuses[0].className, 'visually-hidden');
  assert.equal(f.statuses[0].attrs.get('role'), 'status');
  f.presenter.setFormLoading(f.form as any, false);
  assert.equal(f.statuses.length, 0);
}, true));

test('theme adapters can supply translated labels and leave extension loading hooks intact', () => fixture(f => {
  const presenter = createFormLoading(() => 'Client Translation');
  const extension = () => {};
  (globalThis as any).FOSSBilling.ui.formLoading = extension;
  presenter.initFormLoading();
  assert.equal((globalThis as any).FOSSBilling.ui.formLoading, extension);
  presenter.setFormLoading(f.form as any, true);
  assert.equal(f.statuses[0].textContent, 'Client Translation');
  presenter.setFormLoading(f.form as any, false);
}));
