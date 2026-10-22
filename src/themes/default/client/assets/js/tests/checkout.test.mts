import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { test } from 'node:test';

// Execute the callback supplied by the module template, with translated text and local URLs.
const template = readFileSync(new URL('../../../../../../modules/Orderbutton/templates/client/mod_orderbutton_js.html.twig', import.meta.url), 'utf8');
const callback = template.slice(template.indexOf('    function onOrderCheckout('), template.indexOf('    function onLogin('))
  .replace(/\{\{\s*(['"])(.*?)\1\|trans\|json_encode\|raw\s*\}\}/g, (_match, _quote, text) => JSON.stringify(text))
  .replace(/\{\{\s*"([^"]+)"\|url\s*\}\}/g, (_match, path) => `/${path}`);
assert.equal(callback.includes('{{'), false);

const pendingResponse = Symbol('pending response');

function fixture(payment: unknown = {iframe: true, result: '<iframe title="Provider"></iframe>'}, throws = false) {
  let active: Element;
  class Element extends EventTarget {
    id = ''; tagName: string; hidden = false; href = ''; target = ''; textContent = ''; innerHTML = '';
    children: Element[] = []; attributes = new Map<string, string>(); classes = new Set<string>(); removed = false; clicked = false;
    className = '';
    classList = {
      contains: (value: string) => this.classes.has(value),
      replace: (previous: string, next: string) => {
        const tokens = this.className.split(' ');
        const index = tokens.indexOf(previous);
        if (index < 0) return false;
        tokens[index] = next;
        this.className = tokens.join(' ');
        return true;
      },
    };
    constructor(tag: string) { super(); this.tagName = tag; }
    setAttribute(name: string, value: string) {this.attributes.set(name, value);}
    replaceChildren(...children: Element[]) {this.children = children;}
    closest() {return elements.form;}
    remove() {this.removed = true; active = elements.body;}
    focus() {active = this;}
    click() {this.clicked = true;}
    scrollIntoView() {}
  }
  const elements = Object.fromEntries(['body', 'form', 'checkout', 'checkoutButton', 'payment-step', 'payment-html', 'payment-html-inner', 'toggle'].map(id => [id, new Element(id)]));
  active = elements.checkoutButton;
  let calls = 0;
  let success: (result: unknown) => void;
  let failure: () => void;
  const context = {
    window: {top: {location: {href: ''}}},
    document: {
      getElementById: (id: string) => elements[id],
      querySelector: () => elements.toggle,
      createElement: (tag: string) => new Element(tag),
    },
    tabler: {Collapse: {getOrCreateInstance: () => ({hide() {}, show() {}})}},
    FOSSBilling: {api: {guest: {post: (_endpoint: string, _data: unknown, ok: typeof success, fail: typeof failure) => {
      calls++; success = ok; failure = fail;
      if (throws) throw new Error('Extension gateway failed');
      if (payment !== pendingResponse) ok(payment);
    }}}},
  };
  runInNewContext(`${callback}; globalThis.checkout = onOrderCheckout;`, context);
  return {
    run: (result = {invoice_hash: 'demo-hash', gateway_id: 7, order_id: 12}) => (context as any).checkout(result),
    reveal: () => {elements['payment-html'].classes.add('show'); elements['payment-html'].dispatchEvent(new Event('shown.bs.collapse'));},
    elements, context, calls: () => calls, active: () => active,
    succeed: (result: unknown) => success(result), fail: () => failure(),
    invoice: () => elements['payment-html-inner'].children[1],
    status: () => elements['payment-html-inner'].children[0],
    gateway: () => elements['payment-html-inner'].children[2],
  };
}

test('embedded gateway content retains its HTML and focus moves after the panel becomes visible', () => {
  const html = '<iframe title="Extension provider" data-extension="preserved"></iframe>';
  const f = fixture({iframe: true, result: html}); f.run();
  assert.equal(f.gateway().attributes.get('aria-busy'), 'false');
  assert.match(f.status().textContent, /Complete payment/);
  assert.equal(f.gateway().innerHTML, html);
  assert.equal(f.elements.form.removed, true);
  assert.equal(f.active(), f.elements.body);
  f.reveal();
  assert.equal(f.active(), f.invoice());
  assert.equal(f.invoice().attributes.get('aria-describedby'), f.status().id);
  assert.equal(f.invoice().href, '/invoice/demo-hash');
  assert.equal(f.invoice().target, '_parent');
});

test('missing gateways and synchronous extension failures recover to the invoice without resubmitting', () => {
  for (const gateway of [false, true]) {
    const f = fixture(undefined, gateway);
    f.run({invoice_hash: 'demo-hash', gateway_id: gateway ? 7 : 0, order_id: 12}); f.reveal();
    assert.match(f.status().textContent, /payment could not be started/);
    assert.equal(f.active(), f.invoice());
    assert.match(f.invoice().className, /\bbtn-primary\b/);
    assert.equal(f.calls(), gateway ? 1 : 0);
    assert.equal(f.elements.form.removed, true);
  }
});

test('empty or malformed embedded payment responses provide invoice recovery', () => {
  for (const response of [null, {iframe: true}, {iframe: true, result: ''}, {iframe: true, result: '   '}]) {
    const f = fixture(response); f.run(); f.reveal();
    assert.match(f.status().textContent, /payment could not be started/);
    assert.equal(f.active(), f.invoice());
  }
});

test('late gateway failures restore focus to recovery after the panel is already open', () => {
  const f = fixture(pendingResponse); f.run();
  assert.equal(f.gateway().attributes.get('aria-busy'), 'true');
  assert.match(f.status().textContent, /Opening payment options/);
  f.reveal(); f.fail();
  assert.equal(f.gateway().attributes.get('aria-busy'), 'false');
  assert.equal(f.active(), f.invoice());
  assert.match(f.status().textContent, /payment could not be started/);
});

test('redirect gateways keep a visible parent-target continuation link', () => {
  const f = fixture({type: 'form', result: {merchant: 'extension'}}); f.run();
  const link = f.gateway().children[0];
  assert.equal(link.href, '/invoice/banklink/demo-hash/7');
  assert.equal(link.target, '_parent');
  assert.match(f.invoice().className, /\bbtn-outline-secondary\b/);
  assert.match(link.className, /\bbtn-primary\b/);
  assert.equal(link.clicked, true);
});

test('free or credit-paid checkouts go to the service without requesting payment', () => {
  const f = fixture(); f.run({invoice_hash: '', gateway_id: 0, order_id: 12});
  assert.equal(f.context.window.top.location.href, '/order/service/manage/12');
  assert.equal(f.calls(), 0);
  assert.equal(f.elements.form.removed, false);
});

test('a created checkout account recovers failed automatic sign-in without offering registration again', () => {
  const source = template.slice(template.indexOf('    function onAccountCreate('), template.lastIndexOf('</script>'))
    .replace(/\{\{\s*(['"])(.*?)\1\|trans\|json_encode\|raw\s*\}\}/g, (_match, _quote, text) => JSON.stringify(text));
  for (const throws of [false, true]) {
    let failure: () => void;
    let selected = false;
    const nodes: Record<string, any> = {};
    const makeNode = () => ({hidden: false, textContent: '', children: [] as any[], focused: false, setAttribute() {}, append(child: any) {this.children.push(child);}, focus() {this.focused = true;}, addEventListener(_event: string, handler: () => void) {this.click = handler;}, click() {}});
    const form = {...makeNode(), before(node: unknown) {nodes.status = node;}};
    nodes['reg-email'] = {value: 'client@example.test', form};
    nodes['reg-password'] = {value: 'ExamplePass123!'};
    nodes.email = {value: ''}; nodes.password = makeNode(); nodes['in-tab'] = makeNode();
    const context = {
      document: {getElementById: (id: string) => nodes[id], createElement: makeNode},
      onLogin() {assert.fail('Failed sign-in must not navigate as authenticated');},
      FOSSBilling: {api: {guest: {post(_endpoint: string, _data: unknown, _ok: unknown, fail: () => void) {
        failure = fail;
        if (throws) throw new Error('Transport unavailable');
      }}}},
      tabler: {Tab: {getOrCreateInstance: () => ({show() {selected = true;}})}},
    };
    runInNewContext(source + '; onAccountCreate({});', context);
    assert.equal(form.hidden, true);
    if (!throws) failure!();
    assert.match(nodes.status.textContent, /account has been created.*automatic sign-in did not complete/);
    assert.equal(nodes.status.focused, true);
    nodes.status.children[0].click();
    assert.equal(selected, true);
    assert.equal(nodes.email.value, 'client@example.test');
    assert.equal(nodes.password.focused, true);
  }
});
