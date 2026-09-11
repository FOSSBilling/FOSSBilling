import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import { tabIdFromHash } from '../tabs.mts';

describe('tabIdFromHash', () => {
  test('strips the leading hash', () => {
    assert.equal(tabIdFromHash('#general'), 'general');
  });

  test('returns an empty id for empty or hashless locations', () => {
    assert.equal(tabIdFromHash(''), '');
    assert.equal(tabIdFromHash('#'), '');
    assert.equal(tabIdFromHash('general'), '');
  });
});
