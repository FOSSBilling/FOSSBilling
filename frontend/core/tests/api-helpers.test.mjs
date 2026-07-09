import { test, describe } from 'node:test';
import assert from 'node:assert/strict';
import {
  injectCSRFToken,
  buildRequestBody,
  buildHeaders,
  parseResponseBody,
  validateHttpResponse,
  interpretResponse,
  normalizeApiError,
} from '../api-helpers.mjs';

const mockResponse = (status, body, { statusText = '' } = {}) => ({
  status,
  ok: status >= 200 && status < 300,
  statusText,
  text: async () => body,
});

const mockUrl = (origin = 'https://app.test') => new URL(`${origin}/api/admin/test`);

describe('injectCSRFToken', () => {
  test('appends CSRFToken to FormData when missing', () => {
    const fd = new FormData();
    injectCSRFToken(fd, 'tok123');
    assert.equal(fd.get('CSRFToken'), 'tok123');
  });

  test('does not overwrite CSRFToken in FormData when present', () => {
    const fd = new FormData();
    fd.append('CSRFToken', 'existing');
    injectCSRFToken(fd, 'tok123');
    assert.equal(fd.get('CSRFToken'), 'existing');
  });

  test('assigns CSRFToken on plain object when missing', () => {
    const obj = { foo: 'bar' };
    injectCSRFToken(obj, 'tok456');
    assert.equal(obj.CSRFToken, 'tok456');
    assert.equal(obj.foo, 'bar');
  });

  test('does not overwrite CSRFToken on plain object when present', () => {
    const obj = { CSRFToken: 'existing' };
    injectCSRFToken(obj, 'tok456');
    assert.equal(obj.CSRFToken, 'existing');
  });

  test('returns null unchanged', () => {
    assert.equal(injectCSRFToken(null, 'tok'), null);
  });

  test('returns undefined unchanged', () => {
    assert.equal(injectCSRFToken(undefined, 'tok'), undefined);
  });

  test('returns string params unchanged', () => {
    assert.equal(injectCSRFToken('raw-string', 'tok'), 'raw-string');
  });

  test('does not set CSRFToken on falsy empty string token for FormData', () => {
    const fd = new FormData();
    injectCSRFToken(fd, '');
    assert.equal(fd.get('CSRFToken'), '');
  });
});

