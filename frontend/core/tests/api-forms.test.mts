import assert from 'node:assert/strict';
import { test } from 'node:test';

const testGlobal = globalThis as any;
const forms: any[] = [];
const NativeFormData = FormData;
testGlobal.window = { FOSSBilling: { ready() {}, ui: { notify() {} } }, location: { origin: 'https://billing.example' } };
testGlobal.document = { cookie: '', querySelectorAll: () => forms };
testGlobal.FormData = class extends NativeFormData {
  constructor(form: any) {
    super();
    for (const [name, value] of form.fields) this.append(name, value);
  }
};
await import('../api.ts');
const api = testGlobal.window.FOSSBilling.api;

function formFixture(fields: any[] = [['title', 'Cards']], enctype = 'application/x-www-form-urlencoded') {
  const attributes = new Map<string, string>([['method', 'post'], ['action', '/api/admin/invoice/gateway_update']]);
  const buttons = [{ disabled: false, matches: () => true }, { disabled: true, matches: () => true }];
  let handler: (event: any) => void;
  let bindings = 0;
  const form = {
    dataset: {}, enctype, fields, elements: buttons,
    addEventListener(_event: string, callback: any) { handler = callback; bindings++; },
    getAttribute(name: string) { return attributes.get(name) ?? null; },
    setAttribute(name: string, value: string) { attributes.set(name, value); },
    removeAttribute(name: string) { attributes.delete(name); },
    querySelectorAll() { return buttons.filter(button => !button.disabled); },
  };
  forms.splice(0, forms.length, form);
  api._apiForm();
  return { form, buttons, bindings: () => bindings, submit: (event: any = {}) => handler!({ preventDefault() {}, ...event }) };
}

test('API form binding preserves uploaded files and nested fields in multipart requests', async () => {
  const image = new File(['image bytes'], 'cards.png', { type: 'image/png' });
  const fixture = formFixture([['title', 'Cards'], ['config[key]', 'value'], ['logo_file', image]], 'multipart/form-data');
  let body: FormData;
  api.makeRequest = (method: string, url: string, params: FormData) => {
    assert.equal(method, 'post');
    assert.equal(url, 'https://billing.example/api/admin/invoice/gateway_update');
    body = params;
  };
  fixture.submit();
  assert.ok(body! instanceof NativeFormData);
  assert.equal(body!.get('title'), 'Cards');
  assert.equal(body!.get('config[key]'), 'value');
  assert.equal(await (body!.get('logo_file') as File).text(), 'image bytes');
});

test('ordinary API forms still submit JSON and include the named submitter', () => {
  const fixture = formFixture([['title', 'Cards'], ['config[key]', 'value']]);
  api.makeRequest = (_method: string, _url: string, params: string) => {
    assert.deepEqual(JSON.parse(params), { title: 'Cards', config: { key: 'value' }, save: 'continue' });
  };
  fixture.submit({ submitter: { name: 'save', value: 'continue' } });
});

test('API forms bind once, prevent overlapping requests and recover after failure and success', () => {
  const fixture = formFixture();
  let requests = 0;
  let succeed: (value: unknown) => void;
  let fail: (error: unknown) => void;
  api._afterComplete = () => {};
  api.makeRequest = (_method: string, _url: string, _params: unknown, success: any, error: any) => {
    requests++; succeed = success; fail = error;
  };
  api._apiForm();
  assert.equal(fixture.bindings(), 1);
  fixture.submit(); fixture.submit();
  assert.equal(requests, 1);
  assert.equal(fixture.form.getAttribute('aria-busy'), 'true');
  assert.deepEqual(fixture.buttons.map(button => button.disabled), [true, true]);
  fail!({ message: 'Try again', code: 500 });
  assert.equal(fixture.form.getAttribute('aria-busy'), null);
  assert.deepEqual(fixture.buttons.map(button => button.disabled), [false, true]);
  fixture.submit();
  assert.equal(requests, 2);
  succeed!({});
  assert.equal(fixture.form.getAttribute('aria-busy'), null);
  assert.deepEqual(fixture.buttons.map(button => button.disabled), [false, true]);
});

test('API forms respect submission canceled by extension or validation handlers', () => {
  const fixture = formFixture();
  api.makeRequest = () => assert.fail('Canceled submissions must not reach the API');
  fixture.submit({ defaultPrevented: true });
  assert.equal(fixture.buttons[0].disabled, false);
});

test('theme form loading follows request completion and leaves callbacks and disabled controls intact', () => {
  const fixture = formFixture();
  const ui = testGlobal.window.FOSSBilling.ui;
  const states: boolean[] = [];
  let succeed: (result: unknown) => void;
  let fail: (error: unknown) => void;
  let saved = 0;
  ui.formSaved = (form: unknown) => {
    assert.equal(form, fixture.form);
    assert.equal(fixture.form.getAttribute('aria-busy'), 'true');
    saved++;
  };
  ui.formLoading = (form: unknown, busy: boolean) => {
    assert.equal(form, fixture.form);
    assert.deepEqual(fixture.buttons.map(button => button.disabled), [false, true]);
    states.push(busy);
  };
  api.makeRequest = (_method: string, _url: string, _params: unknown, success: any, error: any, loader: boolean) => {
    assert.equal(loader, false);
    succeed = success; fail = error;
  };
  try {
    fixture.submit(); fixture.submit();
    assert.deepEqual(states, [true]);
    fail!({message: 'Try again', code: 500});
    assert.equal(saved, 0);
    assert.deepEqual(states, [true, false]);
    fixture.submit(); succeed!({});
    assert.equal(saved, 1);
    assert.deepEqual(states, [true, false, true, false]);
    assert.deepEqual(fixture.buttons.map(button => button.disabled), [false, true]);
  } finally {
    delete ui.formLoading;
    delete ui.formSaved;
  }
});

