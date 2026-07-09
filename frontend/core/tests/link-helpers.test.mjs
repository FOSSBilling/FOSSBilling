import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
  buildPromptParams,
  dispatchLinkAction,
  createLinkLoadingState,
} from '../link-helpers.mjs';

globalThis.window = globalThis;

const mockCreatedElement = () => ({
  className: '',
  _attrs: {},
  getAttribute(name) { return this._attrs[name] ?? null; },
  setAttribute(name, value) { this._attrs[name] = String(value); },
  classList: { _set: new Set(), add(c) { this._set.add(c); }, remove(c) { this._set.delete(c); }, contains(c) { return this._set.has(c); } },
});

globalThis.document = {
  createElement: (tag) => mockCreatedElement(),
  createTextNode: (text) => ({ nodeType: 3, textContent: text, nodeName: '#text' }),
};

const mockLinkElement = (overrides = {}) => ({
  innerHTML: '<span>Original</span>',
  classList: {
    _classes: new Set(overrides.disabled ? ['disabled'] : []),
    add(c) { this._classes.add(c); },
    remove(c) { this._classes.delete(c); },
    contains(c) { return this._classes.has(c); },
  },
  _attrs: {},
  getAttribute(name) { return this._attrs[name] ?? null; },
  setAttribute(name, value) { this._attrs[name] = String(value); },
  removeAttribute(name) { delete this._attrs[name]; },
  _children: null,
  replaceChildren(...nodes) { this._children = nodes; },
  _parent: null,
  closest() { return this._parent; },
  parentElement: null,
  ...overrides,
});

const mockEventTarget = () => {
  const listeners = {};
  return {
    addEventListener(name, fn) { listeners[name] = fn; },
    removeEventListener(name, fn) {
      if (listeners[name] === fn) { delete listeners[name]; }
    },
    _fire(name, event) { if (listeners[name]) { listeners[name](event); } },
    _has(name) { return !!listeners[name]; },
  };
};

describe('buildPromptParams', () => {
  test('builds single-key object from modal config', () => {
    const result = buildPromptParams({ key: 'domain' }, 'example.com');
    assert.deepEqual(result, { domain: 'example.com' });
  });

  test('preserves empty string values', () => {
    const result = buildPromptParams({ key: 'note' }, '');
    assert.deepEqual(result, { note: '' });
  });

  test('handles special characters in key and value', () => {
    const result = buildPromptParams({ key: 'weird-key' }, 'value with spaces');
    assert.deepEqual(result, { 'weird-key': 'value with spaces' });
  });
});

