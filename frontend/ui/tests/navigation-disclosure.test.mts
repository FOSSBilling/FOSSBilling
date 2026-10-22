import assert from 'node:assert/strict';
import { test } from 'node:test';
import { bindNavigationDisclosure } from '../navigation-disclosure.ts';
import initDropdownFocus from '../dropdown-focus.ts';

function navigation(run: (f: any) => void) {
  const oldWindow = globalThis.window, oldDocument = globalThis.document;
  const events = new Map<string, Function>(), capture = new Map<string, Function>();
  let shown = true, visibleToggle = true, openDropdown = false, toggleFocus = 0, hidden = 0;
  const target = {closest: () => ({querySelector: () => openDropdown ? {} : null}), matches: () => true, getAttribute: () => 'false'};
  const toggle = {getClientRects: () => visibleToggle ? [1] : [], focus: () => toggleFocus++};
  const menu = {classList: {contains: () => shown}, contains: (node: unknown) => node === target,
    addEventListener: (name: string, fn: Function) => events.set(name, fn)};
  globalThis.window = {addEventListener: (name: string, fn: Function, options: any) => {assert.equal(options.capture, true);capture.set(name, fn);}} as any;
  globalThis.document = {activeElement: target} as any;
  const press = (overrides = {}) => ({key: 'Escape', target, defaultPrevented: false, preventDefault() {this.defaultPrevented = true;}, ...overrides});
  try {
    bindNavigationDisclosure(menu as any, toggle as any, () => hidden++);
    run({menu, toggle, target, events, capture, press, counts: () => ({toggleFocus, hidden}),
      setShown: (next: boolean) => shown = next, setVisible: (next: boolean) => visibleToggle = next,
      setDropdown: (next: boolean) => openDropdown = next});
  } finally {globalThis.window = oldWindow;globalThis.document = oldDocument;}
}

test('Escape dismisses mobile navigation and restores its trigger after the transition', () => navigation(f => {
  const event = f.press();f.events.get('keydown')(event);
  assert.equal(event.defaultPrevented, true);
  assert.deepEqual(f.counts(), {toggleFocus: 0, hidden: 1});
  f.events.get('hidden.bs.collapse')({target: f.menu});
  assert.equal(f.counts().toggleFocus, 1);
}));

test('dropdown dismissal gets the first Escape and closed toggles permit the next', () => navigation(f => {
  f.setDropdown(true);const first = f.press();f.capture.get('keydown')(first);
  assert.equal(first.defaultPrevented, false);assert.equal(f.counts().hidden, 0);
  f.setDropdown(false);const second = f.press();f.capture.get('keydown')(second);
  assert.equal(second.defaultPrevented, true);assert.equal(f.counts().hidden, 1);
}));

test('desktop navigation handled events and nested collapses keep their focus behaviour', () => navigation(f => {
  f.setVisible(false);f.events.get('keydown')(f.press());
  f.setVisible(true);f.events.get('keydown')(f.press({defaultPrevented: true}));
  f.setShown(false);f.events.get('keydown')(f.press());
  f.events.get('hidden.bs.collapse')({target: {}});
  assert.deepEqual(f.counts(), {toggleFocus: 0, hidden: 0});
}));

test('binding twice does not duplicate listeners or replace a hide callback', () => navigation(f => {
  bindNavigationDisclosure(f.menu, f.toggle, () => assert.fail('rebound'));
  f.events.get('keydown')(f.press());assert.equal(f.counts().hidden, 1);
}));

test('hidden dropdowns recover focus only from their own menu and support extension markup', () => {
  const previous = globalThis.document;
  let listener: Function, focused = 0, visible = true, disabled = false;
  const inside = {}, elsewhere = {};
  const menu = {contains: (el: unknown) => el === inside};
  const trigger = {matches: (selector: string) => selector.startsWith(':disabled') ? disabled : true,
    getClientRects: () => visible ? [1] : [], getAttribute: () => null,
    parentElement: {querySelector: () => menu}, focus: (options: any) => {assert.equal(options.preventScroll, true);focused++;}};
  const doc = {activeElement: inside, addEventListener: (_: string, fn: Function) => listener = fn, getElementById: () => menu};
  globalThis.document = doc as any;
  try {
    initDropdownFocus();initDropdownFocus();listener!({target: trigger});assert.equal(focused, 1);
    doc.activeElement = elsewhere;listener!({target: trigger});assert.equal(focused, 1);
    doc.activeElement = inside;visible = false;listener!({target: trigger});assert.equal(focused, 1);
    visible = true;disabled = true;listener!({target: trigger});assert.equal(focused, 1);
    disabled = false;trigger.getAttribute = () => 'extension-menu';listener!({target: trigger});assert.equal(focused, 2);
  } finally {globalThis.document = previous;}
});
