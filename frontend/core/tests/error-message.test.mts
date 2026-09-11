import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import { errorMessage } from '../error-message.mts';

describe('errorMessage', () => {
  test('prefers message over code over fallback', () => {
    assert.equal(errorMessage({ message: 'Boom', code: 'boom_error' }), 'Boom');
    assert.equal(errorMessage({ code: 'boom_error' }), 'boom_error');
    assert.equal(errorMessage({}), 'An unexpected error occurred');
  });

  test('passes strings through and falls back for anything else', () => {
    assert.equal(errorMessage('plain failure'), 'plain failure');
    assert.equal(errorMessage(null), 'An unexpected error occurred');
    assert.equal(errorMessage(undefined), 'An unexpected error occurred');
    assert.equal(errorMessage(42, 'custom fallback'), 'custom fallback');
  });

  test('honours a custom fallback', () => {
    assert.equal(errorMessage({}, 'custom fallback'), 'custom fallback');
    assert.equal(errorMessage({ message: '', code: '' }, 'custom fallback'), 'custom fallback');
  });
});
