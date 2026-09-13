import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import { themeFromHref } from '../theme-toggle.mts';

const base = 'https://example.test/client/profile';

describe('themeFromHref', () => {
  test('extracts a valid theme from the query string', () => {
    assert.equal(themeFromHref('?theme=dark', base), 'dark');
    assert.equal(themeFromHref('?theme=light', base), 'light');
    assert.equal(themeFromHref('https://example.test/?theme=dark', base), 'dark');
  });

  test('rejects missing, empty, and invalid themes', () => {
    assert.equal(themeFromHref('?theme=', base), null);
    assert.equal(themeFromHref('?other=dark', base), null);
    assert.equal(themeFromHref('?theme=sepia', base), null);
    assert.equal(themeFromHref('?theme=Dark', base), null);
    assert.equal(themeFromHref('', base), null);
  });

  test('returns null for invalid href values', () => {
    assert.equal(themeFromHref('https://', base), null);
  });
});
