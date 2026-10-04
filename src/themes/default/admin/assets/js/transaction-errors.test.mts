import assert from 'node:assert/strict';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';
import { fileURLToPath } from 'node:url';
import { buildSync } from 'esbuild';

const runtime = buildSync({
  entryPoints: [fileURLToPath(new URL('./fossbilling.ts', import.meta.url))],
  bundle: true,
  write: false,
  format: 'iife',
}).outputFiles[0].text;

test('transaction clicks render stored errors as text, including dynamically inserted links', () => {
  class Element {
    children: Element[] = [];
    dataset: Record<string, string> = {};
    textContent = '';
    parent: Element | null = null;
    classList = { add() {} };
    appendChild(child: Element) { this.children.push(child); }
    setAttribute() {}
    addEventListener() {}
    closest(selector: string): Element | null {
      assert.equal(selector, 'a[data-transaction-error]');
      return this.parent;
    }
    set innerHTML(_value: string) { assert.fail('Error text must never be parsed as HTML'); }
  }
  const container = new Element();
  const listeners = new Map<string, Function>();
  const context: any = {
    Element,
    document: {
      addEventListener: (type: string, listener: Function) => listeners.set(type, listener),
      querySelector: () => container,
      createElement: () => new Element(),
    },
    tabler: { Toast: class { show() {} } },
  };
  runInNewContext(runtime, context);
  const click = listeners.get('click')!;
  for (const message of [
    "');globalThis.transactionErrorExecuted = true;//",
    '<img src=x onerror="globalThis.transactionErrorExecuted=true">',
    'Gateway\'s "declined" response \\ retry\nPayment failed — £10',
  ]) {
    const link = new Element();
    link.dataset = { transactionError: message, transactionErrorCode: '42' };
    const target = new Element();
    target.parent = link;
    let prevented = false;
    click({ target, preventDefault: () => { prevented = true; } });
    assert.equal(prevented, true);
    const toast = container.children.at(-1)!;
    assert.equal(toast.children[1].textContent, message);
    assert.equal(toast.children[0].children[1].textContent, 'Info');
    assert.equal(context.transactionErrorExecuted, undefined);
  }
  const unrelated = new Element();
  const count = container.children.length;
  click({ target: unrelated, preventDefault: () => assert.fail('Unrelated clicks must remain unchanged') });
  click({ target: {}, preventDefault: () => assert.fail('Non-element clicks must remain unchanged') });
  assert.equal(container.children.length, count);
});
