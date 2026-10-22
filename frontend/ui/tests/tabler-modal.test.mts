import assert from 'node:assert/strict';
import { test } from 'node:test';
import { bindModalLifecycle, hideModals, labelModal } from '../tabler-modal.ts';

class Element extends EventTarget {
  isConnected = true;
  disabled = false;
  id = '';
  htmlFor = '';
  focused = 0;
  attributes = new Map<string, string>();
  children = new Map<string, Element>();
  focus() { this.focused++; }
  getClientRects() { return this.isConnected ? [{}] : []; }
  remove() { this.isConnected = false; }
  setAttribute(name: string, value: string) { this.attributes.set(name, value); }
  hasAttribute(name: string) { return this.attributes.has(name); }
  querySelector(selector: string) { return this.children.get(selector) || null; }
}

function fixture(run: (state: ReturnType<typeof setup>) => void, previous = false) {
  const oldDocument = globalThis.document;
  const state = setup(previous);
  globalThis.document = state.document as any;
  try { run(state); } finally { globalThis.document = oldDocument; }
}

function setup(hasPrevious: boolean) {
  const origin = new Element();
  const element = new Element();
  const confirm = new Element();
  const safe = new Element();
  const underlying = hasPrevious ? new Element() : null;
  const events: string[] = [];
  const instance = {show() {events.push('show');}, hide() {events.push('hide');}, dispose() {events.push('dispose');}};
  const previous = {show() {events.push('previous.show');}, hide() {events.push('previous.hide');}, dispose() {}};
  const document = {
    activeElement: origin,
    querySelector: () => underlying,
    querySelectorAll: () => underlying ? [element, underlying] : [element],
  };
  const library = {getInstance: (node: unknown) => node === underlying ? previous : instance};
  const bind = (options = {}) => bindModalLifecycle(element as any, instance, library as any, {
    confirmButton: confirm as any, initialFocus: safe as any,
    onConfirm: (value?: string) => events.push('confirm:' + (value || '')),
    onCancel: () => events.push('cancel'), onClose: () => events.push('close'), ...options,
  });
  return {origin, element, confirm, safe, underlying, events, instance, library, document, bind};
}

test('client waits for hidden, confirms once, disposes and restores focus before callbacks', () => fixture(f => {
  const dialog = f.bind();
  assert.equal(f.confirm.disabled, true);
  dialog.confirm('premature');
  assert.deepEqual(f.events, []);
  dialog.show();
  f.element.dispatchEvent(new Event('shown.bs.modal'));
  assert.equal(f.confirm.disabled, false);
  assert.equal(f.safe.focused, 1);
  dialog.confirm('entered text');
  dialog.confirm('duplicate');
  dialog.cancel();
  assert.equal(f.confirm.disabled, true);
  assert.deepEqual(f.events, ['show', 'hide']);
  f.element.dispatchEvent(new Event('hidden.bs.modal'));
  f.element.dispatchEvent(new Event('hidden.bs.modal'));
  assert.deepEqual(f.events, ['show', 'hide', 'dispose', 'confirm:entered text', 'close']);
  assert.equal(f.origin.focused, 1);
  assert.equal(f.element.isConnected, false);
}));

test('admin keeps action callbacks immediate but closes exactly once after cleanup', () => fixture(f => {
  const dialog = f.bind({callbackTiming: 'action'});
  dialog.show();
  f.element.dispatchEvent(new Event('shown.bs.modal'));
  dialog.confirm('prompt value');
  dialog.confirm('duplicate');
  assert.deepEqual(f.events, ['show', 'confirm:prompt value', 'hide']);
  f.element.dispatchEvent(new Event('hidden.bs.modal'));
  assert.deepEqual(f.events, ['show', 'confirm:prompt value', 'hide', 'dispose', 'close']);
}));

