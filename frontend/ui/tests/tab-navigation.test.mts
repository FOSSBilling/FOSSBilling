import assert from 'node:assert/strict';
import { test } from 'node:test';
import { initTabNavigation, tabTarget } from '../tab-navigation.ts';

class Trigger {
  attrs: Record<string, string>;
  disabled = false;
  nav: any = null;
  constructor(target: string, attribute = 'href') {this.attrs = {[attribute]: target};}
  getAttribute(name: string) {return this.attrs[name] ?? null;}
  matches(selector: string) {return selector.startsWith(':disabled') ? this.disabled : true;}
  closest(selector: string) {return selector.includes('tablist') ? this.nav : selector === '[data-tab-jump]' ? null : this;}
  getBoundingClientRect() {return {left: 120, right: 170};}
}
function fixture(run: (f: any) => void, options: any = {}) {
  const keys = ['document', 'window', 'location', 'history', 'Element', 'tabler'];
  const old = new Map(keys.map(key => [key, Object.getOwnPropertyDescriptor(globalThis, key)]));
  const listeners = new Map<string, Function>();
  const windowListeners = new Map<string, Function>();
  const one = new Trigger('#one'), two = new Trigger('#two', 'data-bs-target');
  const triggers = [one, two];
  const panes = new Map(['one', 'two'].map(id => [id, {id, classList: {contains: () => id === 'one'}}]));
  const shown: Trigger[] = [], replaced: URL[] = [];
  const state = {extension: 'retained'};
  const library = {getOrCreateInstance(trigger: Trigger) {return {show() {shown.push(trigger);}};}};
  const document = {querySelectorAll: () => triggers, getElementById: (id: string) => panes.get(id), addEventListener: (name: string, cb: Function) => listeners.set(name, cb)};
  const window = {addEventListener: (name: string, cb: Function, capture?: boolean) => {if (name === 'click') assert.equal(capture, true); windowListeners.set(name, cb);}};
  Object.assign(globalThis, {document, window, tabler: {Tab: library}, Element: Trigger, location: {hash: '#two', href: 'https://example.test/account?search=demo&page=3#two'}, history: {state, replaceState(next: unknown, _: string, url: URL) {assert.equal(next, state);replaced.push(url);}}});
  const emit = (trigger: Trigger) => listeners.get('shown.bs.tab')!({target: trigger});
  const click = (trigger: Trigger, extra = {}) => windowListeners.get('click')!({target: trigger, button: 0, preventDefault() {}, ...extra});
  try {initTabNavigation(options);run({one, two, shown, replaced, panes, triggers, emit, click, listeners, windowListeners, library});}
  finally {for (const key of keys) {const descriptor = old.get(key);if (descriptor) Object.defineProperty(globalThis, key, descriptor);else delete (globalThis as any)[key];}}
}

test('initial and subsequent hash links use Tabler without rewriting the initial query', () => fixture(f => {
  assert.deepEqual(f.shown, [f.two]);
  assert.equal(f.replaced.length, 0);
  assert.equal(f.windowListeners.has('click'), false);
  globalThis.location.hash = '#one';
  f.windowListeners.get('hashchange')();
  assert.deepEqual(f.shown, [f.two, f.one]);
  initTabNavigation();
  assert.equal(f.shown.length, 2);
}));

test('shown tabs retain filters and extension history state and reveal a clipped tab horizontally', () => fixture(f => {
  f.two.nav = {scrollWidth: 200, clientWidth: 100, scrollLeft: 0, getBoundingClientRect: () => ({left: 0, right: 100})};
  f.emit(f.two);
  assert.equal(f.replaced[0].href, 'https://example.test/account?search=demo&page=3#two');
  assert.equal(f.two.nav.scrollLeft, 70);
}));

test('disabled and missing panes do not activate or rewrite the URL', () => fixture(f => {
  f.one.disabled = true;
  globalThis.location.hash = '#one';f.windowListeners.get('hashchange')();
  f.emit(f.one);
  f.two.attrs['data-bs-target'] = '#missing';f.emit(f.two);
  assert.equal(f.shown.length, 1);
  assert.equal(f.replaced.length, 0);
}));

test('admin adapter callbacks apply only to already active plain clicks and can prune URL keys', () => {
  const clicked: string[] = [];
  fixture(f => {
    f.click(f.two);f.click(f.one);f.click(f.one, {ctrlKey: true});f.click(f.one, {button: 1});
    assert.deepEqual(clicked, ['one']);
    f.emit(f.two);assert.equal(f.replaced[0].search, '?search=demo');
  }, {onActiveClick: (_: unknown, __: unknown, pane: any) => clicked.push(pane.id), updateUrl: (url: URL) => url.searchParams.delete('page')});
});

test('extension tabs added later and Tabler-prefixed list triggers use the shared lifecycle', () => fixture(f => {
  const extension = new Trigger('#extension:one', 'data-tblr-target');
  f.panes.set('extension:one', {id: 'extension:one'});f.triggers.push(extension);
  globalThis.location.hash = '#extension:one';f.windowListeners.get('hashchange')();f.emit(extension);
  assert.equal(f.shown.at(-1), extension);
  assert.equal(f.replaced[0].hash, '#extension:one');
}));

test('tab targets accept local IDs without interpolating them into CSS selectors', () => {
  assert.equal(tabTarget(new Trigger('#odd:id') as any), 'odd:id');
  for (const target of ['#', 'https://example.test/#one', '.pane', '']) assert.equal(tabTarget(new Trigger(target) as any), null);
});

test('admin jump links use Tabler while modified clicks keep their native behaviour', () => fixture(f => {
  const jump = new Trigger('#two');
  jump.closest = ((selector: string) => selector === '[data-tab-jump]' ? jump : null) as any;
  let prevented = 0;
  f.click(jump, {preventDefault() {prevented++;}});
  assert.equal(f.shown.at(-1), f.two);
  assert.equal(prevented, 1);
  f.click(jump, {metaKey: true, preventDefault() {assert.fail('modified click');}});
  assert.equal(f.shown.length, 2);
}, {jumpLinks: true}));
