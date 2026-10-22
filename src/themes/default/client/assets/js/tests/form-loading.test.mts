import assert from 'node:assert/strict';
import { test } from 'node:test';
import { setFormLoading } from '../ui/form-loading.ts';

test('form loading preserves labels and pre-existing states and clears feedback for a retry', () => {
  const button = (disabled = false, loading = false) => {
    const classes = new Set(loading ? ['btn-loading'] : []);
    return {disabled, classes, matches: () => true, textContent: 'Save Details', classList: {contains: (name: string) => classes.has(name), add: (name: string) => classes.add(name), remove: (name: string) => classes.delete(name)}};
  };
  const buttons = [button(), button(true), button(false, true)];
  const statuses: any[] = [];
  (globalThis as any).document = {
    querySelector: () => ({dataset: {clientLoading: 'Please wait'}}),
    createElement: () => ({className: '', textContent: '', attrs: new Map(), setAttribute(name: string, value: string) {this.attrs.set(name, value);}, remove() {statuses.splice(statuses.indexOf(this), 1);}}),
  };
  const form = {elements: buttons, closest: () => null, before: (status: unknown) => statuses.push(status)};
  setFormLoading(form as any, true); setFormLoading(form as any, true);
  assert.equal(statuses.length, 1);
  assert.equal(statuses[0].attrs.get('role'), 'status');
  assert.equal(statuses[0].textContent, 'Please wait');
  assert.deepEqual(buttons.map(b => b.classes.has('btn-loading')), [true, false, true]);
  assert.equal(buttons[0].textContent, 'Save Details');
  setFormLoading(form as any, false);
  assert.equal(statuses.length, 0);
  assert.deepEqual(buttons.map(b => b.classes.has('btn-loading')), [false, false, true]);
  setFormLoading(form as any, true); setFormLoading(form as any, false);
  assert.equal(statuses.length, 0);
});