describe('buildRequestBody', () => {
  test('GET with object → params in URL search, body null', () => {
    const url = mockUrl();
    const { body, isFormData } = buildRequestBody('GET', { id: 1, name: 'x' }, url);
    assert.equal(body, null);
    assert.equal(isFormData, false);
    assert.equal(url.searchParams.get('id'), '1');
    assert.equal(url.searchParams.get('name'), 'x');
  });

  test('GET with FormData → params in URL search, body null', () => {
    const url = mockUrl();
    const fd = new FormData();
    fd.append('id', '1');
    const { body, isFormData } = buildRequestBody('GET', fd, url);
    assert.equal(body, null);
    assert.equal(isFormData, true);
    assert.equal(url.searchParams.get('id'), '1');
  });

  test('GET with string → sets url.search', () => {
    const url = mockUrl();
    const { body } = buildRequestBody('GET', 'foo=bar&baz=1', url);
    assert.equal(body, null);
    assert.equal(url.searchParams.get('foo'), 'bar');
    assert.equal(url.searchParams.get('baz'), '1');
  });

  test('GET with null → no URL params, body null', () => {
    const url = mockUrl();
    const { body } = buildRequestBody('GET', null, url);
    assert.equal(body, null);
    assert.equal(url.search, '');
  });

  test('POST with object → body is JSON string', () => {
    const url = mockUrl();
    const { body, isFormData } = buildRequestBody('POST', { id: 1 }, url);
    assert.deepEqual(JSON.parse(body), { id: 1 });
    assert.equal(isFormData, false);
  });

  test('POST with FormData → body is the FormData itself', () => {
    const url = mockUrl();
    const fd = new FormData();
    const { body, isFormData } = buildRequestBody('POST', fd, url);
    assert.equal(body, fd);
    assert.equal(isFormData, true);
  });

  test('POST with string → body is the string as-is', () => {
    const url = mockUrl();
    const { body, isFormData } = buildRequestBody('POST', 'raw-body', url);
    assert.equal(body, 'raw-body');
    assert.equal(isFormData, false);
  });

  test('PUT with object → body is JSON string', () => {
    const url = mockUrl();
    const { body } = buildRequestBody('PUT', { id: 2 }, url);
    assert.deepEqual(JSON.parse(body), { id: 2 });
  });

  test('PATCH with object → body is JSON string', () => {
    const url = mockUrl();
    const { body } = buildRequestBody('PATCH', { id: 3 }, url);
    assert.deepEqual(JSON.parse(body), { id: 3 });
  });

  test('DELETE with object → body is JSON string', () => {
    const url = mockUrl();
    const { body } = buildRequestBody('DELETE', { id: 4 }, url);
    assert.deepEqual(JSON.parse(body), { id: 4 });
  });

  test('POST with null → body is "null" JSON string (JSON.stringify(null))', () => {
    const url = mockUrl();
    const { body } = buildRequestBody('POST', null, url);
    assert.equal(body, 'null');
  });

  test('POST with undefined → body is undefined JSON string', () => {
    const url = mockUrl();
    const { body } = buildRequestBody('POST', undefined, url);
    assert.equal(body, undefined);
  });

  test('method is case-insensitive', () => {
    const url = mockUrl();
    const { body } = buildRequestBody('PoSt', { id: 5 }, url);
    assert.deepEqual(JSON.parse(body), { id: 5 });
  });
});

describe('buildHeaders', () => {
  test('same-origin + JSON body → X-Requested-With and Content-Type', () => {
    const url = mockUrl('https://app.test');
    const headers = buildHeaders({ url, body: '{"id":1}', isFormData: false, csrfToken: 'tok', origin: 'https://app.test' });
    assert.equal(headers['Accept'], 'application/json');
    assert.equal(headers['X-CSRF-Token'], 'tok');
    assert.equal(headers['X-Requested-With'], 'XMLHttpRequest');
    assert.equal(headers['Content-Type'], 'application/json');
  });

  test('cross-origin → no X-Requested-With', () => {
    const url = mockUrl('https://other.test');
    const headers = buildHeaders({ url, body: '{"id":1}', isFormData: false, csrfToken: 'tok', origin: 'https://app.test' });
    assert.equal(headers['X-Requested-With'], undefined);
    assert.equal(headers['Content-Type'], 'application/json');
  });

  test('same-origin + FormData body → X-Requested-With, no Content-Type', () => {
    const url = mockUrl('https://app.test');
    const fd = new FormData();
    const headers = buildHeaders({ url, body: fd, isFormData: true, csrfToken: 'tok', origin: 'https://app.test' });
    assert.equal(headers['X-Requested-With'], 'XMLHttpRequest');
    assert.equal(headers['Content-Type'], undefined);
  });

  test('same-origin + null body → X-Requested-With, no Content-Type', () => {
    const url = mockUrl('https://app.test');
    const headers = buildHeaders({ url, body: null, isFormData: false, csrfToken: 'tok', origin: 'https://app.test' });
    assert.equal(headers['X-Requested-With'], 'XMLHttpRequest');
    assert.equal(headers['Content-Type'], undefined);
  });

  test('empty CSRF token → X-CSRF-Token is empty string', () => {
    const url = mockUrl('https://app.test');
    const headers = buildHeaders({ url, body: null, isFormData: false, csrfToken: '', origin: 'https://app.test' });
    assert.equal(headers['X-CSRF-Token'], '');
  });

  test('Accept header is always application/json', () => {
    const url = mockUrl('https://app.test');
    const headers = buildHeaders({ url, body: null, isFormData: false, csrfToken: '', origin: 'https://app.test' });
    assert.equal(headers['Accept'], 'application/json');
  });
});