test('cancel runs only its own callback and close, while Escape backdrop and close only close', () => {
  for (const action of ['cancel', 'dismiss']) fixture(f => {
    const dialog = f.bind();
    dialog.show();
    f.element.dispatchEvent(new Event('shown.bs.modal'));
    if (action === 'cancel') dialog.cancel();
    f.element.dispatchEvent(new Event('hidden.bs.modal'));
    assert.equal(f.events.filter(x => x === 'cancel').length, action === 'cancel' ? 1 : 0);
    assert.equal(f.events.filter(x => x === 'close').length, 1);
    assert.equal(f.events.some(x => x.startsWith('confirm:')), false);
  });
});

test('a confirmation over an existing dialog waits and restores it after cancellation', () => fixture(f => {
  const dialog = f.bind();
  dialog.show();
  assert.deepEqual(f.events, ['previous.hide']);
  f.underlying!.dispatchEvent(new Event('hidden.bs.modal'));
  assert.deepEqual(f.events, ['previous.hide', 'show']);
  f.element.dispatchEvent(new Event('shown.bs.modal'));
  dialog.cancel();
  f.element.dispatchEvent(new Event('hidden.bs.modal'));
  assert.deepEqual(f.events, ['previous.hide', 'show', 'hide', 'dispose', 'previous.show', 'cancel', 'close']);
  assert.equal(f.origin.focused, 0);
  f.underlying!.dispatchEvent(new Event('shown.bs.modal'));
  assert.equal(f.origin.focused, 1);
}, true));

test('confirming leaves the underlying modal closed and removed origins are not focused', () => fixture(f => {
  const dialog = f.bind();
  dialog.show();
  f.underlying!.dispatchEvent(new Event('hidden.bs.modal'));
  f.element.dispatchEvent(new Event('shown.bs.modal'));
  f.origin.isConnected = false;
  dialog.confirm();
  f.element.dispatchEvent(new Event('hidden.bs.modal'));
  assert.equal(f.events.includes('previous.show'), false);
  assert.equal(f.origin.focused, 0);
}, true));

test('action callback errors still hide and delayed actions close after cleanup', () => {
  fixture(f => {
    const dialog = f.bind({callbackTiming: 'action', onConfirm() {throw new Error('Extension error');}});
    dialog.show();
    f.element.dispatchEvent(new Event('shown.bs.modal'));
    assert.throws(() => dialog.confirm(), /Extension error/);
    assert.equal(f.events.at(-1), 'hide');
  });
  fixture(f => {
    const dialog = f.bind({onConfirm() {f.events.push('extension');}});
    dialog.show();
    f.element.dispatchEvent(new Event('shown.bs.modal'));
    dialog.confirm();
    f.element.dispatchEvent(new Event('hidden.bs.modal'));
    assert.deepEqual(f.events.slice(-3), ['dispose', 'extension', 'close']);
  });
});

test('hideAll and closeAll adapters can safely skip uninitialized modals', () => fixture(f => {
  hideModals(f.library as any);
  assert.deepEqual(f.events, ['hide']);
  hideModals({getInstance: () => null});
  assert.deepEqual(f.events, ['hide']);
}));

test('admin dialog naming preserves custom attributes and associates prompt labels', () => {
  const element = new Element();
  const title = new Element();
  const input = new Element();
  const label = new Element();
  input.id = 'prompt-input';
  element.children.set('.modal-title, .modal-body h3', title);
  element.children.set('#prompt-input', input);
  element.children.set('label', label);
  labelModal(element as any);
  assert.match(title.id, /^fb-api-dialog-\d+$/);
  assert.equal(element.attributes.get('aria-labelledby'), title.id);
  assert.equal(label.htmlFor, input.id);
  element.setAttribute('aria-labelledby', 'extension-heading');
  label.htmlFor = 'extension-input';
  labelModal(element as any);
  assert.equal(element.attributes.get('aria-labelledby'), 'extension-heading');
  assert.equal(label.htmlFor, 'extension-input');
});
