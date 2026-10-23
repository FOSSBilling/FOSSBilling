import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createFormErrors } from '../form-errors.ts';
import * as clientErrors from '../../../src/themes/default/client/assets/js/ui/form-errors.ts';

class Node extends EventTarget {
  id = '';
  className = '';
  dataset: Record<string, string> = {};
  textContent = '';
  tabIndex = 0;
  parent: Node | null = null;
  children: Node[] = [];
  attrs = new Map<string, string>();
  classes = new Set<string>();
  focused = false;
  visible = true;
  nextElementSibling = null;
  labels = [{textContent: 'Email *'}];
  validationMessage = 'Enter a valid email address.';
  validity = {valid: false, customError: false};
  willValidate = true;
  elements: Node[] = [];
  classList = {contains: (name: string) => this.classes.has(name), add: (name: string) => this.classes.add(name), remove: (name: string) => this.classes.delete(name)};
  get childElementCount() { return this.children.length; }
  getAttribute(name: string) { return this.attrs.get(name) ?? null; }
  setAttribute(name: string, value: string) { this.attrs.set(name, value); }
  removeAttribute(name: string) { this.attrs.delete(name); }
  append(...nodes: Node[]) { for (const node of nodes) {node.parent = this; this.children.push(node);} }
  prepend(node: Node) {node.parent = this; this.children.unshift(node);}
  after(node: Node) {this.parent?.append(node);}
  remove() {if (this.parent) this.parent.children = this.parent.children.filter(node => node !== this);}
  closest() {return null;}
  matches() {return true;}
  querySelectorAll() {return [];}
  focus() {this.focused = true;}
  scrollIntoView() {}
  getClientRects() {return this.visible ? [{}] : [];}
}

function fixture(run: (state: ReturnType<typeof setup>) => void) {
  const previousDocument = globalThis.document;
  const previousRuntime = (globalThis as any).FOSSBilling;
  const state = setup();
  globalThis.document = state.document as any;
  (globalThis as any).FOSSBilling = state.runtime as any;
  try {run(state);} finally {globalThis.document = previousDocument; (globalThis as any).FOSSBilling = previousRuntime;}
}

function setup() {
  const listeners = new Map<string, (event: any) => void>();
  const document = {
    querySelector: () => ({dataset: {formError: 'Translated Problem', required: 'Translated Required', clientFormError: 'Client Problem', clientRequired: 'Client Required'}}),
    querySelectorAll: () => [],
    createElement: () => new Node(),
    addEventListener: (name: string, listener: any) => listeners.set(name, listener),
  };
  const notified: string[] = [];
  const runtime = {ui: {} as Record<string, any>, message: (text: string) => notified.push(text)};
  const form = new Node();
  const field = new Node();
  field.id = 'email';
  field.setAttribute('aria-describedby', 'existing-hint');
  form.append(field);
  form.elements = [field];
  const presenter = createFormErrors();
  return {document, listeners, runtime, form, field, presenter, notified};
}

test('shared form errors render translated escaped summaries with recoverable field links', () => fixture(f => {
  f.presenter.showFieldError(f.field as any);
  f.presenter.showFormError(f.form as any, '<img src=x onerror=bad()>');
  const summary = f.form.children[0];
  assert.equal(summary.getAttribute('data-fb-form-error'), '');
  assert.equal(summary.getAttribute('role'), 'region');
  assert.equal(summary.children[0].textContent, 'Translated Problem');
  assert.equal(summary.children[1].textContent, '<img src=x onerror=bad()>');
  assert.equal(summary.children[1].children.length, 0);
  assert.equal(summary.focused, true);
  const link = summary.children[2].children[0].children[0];
  link.dispatchEvent(new Event('click', {cancelable: true}));
  assert.equal(f.field.focused, true);
  f.field.validity.valid = true;
  f.presenter.clearFormError(f.form as any);
  assert.equal(f.form.children.length, 1);
  assert.equal(f.field.getAttribute('aria-describedby'), 'existing-hint');
}));

test('adapters can preserve legacy IDs selectors and translated fallback messages', () => fixture(f => {
  const presenter = clientErrors;
  f.field.validationMessage = '';
  presenter.showFieldError(f.field as any);
  assert.match(f.form.children[1].id, /^client-field-error-/);
  assert.equal(f.form.children[1].textContent, 'Client Required');
  presenter.showFormError(f.form as any, 'Retry');
  const summary = f.form.children[0];
  assert.equal(summary.dataset.clientFormError, '');
  assert.equal(summary.getAttribute('data-fb-form-error'), '');
  assert.match(summary.children[0].id, /^client-form-error-/);
  assert.equal(summary.children[0].textContent, 'Client Problem');
}));

