import assert from 'node:assert/strict';
import { test } from 'node:test';
import initFormErrors, { clearFieldError, showFieldError, clearFormError, showFormError } from '../ui/form-errors.ts';

class Node extends EventTarget {
  id = '';
  className = '';
  dataset = {};
  textContent = '';
  tabIndex = 0;
  parent: Node | null = null;
  children: Node[] = [];
  attrs = new Map<string, string>();
  classes = new Set<string>();
  focused = false;
  scrolled = false;
  visible = true;
  nextElementSibling = null;
  labels = [{textContent: 'Email *'}];
  validationMessage = 'Enter an email address.';
  validity = {valid: false};
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
  focus() {this.focused = true;}
  scrollIntoView() {this.scrolled = true;}
  getClientRects() {return this.visible ? [{}] : [];}
}

(globalThis as any).document = {querySelector: () => ({dataset: {clientFormError: 'There Was a Problem'}}), createElement: () => new Node()};

function fixture() {
  const form = new Node();
  const field = new Node(); field.id = 'email';
  form.append(field); form.elements = [field];
  field.setAttribute('aria-describedby', 'email-help');
  return {form, field};
}

test('field feedback preserves hints, updates once and cleans up only its own state', () => {
  const {form, field} = fixture();
  showFieldError(field as any);
  const feedback = form.children[1];
  assert.equal(field.getAttribute('aria-describedby'), `email-help ${feedback.id}`);
  assert.equal(field.getAttribute('aria-invalid'), 'true');
  assert.equal(feedback.textContent, field.validationMessage);
  showFieldError(field as any, 'Use a valid address.');
  assert.equal(form.children.length, 2);
  assert.equal(feedback.textContent, 'Use a valid address.');
  clearFieldError(field as any);
  assert.equal(field.getAttribute('aria-describedby'), 'email-help');
  assert.equal(field.getAttribute('aria-invalid'), null);
  assert.equal(field.classes.has('is-invalid'), false);
  assert.equal(form.children.length, 1);
});

test('clearing client feedback preserves an extension supplied invalid state', () => {
  const {field} = fixture();
  field.setAttribute('aria-invalid', 'grammar'); field.classes.add('is-invalid');
  showFieldError(field as any); clearFieldError(field as any);
  assert.equal(field.getAttribute('aria-invalid'), 'grammar');
  assert.equal(field.classes.has('is-invalid'), true);
});

test('form errors focus a persistent labelled summary with links to invalid controls', () => {
  const {form, field} = fixture();
  showFieldError(field as any);
  showFormError(form as any, 'Could not save <details>.');
  const summary = form.children[0];
  assert.equal(summary.focused, true);
  assert.equal(summary.scrolled, true);
  assert.equal(summary.getAttribute('aria-labelledby'), summary.children[0].id);
  assert.equal(summary.children[1].textContent, 'Could not save <details>.');
  const link = summary.children[2].children[0].children[0];
  assert.equal(link.textContent, 'Email');
  link.dispatchEvent(new Event('click', {cancelable: true}));
  assert.equal(field.focused, true);
  assert.equal(field.scrolled, true);
  clearFormError(form as any);
  assert.equal(form.children.includes(summary), false);
});

test('errors on a dismissed or hidden form do not move focus into hidden content', () => {
  const {form} = fixture(); form.visible = false;
  let message = '';
  (globalThis as any).FOSSBilling = {message: (text: string) => {message = text;}};
  showFormError(form as any, 'Please try again.');
  assert.equal(form.children[0].focused, false);
  assert.equal(message, 'Please try again.');
});

test('a new request clears stale feedback after values were filled without input events', () => {
  const {form, field} = fixture();
  showFieldError(field as any);
  field.validity.valid = true;
  clearFormError(form as any);
  assert.equal(field.getAttribute('aria-invalid'), null);
  assert.equal(field.getAttribute('aria-describedby'), 'email-help');
  assert.equal(form.children.length, 1);
});

test('input-group feedback stays outside the prefix and suffix layout', () => {
  const {form, field} = fixture();
  const group = new Node(); form.children = []; form.append(group); group.append(field);
  field.closest = () => group;
  showFieldError(field as any);
  assert.equal(group.children.length, 1);
  assert.equal(form.children[1].textContent, field.validationMessage);
  clearFieldError(field as any);
  assert.equal(form.children.length, 1);
});

test('inline feedback follows the current validity after an error and disappears when corrected', () => {
  const listeners = new Map<string, (event: any) => void>();
  (globalThis as any).document.addEventListener = (name: string, listener: any) => listeners.set(name, listener);
  (globalThis as any).document.querySelectorAll = () => [];
  (globalThis as any).FOSSBilling = {ui: {}};
  initFormErrors();
  const {form, field} = fixture();
  (field as any).matches = () => true;
  field.validationMessage = 'This field is required.';
  listeners.get('input')!({target: field});
  assert.equal(form.children.length, 1);
  listeners.get('invalid')!({target: field});
  assert.equal(form.children[1].textContent, 'This field is required.');
  field.validationMessage = 'Enter a valid email address.';
  listeners.get('input')!({target: field});
  assert.equal(form.children[1].textContent, 'Enter a valid email address.');
  field.validity.valid = true;
  listeners.get('change')!({target: field});
  assert.equal(form.children.length, 1);
  assert.equal(field.getAttribute('aria-invalid'), null);
  // Native validation fires for every required radio; present one group error.
  const second = new Node(); second.id = 'choice-two'; form.append(second);
  form.elements.push(second);
  Object.assign(field, {type: 'radio', name: 'choice', form, disabled: false});
  Object.assign(second, {type: 'radio', name: 'choice', form, disabled: false, matches: () => true});
  field.validity.valid = false; second.validity.valid = false;
  listeners.get('invalid')!({target: field});
  listeners.get('invalid')!({target: second});
  assert.equal(form.children.filter(node => node.className === 'invalid-feedback d-block text-break').length, 1);
  field.validity.valid = true; second.validity.valid = true;
  listeners.get('change')!({target: second});
  assert.equal(field.getAttribute('aria-invalid'), null);
  assert.equal(form.children.length, 2);
});
