import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import {
  buildHeaders,
  buildRequestBody,
  injectCSRFToken,
  interpretResponse,
  normalizeApiError,
  parseResponseBody,
  validateHttpResponse,
} from '../api-helpers.mjs';

describe('API request helpers', () => {
  test('injects a missing CSRF token without replacing an existing one', () => {
    const params = {};
    assert.equal(injectCSRFToken(params, 'token'), params);
    assert.equal(params.CSRFToken, 'token');

    const existing = { CSRFToken: 'existing' };
    injectCSRFToken(existing, 'token');
    assert.equal(existing.CSRFToken, 'existing');

    const form = new FormData();
    injectCSRFToken(form, 'token');
    assert.equal(form.get('CSRFToken'), 'token');
  });

  test('puts GET parameters in the URL', () => {
    const url = new URL('https://example.test/api');
    assert.deepEqual(buildRequestBody('GET', { id: 1, CSRFToken: 'secret' }, url), { body: null, isFormData: false });
    assert.equal(url.searchParams.get('id'), '1');
    assert.equal(url.searchParams.has('CSRFToken'), false);

    const form = new FormData();
    form.append('name', 'Ada');
    form.append('CSRFToken', 'secret');
    const formUrl = new URL('https://example.test/api');
    assert.equal(buildRequestBody('GET', form, formUrl).body, null);
    assert.equal(formUrl.searchParams.get('name'), 'Ada');
    assert.equal(formUrl.searchParams.has('CSRFToken'), false);

    const stringUrl = new URL('https://example.test/api');
    buildRequestBody('GET', 'name=Ada&CSRFToken=secret', stringUrl);
    assert.equal(stringUrl.search, '?name=Ada');
  });

  test('builds mutation request bodies', () => {
    const url = new URL('https://example.test/api');
    assert.equal(buildRequestBody('POST', { id: 1 }, url).body, '{"id":1}');
    assert.equal(buildRequestBody('PATCH', 'raw', url).body, 'raw');

    const form = new FormData();
    assert.equal(buildRequestBody('DELETE', form, url).body, form);
  });

  test('builds same-origin JSON headers', () => {
    const url = new URL('https://example.test/api');
    assert.deepEqual(buildHeaders({
      url,
      body: '{}',
      isFormData: false,
      csrfToken: 'token',
      origin: url.origin,
    }), {
      Accept: 'application/json',
      'X-CSRF-Token': 'token',
      'X-Requested-With': 'XMLHttpRequest',
      'Content-Type': 'application/json',
    });

    const crossOrigin = buildHeaders({
      url,
      body: new FormData(),
      isFormData: true,
      csrfToken: null,
      origin: 'https://other.test',
    });
    assert.equal(crossOrigin['X-Requested-With'], undefined);
    assert.equal(crossOrigin['X-CSRF-Token'], undefined);
    assert.equal(crossOrigin['Content-Type'], undefined);
  });
});

describe('API response helpers', () => {
  test('parses JSON, empty, and invalid response bodies', async () => {
    assert.deepEqual(await parseResponseBody({ status: 204 }), { payload: null, rawText: '' });
    assert.deepEqual(
      await parseResponseBody({ status: 200, text: async () => '{"result":1}' }),
      { payload: { result: 1 }, rawText: '{"result":1}' },
    );
    assert.deepEqual(
      await parseResponseBody({ status: 200, text: async () => '<html>' }),
      { payload: null, rawText: '<html>' },
    );
  });

  test('validates HTTP responses', () => {
    const parsed = { payload: { result: 1 }, rawText: '{"result":1}' };
    assert.equal(validateHttpResponse({ ok: true }, parsed), parsed.payload);
    assert.throws(
      () => validateHttpResponse(
        { ok: false, status: 422, statusText: 'Invalid' },
        { payload: { error: { message: 'Bad input', code: 'bad_input' } }, rawText: 'body' },
      ),
      (error) => error.message === 'Bad input' && error.code === 'bad_input' && error.status === 422,
    );
    assert.throws(
      () => validateHttpResponse({ ok: true }, { payload: null, rawText: '<html>' }),
      /non-JSON response/,
    );
  });

  test('returns results and throws API errors', () => {
    assert.equal(interpretResponse(null), null);
    assert.equal(interpretResponse({ result: 0 }), 0);
    assert.throws(
      () => interpretResponse({ error: { message: 'Denied', code: 'denied' } }),
      (error) => error.message === 'Denied' && error.code === 'denied',
    );
  });

  test('normalizes timeout, network, and generic errors', () => {
    assert.deepEqual(
      normalizeApiError({ name: 'AbortError' }, { timeoutMs: 5000 }),
      { message: 'Request timed out after 5 seconds', code: 'timeout_error' },
    );
    assert.deepEqual(
      normalizeApiError({ name: 'TypeError', message: 'NetworkError failed' }, {}),
      { message: 'Network connection error', code: 'network_error' },
    );
    assert.deepEqual(
      normalizeApiError({ name: 'TypeError', message: 'Failed to fetch' }, {}),
      { message: 'Network connection error', code: 'network_error' },
    );
    assert.deepEqual(
      normalizeApiError({ name: 'TypeError', message: 'Load failed' }, {}),
      { message: 'Network connection error', code: 'network_error' },
    );
    assert.deepEqual(
      normalizeApiError({ message: 'Denied', code: 'denied' }, {}),
      { message: 'Denied', code: 'denied' },
    );
  });
});
