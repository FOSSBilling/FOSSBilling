import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import { parseDataAttr } from '../parse-data-attr.mjs';

const parse = (value) => parseDataAttr(JSON.stringify(value));

describe('parseDataAttr', () => {
  test('handles empty and valid values', () => {
    assert.deepEqual(parseDataAttr(''), {});

    const data = {
      href: '/api/client/profile/update',
      reload: true,
      timeoutMs: 5000,
      params: { id: 1 },
      loading: { message: 'Saving', button: 'Wait' },
      modal: { type: 'prompt', key: 'reason', title: 'Reason' },
    };
    assert.deepEqual(parse(data), data);
  });

  test('rejects invalid JSON and non-object values', () => {
    assert.throws(() => parseDataAttr('{'), /Invalid JSON/);

    for (const value of [null, [], 42, true, 'value']) {
      assert.throws(() => parse(value), /must be a JSON object/);
    }
  });

  test('validates top-level scalar fields', () => {
    for (const key of ['href', 'type', 'endpoint', 'callback', 'message', 'redirect', 'timeoutMessage']) {
      assert.throws(() => parse({ [key]: 1 }), new RegExp(`${key} must be a string`));
    }

    for (const key of ['reload', 'preventNavigation']) {
      assert.throws(() => parse({ [key]: 'true' }), new RegExp(`${key} must be a boolean`));
    }

    for (const value of [0, -1, Infinity, '100']) {
      assert.throws(() => parse({ timeoutMs: value }), /timeoutMs must be a positive number/);
    }

    for (const value of [null, [], 'params']) {
      assert.throws(() => parse({ params: value }), /params must be an object/);
    }
  });

  test('validates loading fields', () => {
    assert.throws(() => parse({ loading: [] }), /loading must be an object/);

    for (const key of ['message', 'button', 'target', 'alertClass']) {
      assert.throws(() => parse({ loading: { [key]: false } }), new RegExp(`loading.${key} must be a string`));
    }
  });

  test('validates modal type and string fields', () => {
    assert.throws(() => parse({ modal: null }), /modal must be an object/);
    assert.throws(() => parse({ modal: {} }), /modal.type must be a string/);
    assert.throws(() => parse({ modal: { type: 'unknown' } }), /must be one of/);
    assert.throws(() => parse({ modal: { type: 'prompt' } }), /modal.key is required/);

    for (const key of ['title', 'content', 'button', 'buttonColor', 'label', 'value', 'key']) {
      assert.throws(
        () => parse({ modal: { type: 'confirm', [key]: false } }),
        new RegExp(`modal.${key} must be a string`),
      );
    }
  });

  test('allows unknown fields for forward compatibility', () => {
    assert.deepEqual(parse({ futureOption: { enabled: true } }), { futureOption: { enabled: true } });
  });
});
