import assert from 'node:assert/strict';
import { test } from 'node:test';
import { showToast } from '../tabler-toast.ts';

class Element extends EventTarget {
  className = '';
  textContent = '';
  type = '';
  isConnected = true;
  attributes = new Map<string, string>();
  children: Element[] = [];
  parent?: Element;
  focused = false;
  append(...children: Element[]) { children.forEach(child => {child.parent = this; this.children.push(child);}); }
  setAttribute(name: string, value: string) { this.attributes.set(name, value); }
  contains(other: Element) { return other === this || this.children.some(child => child.contains(other)); }
  getClientRects() { return this.isConnected ? [{}] : []; }
  focus() { this.focused = true; }
  remove() {
    this.isConnected = false;
    if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this);
  }
}

function fixture(run: (state: ReturnType<typeof createState>) => void) {
  const oldDocument = (globalThis as any).document;
  const oldTabler = (globalThis as any).tabler;
  const state = createState();
  (globalThis as any).document = state.document;
  (globalThis as any).tabler = state.tabler;
  try { run(state); } finally {
    (globalThis as any).document = oldDocument;
    (globalThis as any).tabler = oldTabler;
  }
}

function createState() {
  const origin = new Element();
  const container = new Element();
  const instances = new Map<Element, {show: () => void; dispose: () => void}>();
  const options: unknown[] = [];
  let shown = 0;
  let disposed = 0;
  const document = {activeElement: origin, querySelector: (): Element | null => container, createElement: () => new Element()};
  const tabler = {Toast: {
    getOrCreateInstance(element: Element, config: unknown) {
      options.push(config);
      const instance = {show() {shown++;}, dispose() {disposed++; instances.delete(element);}};
      instances.set(element, instance);
      return instance;
    },
    getInstance: (element: Element) => instances.get(element),
  }};
  return {origin, container, document, tabler, options, instances, shown: () => shown, disposed: () => disposed};
}

test('client toast retains translated labels, persistent errors and external announcements', () => fixture(f => {
  const announced: string[][] = [];
  showToast('<script>bad()</script>', 'error', {
    title: 'Translated message', closeLabel: 'Translated close', autohide: false,
    restoreFocus: true, announce: (message, type) => announced.push([message, type]),
  });
  const toast = f.container.children[0];
  const [header, body] = toast.children;
  assert.equal(header.children[1].textContent, 'Translated message');
  assert.equal(header.children[2].attributes.get('aria-label'), 'Translated close');
  assert.equal(body.textContent, '<script>bad()</script>');
  assert.equal(body.children.length, 0);
  assert.equal(toast.attributes.has('role'), false);
  assert.deepEqual(f.options, [{autohide: false}]);
  assert.deepEqual(announced, [['<script>bad()</script>', 'error']]);
  assert.equal(f.shown(), 1);
  f.document.activeElement = header.children[2];
  toast.dispatchEvent(new Event('hidden.bs.toast'));
  assert.equal(f.origin.focused, true);
  assert.equal(f.disposed(), 1);
  assert.equal(f.container.children.length, 0);
  assert.equal(f.instances.size, 0);
}));

test('admin toast retains its inline live region and default Tabler dismissal', () => fixture(f => {
  showToast('Saved', 'success', {title: 'Success', closeLabel: 'Close', assertive: true});
  const toast = f.container.children[0];
  assert.equal(toast.attributes.get('role'), 'alert');
  assert.equal(toast.attributes.get('aria-live'), 'assertive');
  assert.equal(toast.attributes.get('aria-atomic'), 'true');
  assert.match(toast.children[0].children[0].className, /bg-success/);
  assert.deepEqual(f.options, [{autohide: true}]);
  toast.dispatchEvent(new Event('hidden.bs.toast'));
  assert.equal(f.origin.focused, false);
  assert.equal(f.disposed(), 1);
}));

test('unknown message types stay neutral and dismissal does not steal external focus', () => fixture(f => {
  showToast('An update', 'constructor', {title: 'Info', closeLabel: 'Close', restoreFocus: true});
  const toast = f.container.children[0];
  assert.match(toast.children[0].children[0].className, /bg-primary/);
  toast.dispatchEvent(new Event('hidden.bs.toast'));
  assert.equal(f.origin.focused, false);
}));

test('layout overrides without a toast container leave the rest of the page running', () => fixture(f => {
  f.document.querySelector = () => null;
  const oldWarn = console.warn;
  const warnings: unknown[] = [];
  console.warn = message => warnings.push(message);
  try {
    showToast('Saved', 'info', {title: 'Info', closeLabel: 'Close'});
    assert.equal(f.shown(), 0);
    assert.equal(warnings.length, 1);
  } finally { console.warn = oldWarn; }
}));
