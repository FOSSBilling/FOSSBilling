import assert from 'node:assert/strict';
import { test } from 'node:test';
import {initEmailPreviewSupport, prepareEmailPreview} from '../email-preview.ts';

test('email preview support shares its helper without replacing extension implementations', () => {
  const previous = (globalThis as any).FOSSBilling;
  try {
    (globalThis as any).FOSSBilling = {ui: {}};
    initEmailPreviewSupport();
    assert.equal(FOSSBilling.ui.prepareEmailPreview, prepareEmailPreview);
    const custom = (source: string) => source;
    FOSSBilling.ui.prepareEmailPreview = custom;
    initEmailPreviewSupport();
    assert.equal(FOSSBilling.ui.prepareEmailPreview, custom);
  } finally {
    (globalThis as any).FOSSBilling = previous;
  }
});