test('API forms restore their original busy state after a synchronous request error', () => {
  const fixture = formFixture();
  fixture.form.setAttribute('aria-busy', 'false');
  api.makeRequest = () => { throw new Error('Transport unavailable'); };
  assert.throws(() => fixture.submit(), /Transport unavailable/);
  assert.equal(fixture.form.getAttribute('aria-busy'), 'false');
  assert.equal(fixture.buttons[0].disabled, false);
});

test('API errors use a supplied accessible form presenter instead of a duplicate toast', () => {
  const fixture = formFixture();
  const ui = testGlobal.window.FOSSBilling.ui;
  let cleared = 0;
  let presented = '';
  const notify = ui.notify;
  ui.notify = () => assert.fail('The same error must not also be toasted');
  ui.clearFormError = (form: unknown) => {assert.equal(form, fixture.form); cleared++;};
  ui.formError = (form: unknown, message: string) => {assert.equal(form, fixture.form); presented = message;};
  api.makeRequest = (_method: string, _url: string, _params: unknown, _success: unknown, failure: any) => failure({message: 'Review your details', code: 400});
  try {
    fixture.submit();
    assert.equal(cleared, 1);
    assert.equal(presented, 'Review your details');
    assert.equal(fixture.buttons[0].disabled, false);
  } finally {delete ui.clearFormError; delete ui.formError; ui.notify = notify;}
});

test('editor validation failures use the form presenter and never submit a request', () => {
  const fixture = formFixture();
  const runtime = testGlobal.window.FOSSBilling;
  let presented = false;
  runtime.editor = {validateForm: () => false};
  runtime.ui.formError = () => {presented = true;};
  api.makeRequest = () => assert.fail('Invalid editor content must not reach the API');
  try {
    fixture.submit();
    assert.equal(presented, true);
    assert.equal(fixture.buttons[0].disabled, false);
  } finally {delete runtime.editor; delete runtime.ui.formError;}
});

test('submission state locks input and external form buttons while preserving disabled controls and field values', () => {
  const fixture = formFixture([['title', 'Original Title']]);
  const input = {disabled: false, matches: () => true};
  const external = {disabled: false, matches: () => true};
  const field = {disabled: false, matches: () => false};
  fixture.form.elements.push(input, external, field);
  let fail: (error: unknown) => void;
  let requests = 0;
  api.makeRequest = (_method: string, _url: string, params: string, _success: any, error: any) => {
    assert.equal(JSON.parse(params).title, 'Original Title');
    requests++; fail = error;
  };
  fixture.submit(); fixture.submit();
  assert.equal(requests, 1);
  assert.equal(input.disabled, true);
  assert.equal(external.disabled, true);
  assert.equal(field.disabled, false);
  fail!({message: 'Try again', code: 500});
  assert.equal(input.disabled, false);
  assert.equal(external.disabled, false);
  assert.equal(fixture.buttons[1].disabled, true);
  fixture.submit();
  assert.equal(requests, 2);
  fail!({message: 'Try again', code: 500});
});

test('loading hooks receive the submitter without changing existing two-argument callbacks', () => {
  const fixture = formFixture();
  const ui = testGlobal.window.FOSSBilling.ui;
  const submitter = fixture.buttons[0];
  let fail: (error: unknown) => void;
  let started = 0;
  ui.formLoading = (form: unknown, busy: boolean, action: unknown) => {
    assert.equal(form, fixture.form);
    assert.equal(action, submitter);
    if (busy) started++;
  };
  api.makeRequest = (_method: string, _url: string, _params: unknown, _success: any, error: any) => {fail = error;};
  try {
    fixture.submit({submitter});
    assert.equal(started, 1);
    fail!({message: 'Try again', code: 500});
  } finally {delete ui.formLoading;}
});

test('a failing extension saved hook cannot leave the form disabled or busy', () => {
  const fixture = formFixture();
  const ui = testGlobal.window.FOSSBilling.ui;
  const states: boolean[] = [];
  let success: (result: unknown) => void;
  ui.formSaved = () => {throw new Error('Extension saved hook failed');};
  ui.formLoading = (_form: unknown, busy: boolean) => states.push(busy);
  api.makeRequest = (_method: string, _url: string, _params: unknown, saved: any) => {success = saved;};
  try {
    fixture.submit();
    assert.throws(() => success!({}), /Extension saved hook failed/);
    assert.equal(fixture.form.getAttribute('aria-busy'), null);
    assert.deepEqual(fixture.buttons.map(button => button.disabled), [false, true]);
    assert.deepEqual(states, [true, false]);
    states.length = 0;
    api.makeRequest = (_method: string, _url: string, _params: unknown, saved: any) => saved({});
    assert.throws(() => fixture.submit(), /Extension saved hook failed/);
    assert.deepEqual(states, [true, false]);
  } finally {delete ui.formSaved; delete ui.formLoading;}
});