describe('parseResponseBody', () => {
  test('204 status → { payload: null, rawText: "" }', async () => {
    const result = await parseResponseBody(mockResponse(204, ''));
    assert.deepEqual(result, { payload: null, rawText: '' });
  });

  test('empty body text → { payload: null, rawText: "" }', async () => {
    const result = await parseResponseBody(mockResponse(200, ''));
    assert.deepEqual(result, { payload: null, rawText: '' });
  });

  test('valid JSON → parsed payload with rawText', async () => {
    const result = await parseResponseBody(mockResponse(200, '{"result":42}'));
    assert.deepEqual(result.payload, { result: 42 });
    assert.equal(result.rawText, '{"result":42}');
  });

  test('invalid JSON → { payload: null, rawText: original }', async () => {
    const result = await parseResponseBody(mockResponse(200, 'not-json'));
    assert.equal(result.payload, null);
    assert.equal(result.rawText, 'not-json');
  });

  test('valid JSON array → parsed as array', async () => {
    const result = await parseResponseBody(mockResponse(200, '[1,2,3]'));
    assert.deepEqual(result.payload, [1, 2, 3]);
  });

  test('valid JSON null literal → payload is null (parsed)', async () => {
    const result = await parseResponseBody(mockResponse(200, 'null'));
    assert.equal(result.payload, null);
    assert.equal(result.rawText, 'null');
  });
});

describe('validateHttpResponse', () => {
  test('!ok without error payload → throws with HTTP error message and code', () => {
    const response = mockResponse(500, '', { statusText: 'Internal Server Error' });
    assert.throws(
      () => validateHttpResponse(response, { payload: null, rawText: '' }),
      (err) => err.message === 'HTTP error 500: Internal Server Error' && err.code === 'http_500'
    );
  });

  test('!ok with error payload → uses API error message and code', () => {
    const response = mockResponse(422, '', { statusText: 'Unprocessable Entity' });
    const parsed = { payload: { error: { message: 'Validation failed', code: 'validation_error' } }, rawText: '' };
    assert.throws(
      () => validateHttpResponse(response, parsed),
      (err) => err.message === 'Validation failed' && err.code === 'validation_error'
    );
  });

  test('!ok error includes .status and .rawBody', () => {
    const response = mockResponse(403, 'forbidden', { statusText: 'Forbidden' });
    const parsed = { payload: null, rawText: 'forbidden' };
    try {
      validateHttpResponse(response, parsed);
      assert.fail('should have thrown');
    } catch (err) {
      assert.equal(err.status, 403);
      assert.equal(err.rawBody, 'forbidden');
    }
  });

  test('ok + rawText + null payload → throws non-JSON error', () => {
    const response = mockResponse(200, 'broken');
    assert.throws(
      () => validateHttpResponse(response, { payload: null, rawText: 'broken' }),
      /Invalid or non-JSON response from server/
    );
  });

  test('ok + valid payload → returns payload', () => {
    const response = mockResponse(200, '{"result":42}');
    const result = validateHttpResponse(response, { payload: { result: 42 }, rawText: '{"result":42}' });
    assert.deepEqual(result, { result: 42 });
  });

  test('ok + empty body (204-style) → returns null payload', () => {
    const response = mockResponse(200, '');
    const result = validateHttpResponse(response, { payload: null, rawText: '' });
    assert.equal(result, null);
  });

  test('!ok falls back to http_ status code when API error has no code', () => {
    const response = mockResponse(404, '', { statusText: 'Not Found' });
    const parsed = { payload: { error: { message: 'Not found' } }, rawText: '' };
    assert.throws(
      () => validateHttpResponse(response, parsed),
      (err) => err.code === 'http_404'
    );
  });
});