describe('dispatchLinkAction', () => {
  test('no modal property calls onRequest directly', () => {
    let called = null;
    const apiData = { href: '/api/test' };
    dispatchLinkAction(apiData, '/link', null, (method, href) => {
      called = { method, href };
    });
    assert.equal(called.method, 'GET');
    assert.equal(called.href, '/link');
  });

  test('no modal property does not pass params argument', () => {
    let callArgs;
    dispatchLinkAction({}, '/link', null, (...args) => { callArgs = args; });
    assert.equal(callArgs.length, 2);
  });

  test('native prompt with truthy value calls onRequest with param', () => {
    const origPrompt = globalThis.prompt;
    globalThis.prompt = () => 'typed-value';
    try {
      let called = null;
      const apiData = { modal: { type: 'prompt', key: 'name', label: 'Enter name', title: 'Prompt Title', value: 'default' } };
      dispatchLinkAction(apiData, '/link', null, (method, href, params) => {
        called = { method, href, params };
      });
      assert.equal(called.method, 'GET');
      assert.equal(called.href, '/link');
      assert.deepEqual(called.params, { name: 'typed-value' });
    } finally {
      globalThis.prompt = origPrompt;
    }
  });

  test('native prompt with falsy value does not call onRequest', () => {
    const origPrompt = globalThis.prompt;
    globalThis.prompt = () => null;
    try {
      let called = false;
      dispatchLinkAction({ modal: { type: 'prompt', key: 'name' } }, '/link', null, () => { called = true; });
      assert.equal(called, false);
    } finally {
      globalThis.prompt = origPrompt;
    }
  });

  test('native prompt uses modal.label fallback to modal.title', () => {
    const origPrompt = globalThis.prompt;
    let promptArgs;
    globalThis.prompt = (message, defaultValue) => { promptArgs = { message, defaultValue }; return null; };
    try {
      dispatchLinkAction({ modal: { type: 'prompt', key: 'x', title: 'Fallback Title' } }, '/link', null, () => {});
      assert.equal(promptArgs.message, 'Fallback Title');
      assert.equal(promptArgs.defaultValue, '');
    } finally {
      globalThis.prompt = origPrompt;
    }
  });

  test('native prompt uses modal.label when present', () => {
    const origPrompt = globalThis.prompt;
    let promptMessage;
    globalThis.prompt = (message) => { promptMessage = message; return null; };
    try {
      dispatchLinkAction({ modal: { type: 'prompt', key: 'x', label: 'Custom Label', title: 'Title' } }, '/link', null, () => {});
      assert.equal(promptMessage, 'Custom Label');
    } finally {
      globalThis.prompt = origPrompt;
    }
  });

  test('native confirm with user OK calls onRequest', () => {
    const origConfirm = globalThis.confirm;
    globalThis.confirm = () => true;
    try {
      let called = null;
      const apiData = { modal: { type: 'confirm', content: 'Are you sure?' } };
      dispatchLinkAction(apiData, '/link', null, (method, href) => { called = { method, href }; });
      assert.equal(called.method, 'GET');
      assert.equal(called.href, '/link');
    } finally {
      globalThis.confirm = origConfirm;
    }
  });

  test('native confirm with user cancel does not call onRequest', () => {
    const origConfirm = globalThis.confirm;
    globalThis.confirm = () => false;
    try {
      let called = false;
      dispatchLinkAction({ modal: { type: 'confirm', content: 'Sure?' } }, '/link', null, () => { called = true; });
      assert.equal(called, false);
    } finally {
      globalThis.confirm = origConfirm;
    }
  });

  test('native confirm falls back to title then default message', () => {
    const origConfirm = globalThis.confirm;
    let confirmMsg;
    globalThis.confirm = (msg) => { confirmMsg = msg; return false; };
    try {
      dispatchLinkAction({ modal: { type: 'confirm', title: 'Title fallback' } }, '/link', null, () => {});
      assert.equal(confirmMsg, 'Title fallback');

      globalThis.confirm = (msg) => { confirmMsg = msg; return false; };
      dispatchLinkAction({ modal: { type: 'confirm' } }, '/link', null, () => {});
      assert.equal(confirmMsg, 'Are you sure?');
    } finally {
      globalThis.confirm = origConfirm;
    }
  });

  test('Modals.create prompt with truthy value calls onRequest via callback', () => {
    const modalsLib = { create: (config) => { config.promptConfirmCallback('user-entered'); } };
    let called = null;
    const apiData = { modal: { type: 'prompt', key: 'email', title: 'Enter email', label: 'Email', value: '' } };
    dispatchLinkAction(apiData, '/link', modalsLib, (method, href, params) => { called = { method, href, params }; });
    assert.equal(called.method, 'GET');
    assert.deepEqual(called.params, { email: 'user-entered' });
  });

  test('Modals.create prompt with falsy value does not call onRequest', () => {
    const modalsLib = { create: (config) => { config.promptConfirmCallback(''); } };
    let called = false;
    dispatchLinkAction({ modal: { type: 'prompt', key: 'x' } }, '/link', modalsLib, () => { called = true; });
    assert.equal(called, false);
  });

  test('Modals.create prompt passes correct config', () => {
    let capturedConfig;
    const modalsLib = { create: (config) => { capturedConfig = config; config.promptConfirmCallback(null); } };
    const apiData = { modal: { type: 'prompt', key: 'k', title: 'T', label: 'L', value: 'V' } };
    dispatchLinkAction(apiData, '/link', modalsLib, () => {});
    assert.equal(capturedConfig.type, 'prompt');
    assert.equal(capturedConfig.title, 'T');
    assert.equal(capturedConfig.label, 'L');
    assert.equal(capturedConfig.value, 'V');
  });

  test('Modals.create prompt uses default label when missing', () => {
    let capturedConfig;
    const modalsLib = { create: (config) => { capturedConfig = config; config.promptConfirmCallback(null); } };
    dispatchLinkAction({ modal: { type: 'prompt', key: 'k' } }, '/link', modalsLib, () => {});
    assert.equal(capturedConfig.label, 'Label');
    assert.equal(capturedConfig.value, '');
  });

  test('Modals.create confirm calls onRequest via confirmCallback', () => {
    const modalsLib = { create: (config) => { config.confirmCallback(); } };
    let called = null;
    dispatchLinkAction({ modal: { type: 'confirm', title: 'Sure?' } }, '/link', modalsLib, (method, href) => { called = { method, href }; });
    assert.equal(called.method, 'GET');
    assert.equal(called.href, '/link');
  });

  test('Modals.create maps type "confirm" to "small-confirm"', () => {
    let capturedType;
    const modalsLib = { create: (config) => { capturedType = config.type; config.confirmCallback(); } };
    dispatchLinkAction({ modal: { type: 'confirm' } }, '/link', modalsLib, () => {});
    assert.equal(capturedType, 'small-confirm');
  });

  test('Modals.create preserves non-confirm modal types as-is', () => {
    let capturedType;
    const modalsLib = { create: (config) => { capturedType = config.type; config.confirmCallback(); } };
    dispatchLinkAction({ modal: { type: 'danger' } }, '/link', modalsLib, () => {});
    assert.equal(capturedType, 'danger');
  });

  test('Modals.create confirm passes full config with defaults', () => {
    let c;
    const modalsLib = { create: (config) => { c = config; config.confirmCallback(); } };
    dispatchLinkAction({ modal: { type: 'confirm', title: 'Delete?', content: 'This will delete the item.', button: 'Delete', buttonColor: 'danger' } }, '/link', modalsLib, () => {});
    assert.equal(c.title, 'Delete?');
    assert.equal(c.content, 'This will delete the item.');
    assert.equal(c.confirmButton, 'Delete');
    assert.equal(c.confirmButtonColor, 'danger');
  });

  test('Modals.create confirm applies default button and color', () => {
    let c;
    const modalsLib = { create: (config) => { c = config; config.confirmCallback(); } };
    dispatchLinkAction({ modal: { type: 'confirm', title: 'X' } }, '/link', modalsLib, () => {});
    assert.equal(c.confirmButton, 'Confirm');
    assert.equal(c.confirmButtonColor, 'primary');
    assert.equal(c.content, '');
  });

  test('modalsLib without create function falls back to native confirm', () => {
    const origConfirm = globalThis.confirm;
    globalThis.confirm = () => true;
    try {
      let called = false;
      dispatchLinkAction({ modal: { type: 'confirm', content: 'OK?' } }, '/link', {}, () => { called = true; });
      assert.equal(called, true);
    } finally {
      globalThis.confirm = origConfirm;
    }
  });
});

