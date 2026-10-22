import assert from 'node:assert/strict';
import { test } from 'node:test';
import initFloatingHelp from '../floating-help.ts';

function fixture(run: (f: any) => void) {
  const oldDocument = globalThis.document, oldWindow = globalThis.window;
  const events = new Map<string, Function>(), windowEvents = new Map<string, Function>(), nodes = new Map<string, any>();
  let hides = 0, connected = true, available = true;
  const tipChild = {}, outside = {};
  const tip = {contains: (el: unknown) => el === tipChild};nodes.set('tip', tip);
  const attrs = new Map([['aria-describedby', 'tip']]);
  const trigger = {getAttribute: (name: string) => attrs.get(name) ?? null, setAttribute: (name: string, value: string) => attrs.set(name, value),
    hasAttribute: (name: string) => attrs.has(name), contains: (el: unknown) => el === trigger,
    get isConnected() {return connected;}, getClientRects: () => [1], focus: () => {}, parentElement: {querySelector: () => null}};
  const doc = {activeElement: outside, getElementById: (id: string) => nodes.get(id), addEventListener: (name: string, fn: Function) => events.set(name, fn)};
  const win = {addEventListener: (name: string, fn: Function, options: any) => {assert.equal(options.capture, true);windowEvents.set(name, fn);}};
  const library = {Tooltip: {getInstance: () => available ? {hide: () => hides++} : null}, Popover: {getInstance: () => available ? {hide: () => hides++} : null}};
  globalThis.document = doc as any;globalThis.window = win as any;
  const emit = (name: string, target = trigger) => events.get(name)!({target});
  const escape = (overrides = {}) => {const event = {key: 'Escape', defaultPrevented: false, stopped: false,
    preventDefault() {this.defaultPrevented = true;}, stopPropagation() {this.stopped = true;}, ...overrides};windowEvents.get('keydown')!(event);return event;};
  try {initFloatingHelp(library as any);run({events, doc, trigger, tip, tipChild, outside, attrs, emit, escape, library, nodes, hides: () => hides,
    disconnect: () => connected = false, dispose: () => available = false});}
  finally {globalThis.document = oldDocument;globalThis.window = oldWindow;}
}

test('Escape dismisses floating help first without replacing Tabler instances or configuration', () => fixture(f => {
  f.emit('shown.bs.tooltip');const event = f.escape();
  assert.equal(f.hides(), 1);assert.equal(event.defaultPrevented, true);assert.equal(event.stopped, true);
  f.emit('hidden.bs.tooltip');const next = f.escape();assert.equal(next.defaultPrevented, false);
}));

test('owned popovers expose expanded state and outside dismissal without changing extension defaults', () => fixture(f => {
  f.attrs.set('data-fb-help-popover', '');f.emit('shown.bs.popover');assert.equal(f.attrs.get('aria-expanded'), 'true');
  f.events.get('pointerdown')({target: f.trigger});f.events.get('pointerdown')({target: f.tipChild});assert.equal(f.hides(), 0);
  f.events.get('pointerdown')({target: f.outside});assert.equal(f.hides(), 1);
  f.emit('hidden.bs.popover');assert.equal(f.attrs.get('aria-expanded'), 'false');
  f.attrs.delete('data-fb-help-popover');f.emit('shown.bs.popover');f.events.get('pointerdown')({target: f.outside});assert.equal(f.hides(), 1);
}));

test('hidden containers dismiss their own help and leave other triggers alone', () => fixture(f => {
  f.emit('shown.bs.tooltip');const container = {contains: (el: unknown) => el === f.trigger};
  f.emit('hidden.bs.modal', {contains: () => false});assert.equal(f.hides(), 0);
  f.emit('hidden.bs.collapse', container);assert.equal(f.hides(), 1);
  f.nodes.set('pane:one', container);
  f.emit('hidden.bs.tab', {getAttribute: (name: string) => name === 'href' ? '#pane:one' : null});assert.equal(f.hides(), 2);
  f.emit('hidden.bs.dropdown', {getAttribute: () => 'pane:one'});assert.equal(f.hides(), 3);
}));

test('detached or extension-disposed help never traps Escape', () => {
  fixture(f => {f.emit('shown.bs.tooltip');f.disconnect();assert.equal(f.escape().defaultPrevented, false);assert.equal(f.hides(), 0);});
  fixture(f => {f.emit('shown.bs.popover');f.dispose();assert.equal(f.escape().defaultPrevented, false);assert.equal(f.hides(), 0);});
});

test('initialization is idempotent and already handled keyboard events remain handled', () => fixture(f => {
  initFloatingHelp({Tooltip: {getInstance: () => assert.fail('rebound')}, Popover: {getInstance: () => assert.fail('rebound')}});
  f.emit('shown.bs.tooltip');f.escape({defaultPrevented: true});assert.equal(f.hides(), 0);
  f.escape();assert.equal(f.hides(), 1);
}));
