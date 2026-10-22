// cspell:ignore Chargement
import assert from 'node:assert/strict';
import { test } from 'node:test';
const testGlobal = globalThis as any;
const previousWindow = testGlobal.window;
testGlobal.window = {FOSSBilling: {ready() {}}, location: {origin: 'https://billing.example'}};
await import('../api.ts');
const api = testGlobal.window.FOSSBilling.api;
testGlobal.window = previousWindow;

function fixture(label?: string) {
  const attached: any[] = [];
  const doc = {
    querySelector: () => label === undefined ? null : {dataset: {requestLoading: label}},
    createElement: () => ({className: '', textContent: '', style: {}, children: [] as any[], attrs: new Map(),
      setAttribute(name: string, value: string) {this.attrs.set(name, value);},
      appendChild(child: any) {this.children.push(child);}}),
    body: {appendChild: (node: any) => attached.push(node)},
  };
  return {doc, attached};
}

for (const label of [undefined, 'Chargement <test>']) {
  test(`request loader supplies status text and preserves API cleanup contract (${label ?? 'fallback'})`, () => {
    const f = fixture(label);
    const previousDocument = testGlobal.document;
    testGlobal.document = f.doc;
    const loader = api._createLoader();
    try {
      assert.equal(loader.attrs.get('role'), 'status');
      assert.equal(loader.attrs.get('aria-atomic'), 'true');
      assert.equal(loader.children[0].textContent, label ?? 'Loading. Please wait.');
      assert.equal(loader.children[0].className, 'visually-hidden');
      assert.match(loader.className, /spinner-border/);
      assert.equal(loader.style.translate, '-50% -50%');
      assert.equal(loader.style.pointerEvents, 'none');
      assert.equal(loader.style.opacity, '0');
      assert.equal(f.attached[0], loader);
      assert.ok(loader._fadeInTimeout);
    } finally {
      clearTimeout(loader._fadeInTimeout);
      testGlobal.document = previousDocument;
    }
  });
}