describe('createLinkLoadingState', () => {
  test('isInProgress is false initially', () => {
    const el = mockLinkElement();
    const state = createLinkLoadingState(el);
    assert.equal(state.isInProgress(), false);
  });

  test('set marks in progress and adds disabled + aria attributes', () => {
    const el = mockLinkElement();
    const state = createLinkLoadingState(el);
    state.set({ loading: null });
    assert.equal(state.isInProgress(), true);
    assert.equal(el.getAttribute('aria-busy'), 'true');
    assert.equal(el.getAttribute('aria-disabled'), 'true');
    assert.equal(el.classList.contains('disabled'), true);
  });

  test('reset restores original state and clears in-progress', () => {
    const el = mockLinkElement();
    const state = createLinkLoadingState(el);
    state.set({ loading: null });
    state.reset();
    assert.equal(state.isInProgress(), false);
    assert.equal(el.getAttribute('aria-busy'), null);
    assert.equal(el.getAttribute('aria-disabled'), null);
    assert.equal(el.classList.contains('disabled'), false);
  });

  test('reset is no-op when not in progress', () => {
    const el = mockLinkElement();
    const state = createLinkLoadingState(el);
    state.reset();
    assert.equal(state.isInProgress(), false);
  });

  test('set with loading.button replaces link children with spinner + text', () => {
    const el = mockLinkElement();
    const state = createLinkLoadingState(el);
    state.set({ loading: { button: 'Loading...' } });
    assert.ok(el._children);
    assert.equal(el._children.length, 2);
    assert.equal(el._children[0].className, 'spinner-border spinner-border-sm me-2');
    assert.equal(el._children[0].getAttribute('aria-hidden'), 'true');
    assert.equal(el._children[1].nodeType, 3);
    assert.equal(el._children[1].textContent, 'Loading...');
  });

  test('set restores innerHTML on reset after button change', () => {
    const el = mockLinkElement();
    const originalHtml = el.innerHTML;
    const state = createLinkLoadingState(el);
    state.set({ loading: { button: 'Working...' } });
    state.reset();
    assert.equal(el.innerHTML, originalHtml);
  });

  test('set preserves existing aria-busy value on reset', () => {
    const el = mockLinkElement();
    el._attrs['aria-busy'] = 'false';
    const state = createLinkLoadingState(el);
    state.set({ loading: null });
    state.reset();
    assert.equal(el.getAttribute('aria-busy'), 'false');
  });

  test('set preserves existing aria-disabled value on reset', () => {
    const el = mockLinkElement();
    el._attrs['aria-disabled'] = 'false';
    const state = createLinkLoadingState(el);
    state.set({ loading: null });
    state.reset();
    assert.equal(el.getAttribute('aria-disabled'), 'false');
  });

  test('set does not remove disabled class if link was originally disabled', () => {
    const el = mockLinkElement({ disabled: true });
    const state = createLinkLoadingState(el);
    state.set({ loading: null });
    state.reset();
    assert.equal(el.classList.contains('disabled'), true);
  });

  test('preventNavigation adds and removes beforeunload listener', () => {
    const origWindow = globalThis.window;
    const eventTarget = mockEventTarget();
    globalThis.window = Object.assign({}, origWindow, eventTarget);

    try {
      const el = mockLinkElement();
      const state = createLinkLoadingState(el);
      state.set({ preventNavigation: true });
      assert.equal(eventTarget._has('beforeunload'), true);
      state.reset();
      assert.equal(eventTarget._has('beforeunload'), false);
    } finally {
      globalThis.window = origWindow;
    }
  });

  test('preventNavigation false does not add beforeunload listener', () => {
    const origWindow = globalThis.window;
    const eventTarget = mockEventTarget();
    globalThis.window = Object.assign({}, origWindow, eventTarget);

    try {
      const el = mockLinkElement();
      const state = createLinkLoadingState(el);
      state.set({ loading: null });
      assert.equal(eventTarget._has('beforeunload'), false);
    } finally {
      globalThis.window = origWindow;
    }
  });
});
