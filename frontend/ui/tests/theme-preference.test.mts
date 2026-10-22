import assert from 'node:assert/strict';
import { test } from 'node:test';
import initThemePreference, {resolveTheme} from '../theme-preference.ts';

class Choice extends EventTarget {
  dataset: Record<string, string> = {};
  attributes = new Map<string, string>();
  classes = new Set<string>();
  classList = {toggle: (name: string, active: boolean) => active ? this.classes.add(name) : this.classes.delete(name)};
  closest(_selector: string): {querySelector: (selector: string) => {focus: () => void}} | null {return null;}
  setAttribute(name: string, value: string) {this.attributes.set(name, value);}
}

function fixture(run: (state: ReturnType<typeof createState>) => void, saved: string | null = null, configured = 'light', blocked = false) {
  const previous = Object.fromEntries(['document', 'window', 'localStorage', 'tabler'].map(key => [key, (globalThis as any)[key]]));
  const state = createState(saved, configured, blocked);
  Object.assign(globalThis, state.globals);
  try { run(state); } finally {Object.assign(globalThis, previous);}
}

function createState(saved: string | null, configured: string, blocked: boolean) {
  const root = new Choice();root.dataset.fbThemeDefault = configured;
  const choices = ['system', 'light', 'dark'].map(preference => {const b = new Choice();b.dataset.themePreference = preference;return b;});
  const icons = ['system', 'light', 'dark'].map(preference => {const b = new Choice();b.dataset.themeIcon = preference;return b;});
  const system = Object.assign(new EventTarget(), {matches: false});
  const window = Object.assign(new EventTarget(), {matchMedia: () => system});
  const document = {
    documentElement: root,
    querySelectorAll: (selector: string) => selector === '[data-theme-preference]' ? choices : selector === '[data-theme-icon]' ? icons : [],
  };
  const localStorage = {getItem: () => {if(blocked) throw new Error('blocked');return saved;}, setItem: (_key: string, value: string) => {if(blocked) throw new Error('blocked');saved = value;}};
  return {
    root, choices, icons, system, window, document,
    globals: {document, window, localStorage, tabler: {Tooltip: {getInstance: () => null}}},
    stored: () => saved,
    device(dark: boolean) {system.matches = dark;system.dispatchEvent(new Event('change'));},
    storage(value: string | null) {saved = value;window.dispatchEvent(Object.assign(new Event('storage'), {key: 'theme'}));},
    select(value: string) {choices.find(b => b.dataset.themePreference === value)!.dispatchEvent(new Event('click'));},
  };
}

test('system preference follows device changes, while explicit choices stay fixed', () => fixture(f => {
  initThemePreference();
  assert.equal(f.root.attributes.get('data-bs-theme'), 'light');
  f.select('system');f.device(true);
  assert.equal(f.stored(), 'system');
  assert.equal(f.root.attributes.get('data-bs-theme'), 'dark');
  assert.equal(f.choices[0].attributes.get('aria-pressed'), 'true');
  assert.equal(f.icons[0].classes.has('d-none'), false);
  f.select('light');f.device(false);f.device(true);
  assert.equal(f.root.attributes.get('data-bs-theme'), 'light');
  f.select('dark');f.device(false);
  assert.equal(f.root.attributes.get('data-bs-theme'), 'dark');
}));

test('saved preferences sync between client and admin and clearing storage restores the configured default', () => fixture(f => {
  initThemePreference();f.device(true);
  assert.equal(f.root.attributes.get('data-bs-theme'), 'light');
  f.storage('system');
  assert.equal(f.root.attributes.get('data-bs-theme'), 'dark');
  f.storage(null);
  assert.equal(f.root.attributes.get('data-bs-theme'), 'light');
  f.storage('invalid');
  assert.equal(f.root.attributes.get('data-bs-theme'), 'light');
}, 'light'));

test('admin defaults to the system setting and unavailable storage still permits session choices', () => fixture(f => {
  initThemePreference();f.device(true);
  assert.equal(f.root.attributes.get('data-bs-theme'), 'dark');
  f.select('light');f.device(false);f.device(true);
  assert.equal(f.root.attributes.get('data-bs-theme'), 'light');
  initThemePreference(); // Existing listeners and a storage-blocked choice remain intact.
  assert.equal(f.root.attributes.get('data-bs-theme'), 'light');
}, null, 'system', true));

test('theme resolution never lets the device override an explicit choice', () => {
  assert.equal(resolveTheme('light', true), 'light');
  assert.equal(resolveTheme('dark', false), 'dark');
  assert.equal(resolveTheme('system', true), 'dark');
  assert.equal(resolveTheme('system', false), 'light');
});

test('legacy extension toggle links still switch themes and return focus to a visible control', () => fixture(f => {
  const previousLocation = (globalThis as any).location;
  (globalThis as any).location = {href: 'https://example.test/client'};
  let focused = '';
  const link = (theme: string) => Object.assign(new Choice(), {
    href: `https://example.test/client?theme=${theme}`,
    getClientRects: () => f.root.attributes.get('data-bs-theme') === theme ? [] : [{}],
    focus: () => {focused = theme;},
  });
  const dark = link('dark'), light = link('light');
  const query = f.document.querySelectorAll;
  f.document.querySelectorAll = selector => selector === '.js-theme-toggler' ? [dark, light] : query(selector);
  Object.assign(f.document, {activeElement: dark});
  try {
    initThemePreference();
    const event = new Event('click', {cancelable: true});
    dark.dispatchEvent(event);
    assert.equal(event.defaultPrevented, true);
    assert.equal(f.root.attributes.get('data-bs-theme'), 'dark');
    assert.equal(f.stored(), 'dark');
    assert.equal(focused, 'light');
  } finally {(globalThis as any).location = previousLocation;}
}));
