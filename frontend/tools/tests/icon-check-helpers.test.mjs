import assert from 'node:assert/strict';
import { describe, test } from 'node:test';

import {
  computeIconErrors,
  extractIconReferencesFromContent,
  parseManifest,
} from '../icon-check-helpers.mjs';

describe('icon manifest helpers', () => {
  test('normalizes manifest entries and tracks dynamic icons', () => {
    const data = parseManifest({
      defaultVariant: 'filled',
      icons: ['home', { name: 'settings', variant: 'outline', dynamic: true }],
    });

    assert.deepEqual(data.manifestIcons, [
      { name: 'home', variant: 'filled', dynamic: false },
      { name: 'settings', variant: 'outline', dynamic: true },
    ]);
    assert.deepEqual([...data.manifestNames], ['home', 'settings']);
    assert.deepEqual([...data.dynamicManifestNames], ['settings']);
  });

  test('extracts modern and legacy references', () => {
    const result = extractIconReferencesFromContent(`
      <use href="#icon-home"></use>
      <use xlink:href="/icons-sprite.svg#icon-user"></use>
    `);

    assert.deepEqual(result.references, ['icon-home', 'icon-user']);
    assert.equal(result.hasLegacy, true);
    assert.equal(result.hasExternalSprite, true);
  });

  test('reports manifest and reference errors', () => {
    const manifestData = parseManifest({
      icons: [
        { name: 'filled', variant: 'filled' },
        { name: 'dynamic', dynamic: true },
        'unused',
      ],
    });
    const errors = computeIconErrors(
      { code: 'test', disallowFilled: true },
      manifestData,
      {
        referencedIcons: new Set(['missing', 'shared']),
        legacyReferences: ['template.twig', 'template.twig'],
        externalSpriteReferences: ['other.twig'],
      },
      new Set(['undocumented', 'shared']),
    );

    assert.deepEqual(errors, [
      'test: "filled" requests the filled variant, but this theme styles icons as outline SVGs.',
      'test: legacy xlink:href references remain in template.twig',
      'test: external sprite references remain in other.twig',
      'test: missing manifest icons: missing, shared, undocumented',
      'test: unused manifest icons: filled, unused',
      'test: dynamic icons should be marked in the manifest: shared, undocumented',
    ]);
  });

  test('accepts a consistent manifest', () => {
    const manifestData = parseManifest({ icons: ['home', { name: 'dynamic', dynamic: true }] });
    assert.deepEqual(computeIconErrors(
      { code: 'test' },
      manifestData,
      { referencedIcons: new Set(['home']), legacyReferences: [], externalSpriteReferences: [] },
      new Set(['dynamic']),
    ), []);
  });
});
