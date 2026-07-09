import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import { createLinkLoadingState, dispatchLinkAction } from '../link-helpers.mjs';

globalThis.window = globalThis;

const createClassList = (classes = []) => ({
  classes: new Set(classes),
  add(value) { this.classes.add(value); },
  remove(value) { this.classes.delete(value); },
  contains(value) { return this.classes.has(value); },
});

const createElement = () => ({
  className: '',
  attributes: {},
  classList: createClassList(),
  getAttribute(name) { return this.attributes[name] ?? null; },
  setAttribute(name, value) { this.attributes[name] = String(value); },
});

globalThis.document = {
  createElement,
  createTextNode: (text) => ({ textContent: text }),
  querySelector: () => null,
};

const createLink = ({ disabled = false, parent = null } = {}) => ({
  innerHTML: '<span>Original</span>',
  attributes: {},
  classList: createClassList(disabled ? ['disabled'] : []),
  parentElement: parent,
  getAttribute(name) { return this.attributes[name] ?? null; },
  setAttribute(name, value) { this.attributes[name] = String(value); },
  removeAttribute(name) { delete this.attributes[name]; },
  replaceChildren(...children) { this.children = children; },
  closest() { return parent; },
});

describe('dispatchLinkAction', () => {
  test('dispatches links without a modal directly', () => {
    let request;
    dispatchLinkAction({}, '/endpoint', null, (...args) => { request = args; });
    assert.deepEqual(request, ['GET', '/endpoint']);
  });

  test('uses native confirm when the modal library is unavailable', () => {
    let request;
    window.confirm = (message) => {
      assert.equal(message, 'Continue?');
      return true;
    };

    dispatchLinkAction(
      { modal: { type: 'confirm', content: 'Continue?' } },
      '/endpoint',
      null,
      (...args) => { request = args; },
    );
    assert.deepEqual(request, ['GET', '/endpoint']);

    window.confirm = () => false;
    request = undefined;
    dispatchLinkAction(
      { modal: { type: 'danger' } },
      '/endpoint',
      null,
      (...args) => { request = args; },
    );
    assert.equal(request, undefined);
  });

  test('uses native prompt and maps the answer to the configured key', () => {
    window.prompt = (label, value) => {
      assert.equal(label, 'Reason');
      assert.equal(value, 'Default');
      return 'Because';
    };

    let request;
    dispatchLinkAction(
      { modal: { type: 'prompt', key: 'reason', label: 'Reason', value: 'Default' } },
      '/endpoint',
      null,
      (...args) => { request = args; },
    );
    assert.deepEqual(request, ['GET', '/endpoint', { reason: 'Because' }]);
  });

  test('configures library confirm and prompt callbacks', () => {
    const created = [];
    const modals = { create: (config) => created.push(config) };
    const requests = [];

    dispatchLinkAction(
      { modal: { type: 'confirm', title: 'Delete', button: 'Delete', buttonColor: 'danger' } },
      '/delete',
      modals,
      (...args) => requests.push(args),
    );
    assert.equal(created[0].type, 'small-confirm');
    assert.equal(created[0].confirmButton, 'Delete');
    created[0].confirmCallback();

    dispatchLinkAction(
      { modal: { type: 'prompt', key: 'name', title: 'Name' } },
      '/rename',
      modals,
      (...args) => requests.push(args),
    );
    assert.equal(created[1].label, 'Label');
    created[1].promptConfirmCallback('New name');

    assert.deepEqual(requests, [
      ['GET', '/delete'],
      ['GET', '/rename', { name: 'New name' }],
    ]);
  });
});

describe('createLinkLoadingState', () => {
  test('sets and restores link state', () => {
    const link = createLink();
    const state = createLinkLoadingState(link);

    assert.equal(state.isInProgress(), false);
    state.set({ loading: { button: 'Saving' } });
    assert.equal(state.isInProgress(), true);
    assert.equal(link.classList.contains('disabled'), true);
    assert.equal(link.getAttribute('aria-busy'), 'true');
    assert.equal(link.children[1].textContent, 'Saving');

    state.reset();
    assert.equal(state.isInProgress(), false);
    assert.equal(link.classList.contains('disabled'), false);
    assert.equal(link.getAttribute('aria-busy'), null);
    assert.equal(link.innerHTML, '<span>Original</span>');
  });

  test('preserves pre-existing accessibility and disabled state', () => {
    const link = createLink({ disabled: true });
    link.setAttribute('aria-busy', 'pending');
    link.setAttribute('aria-disabled', 'mixed');
    const state = createLinkLoadingState(link);

    state.set({});
    state.reset();

    assert.equal(link.classList.contains('disabled'), true);
    assert.equal(link.getAttribute('aria-busy'), 'pending');
    assert.equal(link.getAttribute('aria-disabled'), 'mixed');
  });

  test('adds and removes loading messages and navigation guards', () => {
    const parent = {
      children: [],
      appendChild(child) { this.children.push(child); },
    };
    const removed = [];
    const listeners = new Map();
    window.addEventListener = (name, listener) => listeners.set(name, listener);
    window.removeEventListener = (name, listener) => {
      if (listeners.get(name) === listener) {
        listeners.delete(name);
      }
    };

    const originalCreateElement = document.createElement;
    document.createElement = () => {
      const element = originalCreateElement();
      element.remove = () => removed.push(element);
      return element;
    };

    const state = createLinkLoadingState(createLink({ parent }));
    state.set({
      loading: { message: 'Still working', alertClass: 'notice' },
      preventNavigation: true,
    });

    assert.equal(parent.children[0].textContent, 'Still working');
    assert.equal(parent.children[0].className, 'notice');
    assert.equal(listeners.has('beforeunload'), true);

    state.reset();
    assert.equal(removed.length, 1);
    assert.equal(listeners.has('beforeunload'), false);
  });
});