test('shared initialization preserves extension hooks and binds listeners only once', () => fixture(f => {
  const hooks = {formError() {}, clearFormError() {}, fieldError() {}, clearFieldError() {}};
  Object.assign(f.runtime.ui, hooks);
  f.presenter.initFormErrors();
  const firstListeners = [...f.listeners.values()];
  f.presenter.initFormErrors();
  assert.deepEqual([...f.listeners.values()], firstListeners);
  for (const [name, callback] of Object.entries(hooks)) assert.equal(f.runtime.ui[name], callback);
}));

test('late extension fields get feedback after invalid events and recover while editing', () => fixture(f => {
  f.presenter.initFormErrors();
  f.listeners.get('input')!({target: f.field});
  assert.equal(f.form.children.length, 1);
  f.listeners.get('invalid')!({target: f.field});
  assert.equal(f.form.children.length, 2);
  f.field.validationMessage = 'Try a different address.';
  f.listeners.get('input')!({target: f.field});
  assert.equal(f.form.children[1].textContent, 'Try a different address.');
  f.field.validity.valid = true;
  f.listeners.get('change')!({target: f.field});
  assert.equal(f.form.children.length, 1);
}));

test('Tom Select errors describe and focus the visible widget and preserve its prior state', () => fixture(f => {
  const target = new Node();
  target.setAttribute('aria-describedby', 'widget-hint');
  target.setAttribute('aria-invalid', 'grammar');
  const wrapper = new Node();
  f.form.append(wrapper);
  const select = {focus: () => target.focus(), focus_node: target, wrapper};
  Object.assign(f.field, {tomselect: select, form: f.form});
  f.presenter.initFormErrors();
  let prevented = false;
  f.listeners.get('invalid')!({target: f.field, preventDefault() {prevented = true;}});
  assert.equal(prevented, true);
  assert.equal(target.focused, true);
  assert.equal(target.getAttribute('aria-invalid'), 'true');
  assert.equal(wrapper.classes.has('is-invalid'), true);
  f.presenter.showFormError(f.form as any, 'Review the selection.');
  const link = f.form.children[0].children[2].children[0].children[0];
  target.focused = false;
  link.dispatchEvent(new Event('click', {cancelable: true}));
  assert.equal(target.focused, true);
  f.presenter.clearFieldError(f.field as any);
  assert.equal(target.getAttribute('aria-invalid'), 'grammar');
  assert.equal(target.getAttribute('aria-describedby'), 'widget-hint');
  assert.equal(wrapper.classes.has('is-invalid'), false);
  wrapper.classes.add('is-invalid');
  f.presenter.showFieldError(f.field as any);
  f.presenter.clearFieldError(f.field as any);
  assert.equal(wrapper.classes.has('is-invalid'), true);
}));

test('a later enhanced select does not steal focus from the first invalid native field', () => fixture(f => {
  const target = new Node();
  const earlier = new Node();
  Object.assign(f.field, {tomselect: {focus: () => target.focus(), focus_node: target, wrapper: new Node()}, form: f.form});
  f.form.elements.unshift(earlier);
  f.presenter.initFormErrors();
  f.listeners.get('invalid')!({target: f.field, preventDefault() {}});
  assert.equal(target.focused, false);
}));

test('CKEditor feedback updates its accessibility model and focuses the editable surface', () => fixture(f => {
  const target = new Node();
  const calls: string[] = [];
  Object.assign(f.field, {editor: {
    focus() {target.focus();},
    getAccessibleElement: () => target,
    setAccessibilityAttribute(name: string, value: string | null) {
      calls.push(name);
      if (value === null) target.removeAttribute(name);
      else target.setAttribute(name, value);
    },
  }});
  f.presenter.showFieldError(f.field as any, 'Please enter a reply.');
  f.presenter.showFormError(f.form as any, 'Review the reply.');
  f.form.children[0].children[2].children[0].children[0].dispatchEvent(new Event('click', {cancelable: true}));
  assert.equal(target.focused, true);
  assert.equal(target.getAttribute('aria-invalid'), 'true');
  f.presenter.clearFieldError(f.field as any);
  assert.equal(target.getAttribute('aria-invalid'), null);
  assert.equal(target.getAttribute('aria-describedby'), null);
  assert.equal(calls.length, 4);
}));