describe('interpretResponse', () => {
  test('null → returns null', () => {
    assert.equal(interpretResponse(null), null);
  });

  test('undefined → returns null', () => {
    assert.equal(interpretResponse(undefined), null);
  });

  test('payload with .error → throws with error.message and error.code', () => {
    const payload = { error: { message: 'Access denied', code: 'forbidden' } };
    assert.throws(
      () => interpretResponse(payload),
      (err) => err.message === 'Access denied' && err.code === 'forbidden'
    );
  });

  test('payload.error without message → throws "Unknown API error"', () => {
    const payload = { error: {} };
    assert.throws(
      () => interpretResponse(payload),
      /Unknown API error/
    );
  });

  test('valid payload → returns .result', () => {
    const payload = { result: { id: 1, name: 'test' } };
    assert.deepEqual(interpretResponse(payload), { id: 1, name: 'test' });
  });

  test('payload.result is falsy (0) → returns 0', () => {
    const payload = { result: 0 };
    assert.equal(interpretResponse(payload), 0);
  });

  test('payload.result is null → returns null', () => {
    const payload = { result: null };
    assert.equal(interpretResponse(payload), null);
  });
});

describe('normalizeApiError', () => {
  test('AbortError with timeoutMessage → uses custom message', () => {
    const error = new Error('aborted');
    error.name = 'AbortError';
    const result = normalizeApiError(error, { timeoutMs: 30000, timeoutMessage: 'Custom timeout' });
    assert.equal(result.message, 'Custom timeout');
    assert.equal(result.code, 'timeout_error');
  });

  test('AbortError without timeoutMessage → default message with seconds', () => {
    const error = new Error('aborted');
    error.name = 'AbortError';
    const result = normalizeApiError(error, { timeoutMs: 30000, timeoutMessage: null });
    assert.equal(result.message, 'Request timed out after 30 seconds');
    assert.equal(result.code, 'timeout_error');
  });

  test('AbortError with 5000ms → "5 seconds"', () => {
    const error = new Error('aborted');
    error.name = 'AbortError';
    const result = normalizeApiError(error, { timeoutMs: 5000, timeoutMessage: null });
    assert.equal(result.message, 'Request timed out after 5 seconds');
  });

  test('TypeError with NetworkError → network_error', () => {
    const error = new TypeError('Failed to fetch: NetworkError when attempting to fetch resource');
    const result = normalizeApiError(error, { timeoutMs: 30000, timeoutMessage: null });
    assert.equal(result.message, 'Network connection error');
    assert.equal(result.code, 'network_error');
  });

  test('TypeError without NetworkError → falls through to generic', () => {
    const error = new TypeError('Some other type error');
    const result = normalizeApiError(error, { timeoutMs: 30000, timeoutMessage: null });
    assert.equal(result.code, 'unknown_error');
  });

  test('generic error with code → preserves message and code', () => {
    const error = new Error('Something broke');
    error.code = 'custom_code';
    const result = normalizeApiError(error, { timeoutMs: 30000, timeoutMessage: null });
    assert.equal(result.message, 'Something broke');
    assert.equal(result.code, 'custom_code');
  });

  test('generic error without code → code is unknown_error', () => {
    const error = new Error('Something broke');
    const result = normalizeApiError(error, { timeoutMs: 30000, timeoutMessage: null });
    assert.equal(result.message, 'Something broke');
    assert.equal(result.code, 'unknown_error');
  });

  test('error without message → message is "Unknown error occurred"', () => {
    const error = new Error();
    const result = normalizeApiError(error, { timeoutMs: 30000, timeoutMessage: null });
    assert.equal(result.message, 'Unknown error occurred');
    assert.equal(result.code, 'unknown_error');
  });

  test('HTTP error thrown by validateHttpResponse → preserved via generic branch', () => {
    const error = new Error('HTTP error 500: Internal Server Error');
    error.code = 'http_500';
    error.status = 500;
    error.rawBody = '';
    const result = normalizeApiError(error, { timeoutMs: 30000, timeoutMessage: null });
    assert.equal(result.message, 'HTTP error 500: Internal Server Error');
    assert.equal(result.code, 'http_500');
  });
});
