import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

for (const template of ['client/mod_support_ticket_upload.html.twig', 'admin/mod_support_ticket_reply_files.html.twig']) {
  test(`${template} rejects excess selections including uploads still in flight`, () => {
    const html = readFileSync(new URL(`../../../src/modules/Support/templates/${template}`, import.meta.url), 'utf8');
    const script = html.match(/<script>([\s\S]*?)<\/script>/i)![1].replace(/{{[\s\S]*?}}/g, 'translated __FILES__');
    const rows: any[] = [];
    const pending: any[] = [];
    const errors: string[] = [];
    const submit = { disabled: false };
    let change: () => void;
    const input: any = { files: [], value: '', disabled: true, setAttribute() {}, addEventListener(_name: string, fn: () => void) { change = fn; } };
    const list = {
      append(row: any) { rows.push(row); },
      querySelectorAll() { return rows.map((row) => row.children[1]); },
    };
    const widget = {
      closest() { return { querySelectorAll() { return [submit]; } }; },
      getAttribute() { return '60'; },
      querySelector(selector: string) {
        return selector === '#ticket-file-input' ? input : selector === '[data-ticket-files-list]' ? list : {};
      },
    };
    const post = (endpoint: string, _data: unknown, success: any, failure: any) => {
      if (endpoint === 'support/ticket_file_discard') success();
      else pending.push({ success, failure });
    };
    runInNewContext(script, {
      document: {
        addEventListener(_name: string, fn: () => void) { fn(); },
        querySelector() { return widget; },
        createElement() {
          return {
            children: [] as any[],
            append(...children: any[]) { this.children.push(...children); },
            addEventListener(_name: string, fn: () => void) { this.click = fn; },
            click() {},
            remove() { rows.splice(rows.indexOf(this), 1); },
          };
        },
      },
      FormData: class { append() {} },
      FOSSBilling: {
        message(text: string) { errors.push(text); },
        api: { client: { post }, admin: { post }, guest: { get(_endpoint: string, _data: unknown, success: any) {
          success({ max_files: 5, max_mb: 10, extensions: ['txt'], accept: '.txt' });
        } } },
      },
    });
    const select = (count: number) => { input.files = Array.from({ length: count }, () => ({})); change(); };
    const complete = () => { pending.shift().success({ id: `file-${rows.length}`, name: 'note.txt', size_label: '1 KB' }); };
    assert.equal(input.disabled, false);
    select(6);
    assert.equal(pending.length, 0);
    assert.equal(errors.length, 1);
    select(5);
    assert.equal(pending.length, 5);
    assert.equal(submit.disabled, true);
    select(1);
    assert.equal(pending.length, 5);
    assert.equal(errors.length, 2);
    while (pending.length) complete();
    assert.equal(rows.length, 5);
    assert.equal(submit.disabled, false);
    select(1);
    assert.equal(pending.length, 0);
    assert.equal(errors.length, 3);
    rows[0].children[2].click();
    assert.equal(rows.length, 4);
    select(1);
    assert.equal(pending.length, 1);
    pending.shift().failure();
    assert.equal(submit.disabled, false);
    select(1);
    complete();
    assert.equal(rows.length, 5);
  });
}