test('errors from hidden forms remain available without moving focus into a closed dialog', () => fixture(f => {
  f.form.visible = false;
  f.presenter.showFormError(f.form as any, 'Please try again.');
  assert.equal(f.form.children[0].focused, false);
  assert.deepEqual(f.notified, ['Please try again.']);
}));


test('invalid fields in inactive tabs are revealed through Tabler before focusing', () => fixture(f => {
  const oldTabler = (globalThis as any).tabler;
  const pane = new Node(); pane.id = 'account-tab';
  const trigger = new Node(); trigger.setAttribute('data-bs-target', '#account-tab');
  trigger.matches = () => false;
  let active = false;
  f.field.visible = false;
  Object.assign(f.field, {form: f.form, closest: (selector: string) => selector.startsWith('.tab-pane') && !active ? pane : null});
  f.document.querySelectorAll = ((selector: string) => selector.includes('data-bs-toggle') ? [trigger] : []) as any;
  (globalThis as any).tabler = {Tab: {getOrCreateInstance: () => ({show() {
    active = true;
    f.field.visible = true;
    trigger.dispatchEvent(new Event('shown.bs.tab'));
  }})}};
  try {
    f.presenter.initFormErrors();
    let prevented = false;
    f.listeners.get('invalid')!({target: f.field, preventDefault() {prevented = true;}});
    assert.equal(prevented, true);
    assert.equal(active, true);
    assert.equal(f.field.focused, true);
  } finally {(globalThis as any).tabler = oldTabler;}
}));


test('fields without IDs receive stable summary targets without changing submission names', () => fixture(f => {
  f.field.id = '';
  const label = {tagName: 'LABEL', htmlFor: ''};
  Object.assign(f.field, {name: 'email', previousElementSibling: label});
  f.presenter.showFieldError(f.field as any);
  const id = f.field.id;
  assert.match(id, /^fb-field-/);
  assert.equal(label.htmlFor, id);
  f.presenter.showFormError(f.form as any, 'Review your details.');
  assert.equal((f.form.children[0].children[2].children[0].children[0] as any).href, '#' + id);
  f.presenter.clearFieldError(f.field as any);
  f.presenter.showFieldError(f.field as any);
  assert.equal(f.field.id, id);
  assert.equal((f.field as any).name, 'email');
}));


test('errors in modal forms stay inside the scrollable body and preserve the dialog header and actions', () => fixture(f => {
  const body = new Node();
  Object.assign(f.form, {closest: () => new Node(), querySelector: () => body});
  f.presenter.showFormError(f.form as any, 'Please try again.');
  assert.equal(f.form.children.length, 1);
  assert.equal(body.children[0].getAttribute('data-fb-form-error'), '');
  assert.equal(body.children[0].focused, true);
  f.presenter.clearFormError(f.form as any);
  assert.equal(body.children.length, 0);
}));

test('clearing feedback preserves an extension supplied invalid state', () => fixture(f => {
  const {field, presenter} = f;
  field.setAttribute('aria-invalid', 'grammar'); field.classes.add('is-invalid');
  presenter.showFieldError(field as any); presenter.clearFieldError(field as any);
  assert.equal(field.getAttribute('aria-invalid'), 'grammar');
  assert.equal(field.classes.has('is-invalid'), true);
}));

test('input-group feedback stays outside the prefix and suffix layout', () => fixture(f => {
  const {form, field, presenter} = f;
  const group = new Node(); form.children = []; form.append(group); group.append(field);
  field.closest = () => group;
  presenter.showFieldError(field as any);
  assert.equal(group.children.length, 1);
  assert.equal(form.children[1].textContent, field.validationMessage);
  presenter.clearFieldError(field as any);
  assert.equal(form.children.length, 1);
}));

test('native radio invalid events show one group error and clear it when corrected', () => fixture(f => {
  const {form, field, presenter, listeners} = f;
  const second = new Node(); second.id = 'choice-two'; form.append(second); form.elements.push(second);
  Object.assign(field, {type: 'radio', name: 'choice', form, disabled: false});
  Object.assign(second, {type: 'radio', name: 'choice', form, disabled: false});
  presenter.initFormErrors();
  listeners.get('invalid')!({target: field});
  listeners.get('invalid')!({target: second});
  assert.equal(form.children.filter(node => node.className === 'invalid-feedback d-block text-break').length, 1);
  field.validity.valid = true; second.validity.valid = true;
  listeners.get('change')!({target: second});
  assert.equal(field.getAttribute('aria-invalid'), null);
  assert.equal(form.children.length, 2);
}));
