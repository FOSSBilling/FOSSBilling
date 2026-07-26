import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import { formatCurrencyAmount } from '../currency-format.mts';

describe('currency formatting', () => {
  test('uses Intl currency formatting by default', () => {
    assert.equal(
      formatCurrencyAmount(1234, { code: 'USD' }, 'en-US'),
      '$1,234.00',
    );
  });

  test('applies a fraction digit override without replacing the currency display', () => {
    assert.equal(
      formatCurrencyAmount(1234.5, { code: 'THB', fraction_digits: 0 }, 'th-TH'),
      '฿1,235',
    );
  });

  test('applies a plain-text amount pattern using locale-aware decimal formatting', () => {
    assert.equal(
      formatCurrencyAmount(1234, {
        code: 'THB',
        format_pattern: '{amount} บาท',
        fraction_digits: 0,
      }, 'th-TH'),
      '1,234 บาท',
    );
  });

  test('uses currency minor units for a pattern without a fraction override', () => {
    assert.equal(
      formatCurrencyAmount(1234, {
        code: 'JPY',
        format_pattern: '{amount} 円',
      }, 'ja-JP'),
      '1,234 円',
    );
  });

  test('keeps the locale-aware minus sign inside the amount', () => {
    assert.equal(
      formatCurrencyAmount(-1234, {
        code: 'THB',
        format_pattern: '{amount} บาท',
        fraction_digits: 0,
      }, 'en-US'),
      '-1,234 บาท',
    );
  });
});
