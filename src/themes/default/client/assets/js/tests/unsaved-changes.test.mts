import assert from 'node:assert/strict';
import { test } from 'node:test';
import initUnsavedChanges, { formSnapshot, UnsavedForms } from '../ui/unsaved-changes.ts';

function fixture() {
  const field = (name: string, type = 'text', value = ''): any => ({name, type, value, tagName: 'INPUT', checked: false, closest: () => null});
  const title = field('title', 'text', 'Original');
  const token = field('CSRFToken', 'hidden', 'token');
  const reply = {...field('content', 'textarea'), tagName: 'TEXTAREA'};
  const attrs = new Map<string, string>();
  const form = {elements: [title, token, reply], getAttribute: (name: string) => attrs.get(name) ?? null} as unknown as HTMLFormElement;
  return {form, title, token, reply, attrs, field};
}

test('unchanged, reverted and hidden values do not warn; separate forms keep their own edits', () => {
  const a = fixture(), b = fixture(), tracker = new UnsavedForms();
  tracker.register(a.form); tracker.register(b.form);
  assert.equal(tracker.dirty(a.form), false);
  a.token.value = 'new token'; tracker.visit(a.form);
  assert.equal(tracker.dirty(a.form), false);
  a.title.value = 'Edited'; tracker.visit(a.form);
  assert.equal(tracker.dirty(a.form), true);
  a.title.value = 'Original';
  assert.equal(tracker.dirty(a.form), false);
  a.title.value = 'Saved'; b.title.value = 'Other edits'; tracker.visit(b.form);
  tracker.saved(a.form);
  assert.equal(tracker.dirty(a.form), false);
  assert.equal(tracker.dirty(b.form), true);
});

test('editor, checkbox, radio, multiple select and upload changes are compared without storing drafts', () => {
  const f = fixture(), tracker = new UnsavedForms();
  let editorContent = '';
  f.reply.editor = {getData: () => editorContent};
  const checkbox = f.field('terms', 'checkbox', '1');
  const radio = f.field('gateway_id', 'radio', '2');
  const select = {...f.field('options', 'select-multiple'), tagName: 'SELECT', selectedOptions: [{value: 'a'}]};
  const file = {...f.field('attachment', 'file'), files: [] as any[]};
  (f.form.elements as any).push(checkbox, radio, select, file);
  tracker.register(f.form); tracker.visit(f.form);
  for (const [edit, revert] of [
    [() => {editorContent = 'A reply';}, () => {editorContent = '';}],
    [() => {checkbox.checked = true;}, () => {checkbox.checked = false;}],
    [() => {radio.checked = true;}, () => {radio.checked = false;}],
    [() => {select.selectedOptions.push({value: 'b'});}, () => {select.selectedOptions.pop();}],
    [() => {file.files.push({name: 'notes.txt', size: 20, lastModified: 1});}, () => {file.files.pop();}],
  ]) {
    edit(); assert.equal(tracker.dirty(f.form), true);
    revert(); assert.equal(tracker.dirty(f.form), false);
  }
  f.reply.editor.getData = () => {throw new Error('Editor crashed');};
  f.reply.value = 'Last synchronized reply';
  assert.match(formSnapshot(f.form), /Last synchronized reply/);
});

test('failed requests remain dirty and edits made during a successful request remain protected', () => {
  const f = fixture(), tracker = new UnsavedForms();
  tracker.register(f.form); tracker.visit(f.form);
  f.title.value = 'Submitted'; tracker.submitting(f.form, true);
  f.attrs.set('aria-busy', 'true');
  assert.equal(tracker.dirty(f.form), true);
  tracker.submitting(f.form, false); f.attrs.delete('aria-busy');
  assert.equal(tracker.dirty(f.form), true);
  tracker.submitting(f.form, true);
  f.title.value = 'Typed while waiting';
  tracker.saved(f.form); tracker.submitting(f.form, false);
  assert.equal(tracker.dirty(f.form), true);
  tracker.visit(f.form);
  assert.equal(tracker.dirty(f.form), true);
  f.title.value = 'Submitted';
  assert.equal(tracker.dirty(f.form), false);
  f.title.value = 'Reply explicitly discarded'; tracker.saved(f.form);
  assert.equal(tracker.dirty(f.form), false);
});

test('phone formatting is not an edit but changing the dialing code is protected', () => {
  const f = fixture(), tracker = new UnsavedForms();
  const phone = f.field('phone', 'tel', '+447700900123');
  const code = f.field('phone_cc', 'hidden', '44');
  (f.form.elements as any).push(phone, code);
  tracker.register(f.form); tracker.visit(f.form);
  phone.value = '7700 900123';
  assert.equal(tracker.dirty(f.form), false);
  code.value = '33';
  assert.equal(tracker.dirty(f.form), true);
});


test('unsaved-change hooks forward the submitter to the existing loading presenter', () => {
  const previousDocument = globalThis.document;
  const previousWindow = globalThis.window;
  const previousRuntime = (globalThis as any).FOSSBilling;
  const f = fixture();
  Object.assign(f.form, {matches: () => true});
  const submitter = {};
  const calls: unknown[][] = [];
  (globalThis as any).document = {querySelectorAll: () => [f.form], addEventListener() {}};
  (globalThis as any).window = {addEventListener() {}};
  (globalThis as any).FOSSBilling = {ui: {formLoading: (...args: unknown[]) => calls.push(args)}};
  try {
    initUnsavedChanges();
    (globalThis as any).FOSSBilling.ui.formLoading(f.form, true, submitter);
    (globalThis as any).FOSSBilling.ui.formLoading(f.form, false);
    assert.deepEqual(calls, [[f.form, true, submitter], [f.form, false, undefined]]);
  } finally {
    globalThis.document = previousDocument;
    globalThis.window = previousWindow;
    (globalThis as any).FOSSBilling = previousRuntime;
  }
});
