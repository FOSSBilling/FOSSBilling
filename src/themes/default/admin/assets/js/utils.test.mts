import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import { listParamKeys, pruneListParamsForTab } from './utils.ts';

describe('listParamKeys', () => {
  test('returns bare keys for the default namespace', () => {
    assert.deepEqual(listParamKeys(''), ['sort', 'direction', 'page', 'per_page']);
  });

  test('prefixes keys for a namespaced list', () => {
    assert.deepEqual(listParamKeys('registrar'), ['registrar_sort', 'registrar_direction', 'registrar_page', 'registrar_per_page']);
  });
});

describe('pruneListParamsForTab', () => {
  test("drops other tabs' list params and keeps the target tab's", () => {
    assert.equal(
      pruneListParamsForTab('?sort=tld&direction=ASC&page=1&registrar_sort=title', 'registrar', ['']),
      '?registrar_sort=title'
    );
  });

  test('keeps search terms, filters, and ids', () => {
    assert.equal(
      pruneListParamsForTab('?search=com&client_id=7&sort=tld&page=2', 'registrar', ['', 'registrar']),
      '?search=com&client_id=7'
    );
  });

  test('keeps shared-namespace keys owned by both panes', () => {
    assert.equal(
      pruneListParamsForTab('?sort=title&page=1', '', ['']),
      '?sort=title&page=1'
    );
  });

  test('returns an empty string when nothing remains', () => {
    assert.equal(pruneListParamsForTab('?sort=tld&page=1', 'registrar', ['']), '');
  });

  test('leaves the query untouched without annotated panes', () => {
    assert.equal(
      pruneListParamsForTab('?sort=tld&page=1', null, []),
      '?sort=tld&page=1'
    );
  });

  test('drops default-namespace keys when the target pane is not a list', () => {
    assert.equal(
      pruneListParamsForTab('?sort=tld&page=3', null, ['']),
      ''
    );
  });

  test("resets one tab while keeping other tabs' keys (re-click active tab)", () => {
    assert.equal(
      pruneListParamsForTab('?sort=tld&page=2&registrar_sort=title&search=x', null, ['']),
      '?registrar_sort=title&search=x'
    );
  });
});
