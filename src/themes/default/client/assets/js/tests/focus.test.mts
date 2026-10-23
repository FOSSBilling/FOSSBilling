import assert from 'node:assert/strict';
import { test } from 'node:test';
import { wrapModalFocus } from '../ui/focus.ts';

test('modal focus wraps in both directions and leaves ordinary tabbing alone', () => {
  const controls = Array.from({length: 3}, () => ({tabIndex: 0, matches: () => false, closest: () => null, getClientRects: () => [{}], focused: false, focus() {this.focused = true;}}));
  const modal = {querySelectorAll: () => controls};
  (globalThis as any).getComputedStyle = () => ({visibility: 'visible'});
  let prevented = 0;
  const event = (index: number, shiftKey = false) => ({key: 'Tab', shiftKey, target: {...controls[index], closest: () => modal}, preventDefault() {prevented++;}});
  // Event targets are the actual focusable nodes, rather than copies.
  controls.forEach(control => {control.closest = (selector?: string) => selector?.startsWith('.modal') ? modal as any : null;});
  wrapModalFocus({...event(2), target: controls[2]} as any);
  assert.equal(controls[0].focused, true);
  wrapModalFocus({...event(0, true), target: controls[0]} as any);
  assert.equal(controls[2].focused, true);
  wrapModalFocus({...event(1), target: controls[1]} as any);
  assert.equal(prevented, 2);
  wrapModalFocus({...event(2), target: controls[2], defaultPrevented: true} as any);
  assert.equal(prevented, 2);
});

test('Tab outside a modal and non-Tab keys retain native behaviour', () => {
  const target = {closest: () => null};
  const preventDefault = () => assert.fail('Native navigation must remain unchanged');
  wrapModalFocus({key: 'Tab', target, preventDefault} as any);
  wrapModalFocus({key: 'Escape', target, preventDefault} as any);
});
