/**
 * Unit tests for parseDataAttr — the validator that parses the JSON object
 * stored in a `data-fb-api` HTML attribute.
 *
 * Uses Node's built-in node:test runner (zero external dependencies).
 * Run via: `npm run test:js`.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  parseDataAttr,
  assertString,
  assertBoolean,
  assertPositiveNumber,
  assertPlainObject,
  validateLoading,
  validateModal,
  TOP_LEVEL_SCHEMA,
  MODAL_ALLOWED_TYPES,
} from '../parse-data-attr.mjs';

const rejects = async (fn, message) => {
  await assert.throws(fn, (err) => {
    assert.ok(err instanceof Error, `expected Error, got ${typeof err}`);
    assert.equal(err.message, message, `error message mismatch`);
    return true;
  });
};

test('parseDataAttr returns an empty object for falsy input', () => {
  assert.deepEqual(parseDataAttr(''), {});
  assert.deepEqual(parseDataAttr(null), {});
  assert.deepEqual(parseDataAttr(undefined), {});
  assert.deepEqual(parseDataAttr(0), {});
});

test('parseDataAttr throws on invalid JSON', async () => {
  await rejects(() => parseDataAttr('{not json'), 'Invalid JSON in data-fb-api attribute.');
  await rejects(() => parseDataAttr('"unclosed'), 'Invalid JSON in data-fb-api attribute.');
});

test('parseDataAttr requires the parsed value to be a JSON object (not array/scalar/null)', async () => {
  await rejects(() => parseDataAttr('[]'), 'data-fb-api must be a JSON object.');
  await rejects(() => parseDataAttr('42'), 'data-fb-api must be a JSON object.');
  await rejects(() => parseDataAttr('"a string"'), 'data-fb-api must be a JSON object.');
  await rejects(() => parseDataAttr('true'), 'data-fb-api must be a JSON object.');
  await rejects(() => parseDataAttr('null'), 'data-fb-api must be a JSON object.');
});

test('parseDataAttr returns a shallow-equivalent object for the empty object case', () => {
  assert.deepEqual(parseDataAttr('{}'), {});
});

for (const key of ['href', 'type', 'endpoint', 'callback', 'message', 'redirect']) {
  test(`parseDataAttr validates that ${key} is a string`, async () => {
    await rejects(
      () => parseDataAttr(JSON.stringify({ [key]: 123 })),
      `data-fb-api.${key} must be a string.`
    );
    await rejects(
      () => parseDataAttr(JSON.stringify({ [key]: true })),
      `data-fb-api.${key} must be a string.`
    );
    await rejects(
      () => parseDataAttr(JSON.stringify({ [key]: {} })),
      `data-fb-api.${key} must be a string.`
    );
  });

  test(`parseDataAttr accepts a valid string for ${key}`, () => {
    const input = { [key]: 'value' };
    assert.deepEqual(parseDataAttr(JSON.stringify(input)), input);
  });
}

for (const key of ['reload', 'preventNavigation']) {
  test(`parseDataAttr validates that ${key} is a boolean`, async () => {
    await rejects(
      () => parseDataAttr(JSON.stringify({ [key]: 'true' })),
      `data-fb-api.${key} must be a boolean.`
    );
    await rejects(
      () => parseDataAttr(JSON.stringify({ [key]: 1 })),
      `data-fb-api.${key} must be a boolean.`
    );
  });

  test(`parseDataAttr accepts true and false for ${key}`, () => {
    assert.deepEqual(parseDataAttr(JSON.stringify({ [key]: true })), { [key]: true });
    assert.deepEqual(parseDataAttr(JSON.stringify({ [key]: false })), { [key]: false });
  });
}

test('parseDataAttr validates that timeoutMs is a positive finite number', async () => {
  await rejects(() => parseDataAttr('{"timeoutMs": -1}'), 'data-fb-api.timeoutMs must be a positive number.');
  await rejects(() => parseDataAttr('{"timeoutMs": 0}'), 'data-fb-api.timeoutMs must be a positive number.');
  await rejects(() => parseDataAttr('{"timeoutMs": "1000"}'), 'data-fb-api.timeoutMs must be a positive number.');
  await rejects(
    () => parseDataAttr(JSON.stringify({ timeoutMs: null })),
    'data-fb-api.timeoutMs must be a positive number.'
  );
});

test('parseDataAttr validates that timeoutMessage is a string', async () => {
  await rejects(
    () => parseDataAttr('{"timeoutMessage": 5}'),
    'data-fb-api.timeoutMessage must be a string.'
  );
});

test('parseDataAttr validates that params is a plain object', async () => {
  await rejects(() => parseDataAttr('{"params": []}'), 'data-fb-api.params must be an object.');
  await rejects(() => parseDataAttr('{"params": "x"}'), 'data-fb-api.params must be an object.');
  await rejects(() => parseDataAttr('{"params": null}'), 'data-fb-api.params must be an object.');
});

test('parseDataAttr accepts a valid params object', () => {
  const input = { params: { id: 1, name: 'x' } };
  assert.deepEqual(parseDataAttr(JSON.stringify(input)), input);
});

test('parseDataAttr validates the loading sub-object structure', async () => {
  await rejects(() => parseDataAttr('{"loading": []}'), 'data-fb-api.loading must be an object.');
  await rejects(() => parseDataAttr('{"loading": "x"}'), 'data-fb-api.loading must be an object.');
  await rejects(() => parseDataAttr('{"loading": null}'), 'data-fb-api.loading must be an object.');
});

for (const field of ['message', 'button', 'target', 'alertClass']) {
  test(`parseDataAttr validates loading.${field} is a string`, async () => {
    await rejects(
      () => parseDataAttr(JSON.stringify({ loading: { [field]: 5 } })),
      `data-fb-api.loading.${field} must be a string.`
    );
  });
}

test('parseDataAttr accepts a fully populated valid loading object', () => {
  const input = { loading: { message: 'm', button: 'b', target: '#t', alertClass: 'c' } };
  assert.deepEqual(parseDataAttr(JSON.stringify(input)), input);
});

test('parseDataAttr validates the modal sub-object structure', async () => {
  await rejects(() => parseDataAttr('{"modal": []}'), 'data-fb-api.modal must be an object.');
  await rejects(() => parseDataAttr('{"modal": "x"}'), 'data-fb-api.modal must be an object.');
  await rejects(() => parseDataAttr('{"modal": null}'), 'data-fb-api.modal must be an object.');
});

test('parseDataAttr requires modal.type to be a string', async () => {
  await rejects(
    () => parseDataAttr('{"modal": {}}'),
    'data-fb-api.modal.type must be a string.'
  );
  await rejects(
    () => parseDataAttr('{"modal": {"type": 1}}'),
    'data-fb-api.modal.type must be a string.'
  );
});

test('parseDataAttr enforces the modal.type enum', async () => {
  await rejects(
    () => parseDataAttr('{"modal": {"type": "unknown"}}'),
    `data-fb-api.modal.type must be one of: ${MODAL_ALLOWED_TYPES.join(', ')}.`
  );
});

test('parseDataAttr requires modal.key for prompt modals', async () => {
  await rejects(
    () => parseDataAttr('{"modal": {"type": "prompt"}}'),
    'data-fb-api.modal.key is required for prompt modals.'
  );
  await rejects(
    () => parseDataAttr('{"modal": {"type": "prompt", "key": 1}}'),
    'data-fb-api.modal.key is required for prompt modals.'
  );
});

test('parseDataAttr accepts prompt modal when key is a string', () => {
  const input = { modal: { type: 'prompt', key: 'reason' } };
  assert.deepEqual(parseDataAttr(JSON.stringify(input)), input);
});

for (const field of ['title', 'content', 'button', 'buttonColor', 'label', 'value', 'key']) {
  test(`parseDataAttr validates modal.${field} is a string when present`, async () => {
    const payload = field === 'key'
      ? { modal: { type: 'confirm', [field]: 1 } }
      : { modal: { type: 'confirm', [field]: 1 } };
    await rejects(
      () => parseDataAttr(JSON.stringify(payload)),
      `data-fb-api.modal.${field} must be a string.`
    );
  });
}

test('parseDataAttr accepts all three modal types when properly shaped', () => {
  for (const type of MODAL_ALLOWED_TYPES) {
    const input = { modal: { type, ...(type === 'prompt' ? { key: 'k' } : {}) } };
    assert.deepEqual(parseDataAttr(JSON.stringify(input)), input);
  }
});

test('golden path: a fully populated valid data-fb-api value round-trips unchanged', () => {
  const input = {
    href: '/api/admin/example',
    type: 'danger',
    endpoint: 'admin/example',
    callback: 'handleResult',
    message: 'Saved',
    redirect: '/admin',
    reload: true,
    preventNavigation: false,
    timeoutMs: 45000,
    timeoutMessage: 'Timed out',
    params: { id: 7, label: 'x' },
    loading: { message: 'Working', button: 'Wait', target: '#card', alertClass: 'alert-warning' },
    modal: {
      type: 'prompt',
      title: 'Confirm',
      content: 'Are you sure?',
      button: 'Go',
      buttonColor: 'danger',
      label: 'Reason',
      value: 'default',
      key: 'reason',
    },
  };
  assert.deepEqual(parseDataAttr(JSON.stringify(input)), input);
});

test('TOP_LEVEL_SCHEMA covers exactly the documented top-level fields', () => {
  assert.deepEqual(
    Object.keys(TOP_LEVEL_SCHEMA).sort(),
    ['callback', 'endpoint', 'href', 'message', 'params', 'preventNavigation', 'redirect', 'reload', 'timeoutMessage', 'timeoutMs', 'type']
  );
});

test('validators are usable in isolation', () => {
  assertString('ok', 'href');
  assertBoolean(true, 'reload');
  assertPositiveNumber(1, 'timeoutMs');
  assertPlainObject({}, 'params');

  assert.throws(() => assertString(1, 'href'), /data-fb-api\.href must be a string\./);
  assert.throws(() => assertBoolean('x', 'reload'), /data-fb-api\.reload must be a boolean\./);
  assert.throws(() => assertPositiveNumber(0, 'timeoutMs'), /data-fb-api\.timeoutMs must be a positive number\./);
  assert.throws(() => assertPlainObject([], 'params'), /data-fb-api\.params must be an object\./);
});

test('validateLoading and validateModal can be invoked directly', () => {
  validateLoading({ message: 'm' });
  validateModal({ type: 'confirm', title: 't' });

  assert.throws(() => validateLoading(null), /data-fb-api\.loading must be an object\./);
  assert.throws(() => validateModal({ type: 'nope' }), /must be one of:/);
});

test('parseDataAttr ignores unknown keys (forward-compatible with future schema additions)', () => {
  const input = { href: '/x', futureField: 'whatever', nested: { a: 1 } };
  assert.deepEqual(parseDataAttr(JSON.stringify(input)), input);
});
