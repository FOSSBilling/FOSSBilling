import assert from 'node:assert/strict';
import { test } from 'node:test';

const testGlobal = globalThis as any;
const forms: any[] = [];
const NativeFormData = FormData;

testGlobal.window = { FOSSBilling: { ready() {} }, location: { origin: 'https://billing.example' } };
testGlobal.document = { cookie: '', querySelectorAll: () => forms };
testGlobal.FormData = class extends NativeFormData {
  constructor(form: any) {
    super();
    for (const [name, value] of form.fields) this.append(name, value);
  }
};
await import('../api.ts');

test('API form binding preserves uploaded files and nested fields in multipart requests', async () => {
  let submit: (event: any) => void;
  const image = new File(['image bytes'], 'cards.png', { type: 'image/png' });
  forms.push({
    dataset: {},
    enctype: 'multipart/form-data',
    fields: [['title', 'Cards'], ['config[key]', 'value'], ['logo_file', image]],
    addEventListener(_event: string, handler: any) { submit = handler; },
    getAttribute(name: string) { return name === 'method' ? 'post' : '/api/admin/invoice/gateway_update'; },
    querySelectorAll() { return []; },
  });
  let body: FormData;
  const api = testGlobal.window.FOSSBilling.api;
  api.makeRequest = (method: string, url: string, params: FormData) => {
    assert.equal(method, 'post');
    assert.equal(url, 'https://billing.example/api/admin/invoice/gateway_update');
    body = params;
  };
  api._apiForm();
  submit!({ preventDefault() {} });

  assert.ok(body! instanceof NativeFormData);
  assert.equal(body!.get('title'), 'Cards');
  assert.equal(body!.get('config[key]'), 'value');
  assert.equal(await (body!.get('logo_file') as File).text(), 'image bytes');
});

test('ordinary API forms still submit JSON', () => {
  let submit: (event: any) => void;
  forms.length = 0;
  forms.push({
    dataset: {},
    enctype: 'application/x-www-form-urlencoded',
    fields: [['title', 'Cards'], ['config[key]', 'value']],
    addEventListener(_event: string, handler: any) { submit = handler; },
    getAttribute(name: string) { return name === 'method' ? 'post' : '/api/admin/invoice/gateway_update'; },
    querySelectorAll() { return []; },
  });
  const api = testGlobal.window.FOSSBilling.api;
  api.makeRequest = (_method: string, _url: string, params: string) => {
    assert.deepEqual(JSON.parse(params), { title: 'Cards', config: { key: 'value' } });
  };
  api._apiForm();
  submit!({ preventDefault() {} });
});
