import assert from 'node:assert/strict';
import { test } from 'node:test';
import flashMessage from '../ui/flash.ts';

test('flash messages retain severity across navigation and consume legacy plain text once', () => {
  const saved = new Map<string, string>();
  const shown: unknown[] = [];
  const navigated: unknown[] = [];
  Object.assign(globalThis, {
    sessionStorage: {getItem: (key: string) => saved.get(key) ?? null, setItem: (key: string, value: string) => saved.set(key, value), removeItem: (key: string) => saved.delete(key)},
    FOSSBilling: {message: (...args: unknown[]) => shown.push(args)},
    window: {location: {reload: () => navigated.push('reload'), assign: (url: string) => navigated.push(url)}},
  });
  flashMessage({message: 'Password changed', type: 'success', reload: '/login'});
  assert.equal(saved.get('flash-message'), 'Password changed');
  assert.deepEqual(navigated, ['/login']);
  flashMessage(); flashMessage();
  assert.deepEqual(shown, [['Password changed', 'success']]);
  saved.set('flash-message', 'Legacy message');
  flashMessage();
  assert.deepEqual(shown[1], ['Legacy message', 'info']);
  flashMessage({message: 'Saved', type: 'success'});
  assert.deepEqual(shown[2], ['Saved', 'success']);
  assert.equal(saved.size, 0);

  (globalThis as any).sessionStorage = {getItem() {throw new Error('Blocked');}, setItem() {throw new Error('Blocked');}};
  assert.doesNotThrow(() => flashMessage());
  flashMessage({message: 'Saved', reload: true});
  assert.equal(navigated[1], 'reload');
});
