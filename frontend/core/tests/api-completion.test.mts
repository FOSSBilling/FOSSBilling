import assert from 'node:assert/strict';
import { test } from 'node:test';
import { completeApiAction } from '../api-completion.ts';
import flashMessage, { queueFlashMessage } from '../flash.ts';

function fixture(run: (state: ReturnType<typeof setup>) => void) {
  const original = {window: globalThis.window, document: globalThis.document,
    runtime: (globalThis as any).FOSSBilling, storage: (globalThis as any).sessionStorage, warn: console.warn};
  const state = setup();
  Object.assign(globalThis, {window: state.window, document: state.document, FOSSBilling: state.runtime, sessionStorage: state.storage});
  console.warn = (...args: unknown[]) => state.warnings.push(args);
  try {run(state);} finally {
    Object.assign(globalThis, {window: original.window, document: original.document, FOSSBilling: original.runtime, sessionStorage: original.storage});
    console.warn = original.warn;
  }
}

function setup() {
  const saved = new Map<string, string>();
  const shown: unknown[][] = [];
  const navigation: string[] = [];
  const warnings: unknown[][] = [];
  const runtime = {ui: {notify: (...args: unknown[]) => shown.push(args)}};
  const location = {href: 'https://billing.example/client/profile', origin: 'https://billing.example',
    reload: () => navigation.push('reload'), assign: (url: string) => navigation.push(url)};
  const window = {location, FOSSBilling: runtime} as any;
  const document = {querySelector: () => ({dataset: {formUpdated: 'Translated Changes Saved'}})};
  const storage = {getItem: (key: string) => saved.get(key) ?? null,
    setItem: (key: string, value: string) => saved.set(key, value), removeItem: (key: string) => saved.delete(key)};
  return {saved, shown, navigation, warnings, runtime, window, document, storage};
}

test('extension callbacks retain their window context and own feedback and navigation', () => fixture(f => {
  const result = {id: 7};
  f.window.afterSave = function (value: unknown) {
    assert.equal(this, f.window);
    assert.equal(value, result);
    return 'handled';
  };
  assert.equal(completeApiAction({callback: 'afterSave', message: 'Saved', reload: true}, result), 'handled');
  assert.deepEqual(f.shown, []);
  assert.deepEqual(f.navigation, []);
  assert.equal(f.saved.size, 0);
}));

test('invalid callbacks warn and still allow the declared completion behaviour', () => fixture(f => {
  completeApiAction({callback: 'missingExtension', message: 'Saved'}, {});
  assert.equal(f.warnings.length, 1);
  assert.deepEqual(f.shown, [['Saved', 'success']]);
}));

test('explicit success messages survive reload and are consumed exactly once', () => fixture(f => {
  completeApiAction({reload: true, message: 'Settings Saved'}, {});
  assert.deepEqual(f.navigation, ['reload']);
  assert.deepEqual(f.shown, []);
  assert.equal(f.saved.get('flash-message'), 'Settings Saved');
  assert.equal(f.saved.get('flash-message-type'), 'success');
  flashMessage(); flashMessage();
  assert.deepEqual(f.shown, [['Settings Saved', 'success']]);
  assert.equal(f.saved.size, 0);
}));

test('relative same-origin redirects retain the supplied message and severity', () => fixture(f => {
  completeApiAction({redirect: '/invoice/7#payment', message: 'Payment Submitted'}, {});
  assert.deepEqual(f.navigation, ['https://billing.example/invoice/7#payment']);
  assert.deepEqual(f.shown, []);
  flashMessage();
  assert.deepEqual(f.shown, [['Payment Submitted', 'success']]);
}));

test('redirecting to the current URL preserves reload semantics', () => fixture(f => {
  completeApiAction({redirect: f.window.location.href, message: 'Saved'}, {});
  assert.deepEqual(f.navigation, ['reload']);
  assert.equal(f.saved.get('flash-message'), 'Saved');
}));

test('external navigation does not leave success feedback waiting on an unrelated return visit', () => fixture(f => {
  completeApiAction({redirect: 'https://gateway.example/pay', message: 'Continue to Payment'}, {});
  assert.deepEqual(f.navigation, ['https://gateway.example/pay']);
  assert.equal(f.saved.size, 0);
}));

test('navigation without an explicit message stays silent and reload flags keep their legacy contract', () => fixture(f => {
  completeApiAction({reload: false}, {});
  assert.deepEqual(f.navigation, ['reload']);
  assert.deepEqual(f.shown, []);
  assert.equal(f.saved.size, 0);
}));

test('inline messages show once and an explicit empty message suppresses generic feedback', () => fixture(f => {
  completeApiAction({message: 'Saved <details>'}, false);
  completeApiAction({message: ''}, {});
  assert.deepEqual(f.shown, [['Saved <details>', 'success']]);
  assert.equal(f.saved.size, 0);
}));

test('default success labels are translated while unhandled empty outcomes stay unchanged', () => fixture(f => {
  completeApiAction({}, {id: 1});
  assert.deepEqual(f.shown, [['Translated Changes Saved', 'success']]);
  completeApiAction({}, null);
  assert.equal(f.shown.length, 1);
  assert.equal(f.warnings.length, 1);
}));

test('blocked storage never prevents navigation', () => fixture(f => {
  f.storage.setItem = () => {throw new Error('Blocked');};
  assert.doesNotThrow(() => completeApiAction({message: 'Saved', reload: true}, {}));
  assert.deepEqual(f.navigation, ['reload']);
}));

test('partial flash writes do not pair a new message with stale severity', () => fixture(f => {
  f.saved.set('flash-message-type', 'error');
  f.storage.setItem = (key: string, value: string) => {
    if (key === 'flash-message-type') throw new Error('Quota exceeded');
    f.saved.set(key, value);
    return f.saved;
  };
  queueFlashMessage('Saved', 'success');
  assert.equal(f.saved.size, 0);
}));

test('legacy flash payloads remain plain text and respect extension notification hooks', () => fixture(f => {
  f.saved.set('flash-message', '<img src=x onerror=bad()>');
  flashMessage();
  assert.deepEqual(f.shown, [['<img src=x onerror=bad()>', 'info']]);
}));
