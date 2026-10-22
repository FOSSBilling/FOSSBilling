import assert from 'node:assert/strict';
import { test } from 'node:test';
import { announceMessage } from '../ui/announcements.ts';

class Region {
  className = '';
  dataset: Record<string, string> = {};
  attrs = new Map<string, string>();
  textContent = '';
  setAttribute(name: string, value: string) { this.attrs.set(name, value); }
}

test('announcements update established regions, repeat identical messages and keep urgency separate', async () => {
  const regions: Region[] = [];
  const updates: (() => void)[] = [];
  const originalTimer = globalThis.setTimeout;
  const originalClear = globalThis.clearTimeout;
  (globalThis as any).document = {
    querySelector: (selector: string) => regions.find(region => selector.includes(`"${region.dataset.clientAnnouncement}"`)),
    createElement: () => new Region(),
    body: {append: (region: Region) => regions.push(region)},
  };
  (globalThis as any).setTimeout = (callback: () => void) => { updates.push(callback); return updates.length; };
  (globalThis as any).clearTimeout = (id: number) => { if (id) updates[id - 1] = () => {}; };
  try {
    announceMessage('Saved <details>.', 'info');
    assert.equal(regions.length, 1);
    assert.equal(regions[0].attrs.get('role'), 'status');
    assert.equal(regions[0].textContent, '');
    updates.shift()!();
    assert.equal(regions[0].textContent, 'Saved <details>.');
    announceMessage('Saved <details>.', 'info');
    assert.equal(regions[0].textContent, '');
    updates.shift()!();
    assert.equal(regions[0].textContent, 'Saved <details>.');
    announceMessage('Please try again.', 'error');
    updates.shift()!();
    assert.equal(regions.length, 2);
    assert.equal(regions[1].attrs.get('role'), 'alert');
    assert.equal(regions[1].attrs.has('aria-live'), false);
    assert.equal(regions[1].attrs.get('aria-atomic'), 'true');
    assert.equal(regions[1].textContent, 'Please try again.');
  } finally {
    globalThis.setTimeout = originalTimer;
    globalThis.clearTimeout = originalClear;
  }
});
