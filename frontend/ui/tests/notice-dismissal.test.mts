import assert from 'node:assert/strict';
import { test } from 'node:test';
import initNoticeDismissal from '../notice-dismissal.ts';

function fixture(run: (f: any) => void) {
  const previous = globalThis.document;
  let close: Function, closed: Function, focus = 0, binds = 0;
  const button = {}, body = {}, elsewhere = {};
  const candidate = {isConnected: true, tabIndex: 0, getClientRects: () => [1], matches: () => false, closest: () => null,
    focus(options: any) {assert.equal(options.preventScroll, true);focus++;}};
  const notice = {matches: () => true, contains: (el: unknown) => el === button, compareDocumentPosition: () => 4,
    addEventListener(name: string, cb: Function, options: any) {assert.equal(name, 'closed.bs.alert');assert.equal(options.once, true);binds++;closed = cb;}};
  const doc = {body, activeElement: button, querySelectorAll: () => [candidate], addEventListener: (_: string, cb: Function) => close = cb};
  globalThis.document = doc as any;
  try {initNoticeDismissal();run({doc, button, body, elsewhere, candidate, notice, close: () => close!({target: notice}), closed: () => closed!(), counts: () => ({focus, binds})});}
  finally {globalThis.document = previous;}
}

test('notice dismissal restores focus after Tabler removes the focused close button', () => fixture(f => {
  f.close();assert.equal(f.counts().focus, 0);f.doc.activeElement = f.body;f.closed();assert.equal(f.counts().focus, 1);
}));

test('pointer dismissal and focus moved during a fade do not steal focus', () => {
  fixture(f => {f.doc.activeElement = f.elsewhere;f.close();f.doc.activeElement = f.body;f.closed();assert.equal(f.counts().focus, 0);});
  fixture(f => {f.close();f.doc.activeElement = f.elsewhere;f.closed();assert.equal(f.counts().focus, 0);});
});

test('cancelled close attempts can retry without duplicate focus listeners', () => fixture(f => {
  f.close();f.close();assert.equal(f.counts().binds, 1);f.doc.activeElement = f.body;f.closed();assert.equal(f.counts().focus, 1);
}));

test('hidden disabled and removed recovery targets are skipped and initialization is idempotent', () => fixture(f => {
  initNoticeDismissal();f.candidate.isConnected = false;f.close();f.doc.activeElement = f.body;f.closed();assert.equal(f.counts().focus, 0);
}));
