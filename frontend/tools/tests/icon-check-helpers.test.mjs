import { test } from 'node:test';
import assert from 'node:assert/strict';

import { normalizeIconEntry, parseManifest, extractIconReferencesFromContent, computeIconErrors } from '../icon-check-helpers.mjs';

const mockTheme = (overrides = {}) => ({
  code: 'test',
  area: 'admin',
  disallowFilled: false,
  dynamicNavigation: false,
  ...overrides,
});

const mockManifestData = (overrides = {}) => {
  const manifestIcons = overrides.manifestIcons ?? [
    { name: 'home', variant: 'outline', dynamic: false },
    { name: 'settings', variant: 'outline', dynamic: true },
  ];
  const manifestNames = overrides.manifestNames ?? new Set(manifestIcons.map((i) => i.name));
  const declaredDynamicManifestNames = overrides.declaredDynamicManifestNames ?? new Set(manifestIcons.filter((i) => i.dynamic).map((i) => i.name));
  const dynamicManifestNames = overrides.dynamicManifestNames ?? new Set(declaredDynamicManifestNames);

  return { manifestIcons, manifestNames, declaredDynamicManifestNames, dynamicManifestNames };
};

const mockScanData = (overrides = {}) => ({
  referencedIcons: overrides.referencedIcons ?? new Set(),
  legacyReferences: overrides.legacyReferences ?? [],
  externalSpriteReferences: overrides.externalSpriteReferences ?? [],
});

test.describe('normalizeIconEntry', () => {
  test('string entry uses defaultVariant', () => {
    assert.deepEqual(normalizeIconEntry('foo', 'outline'), { name: 'foo', variant: 'outline', dynamic: false });
  });

  test('object entry preserves explicit variant', () => {
    assert.deepEqual(
      normalizeIconEntry({ name: 'bar', variant: 'filled' }, 'outline'),
      { name: 'bar', variant: 'filled', dynamic: false },
    );
  });

  test('object entry without variant falls back to defaultVariant', () => {
    assert.deepEqual(
      normalizeIconEntry({ name: 'bar' }, 'outline'),
      { name: 'bar', variant: 'outline', dynamic: false },
    );
  });

  test('object entry with dynamic flag', () => {
    assert.deepEqual(
      normalizeIconEntry({ name: 'bar', dynamic: true }, 'outline'),
      { name: 'bar', variant: 'outline', dynamic: true },
    );
  });

  test('object entry with falsy dynamic defaults to false', () => {
    assert.deepEqual(
      normalizeIconEntry({ name: 'bar', dynamic: false }, 'outline'),
      { name: 'bar', variant: 'outline', dynamic: false },
    );
  });

  test('falsy variant in object entry falls back to defaultVariant', () => {
    assert.deepEqual(
      normalizeIconEntry({ name: 'bar', variant: '' }, 'solid'),
      { name: 'bar', variant: 'solid', dynamic: false },
    );
  });
});

test.describe('parseManifest', () => {
  test('defaultVariant defaults to outline when not specified', () => {
    const data = parseManifest({ icons: ['foo'] });
    assert.equal(data.manifestIcons[0].variant, 'outline');
  });

  test('custom defaultVariant is used', () => {
    const data = parseManifest({ defaultVariant: 'filled', icons: ['foo'] });
    assert.equal(data.manifestIcons[0].variant, 'filled');
  });

  test('empty icons array produces empty sets', () => {
    const data = parseManifest({ icons: [] });
    assert.equal(data.manifestIcons.length, 0);
    assert.equal(data.manifestNames.size, 0);
    assert.equal(data.declaredDynamicManifestNames.size, 0);
    assert.equal(data.dynamicManifestNames.size, 0);
  });

  test('manifestNames contains all icon names', () => {
    const data = parseManifest({ icons: ['a', 'b', 'c'] });
    assert.deepEqual([...data.manifestNames], ['a', 'b', 'c']);
  });

  test('declaredDynamicManifestNames only includes dynamic: true icons', () => {
    const data = parseManifest({
      icons: [
        'static-one',
        { name: 'dyn-one', dynamic: true },
        { name: 'static-two', dynamic: false },
        { name: 'dyn-two', dynamic: true },
      ],
    });
    assert.deepEqual([...data.declaredDynamicManifestNames], ['dyn-one', 'dyn-two']);
  });

  test('dynamicManifestNames is a separate Set with same contents as declaredDynamicManifestNames', () => {
    const data = parseManifest({
      icons: [{ name: 'dyn', dynamic: true }],
    });
    assert.deepEqual([...data.dynamicManifestNames], [...data.declaredDynamicManifestNames]);
    assert.notEqual(data.dynamicManifestNames, data.declaredDynamicManifestNames);
  });

  test('mixed string and object entries are normalized consistently', () => {
    const data = parseManifest({
      defaultVariant: 'solid',
      icons: ['str', { name: 'obj', variant: 'outline' }],
    });
    assert.equal(data.manifestIcons[0].name, 'str');
    assert.equal(data.manifestIcons[0].variant, 'solid');
    assert.equal(data.manifestIcons[1].name, 'obj');
    assert.equal(data.manifestIcons[1].variant, 'outline');
  });
});

test.describe('extractIconReferencesFromContent', () => {
  test('empty content returns no references', () => {
    const result = extractIconReferencesFromContent('');
    assert.deepEqual(result.references, []);
    assert.equal(result.hasLegacy, false);
    assert.equal(result.hasExternalSprite, false);
  });

  test('plain text with no use tags returns empty', () => {
    const result = extractIconReferencesFromContent('<div>hello</div>');
    assert.deepEqual(result.references, []);
  });

  test('extracts icon from modern href syntax', () => {
    const result = extractIconReferencesFromContent('<use href="#home" />');
    assert.deepEqual(result.references, ['home']);
  });

  test('extracts icon from legacy xlink:href syntax', () => {
    const result = extractIconReferencesFromContent('<use xlink:href="#home" />');
    assert.deepEqual(result.references, ['home']);
    assert.equal(result.hasLegacy, true);
  });

  test('extracts icon with path prefix before hash', () => {
    const result = extractIconReferencesFromContent('<use href="/assets/sprite.svg#home" />');
    assert.deepEqual(result.references, ['home']);
  });

  test('extracts icon with empty path prefix (just hash)', () => {
    const result = extractIconReferencesFromContent('<use href="#home" />');
    assert.deepEqual(result.references, ['home']);
  });

  test('extracts multiple icons from content', () => {
    const content = '<use href="#home" /><use href="#settings" /><use xlink:href="#logout" />';
    const result = extractIconReferencesFromContent(content);
    assert.deepEqual(result.references, ['home', 'settings', 'logout']);
  });

  test('preserves duplicate references (caller deduplicates via Set)', () => {
    const content = '<use href="#home" /><use href="#home" />';
    const result = extractIconReferencesFromContent(content);
    assert.deepEqual(result.references, ['home', 'home']);
  });

  test('handles icon names with dashes', () => {
    const result = extractIconReferencesFromContent('<use href="#arrow-left" />');
    assert.deepEqual(result.references, ['arrow-left']);
  });

  test('handles icon names with underscores', () => {
    const result = extractIconReferencesFromContent('<use href="#arrow_left" />');
    assert.deepEqual(result.references, ['arrow_left']);
  });

  test('handles icon names with numbers', () => {
    const result = extractIconReferencesFromContent('<use href="#icon3d" />');
    assert.deepEqual(result.references, ['icon3d']);
  });

  test('use tag without href attribute is skipped', () => {
    const result = extractIconReferencesFromContent('<use class="something" />');
    assert.deepEqual(result.references, []);
  });

  test('hasLegacy is true when xlink:href substring is present anywhere', () => {
    const result = extractIconReferencesFromContent('<!-- uses xlink:href -->');
    assert.equal(result.hasLegacy, true);
  });

  test('hasLegacy is false for modern href only', () => {
    const result = extractIconReferencesFromContent('<use href="#home" />');
    assert.equal(result.hasLegacy, false);
  });

  test('hasExternalSprite is true when icons-sprite.svg# is present', () => {
    const result = extractIconReferencesFromContent('<img src="icons-sprite.svg#home" />');
    assert.equal(result.hasExternalSprite, true);
  });

  test('hasExternalSprite is false when not present', () => {
    const result = extractIconReferencesFromContent('<use href="#home" />');
    assert.equal(result.hasExternalSprite, false);
  });

  test('single use tag with both href and xlink:href', () => {
    const content = '<use href="#home" xlink:href="#home" />';
    const result = extractIconReferencesFromContent(content);
    assert.deepEqual(result.references, ['home']);
    assert.equal(result.hasLegacy, true);
  });
});

test.describe('computeIconErrors', () => {
  test('no issues returns empty array', () => {
    const theme = mockTheme();
    const md = mockManifestData({ manifestIcons: [{ name: 'home', variant: 'outline', dynamic: false }] });
    const sd = mockScanData({ referencedIcons: new Set(['home']) });
    const errors = computeIconErrors(theme, md, sd, new Set());
    assert.deepEqual(errors, []);
  });

  test('disallowFilled + filled variant produces error', () => {
    const theme = mockTheme({ disallowFilled: true });
    const md = mockManifestData({
      manifestIcons: [{ name: 'x', variant: 'filled', dynamic: false }],
    });
    const sd = mockScanData({ referencedIcons: new Set(['x']) });
    const errors = computeIconErrors(theme, md, sd, new Set());
    assert.equal(errors.length, 1);
    assert.match(errors[0], /"x" requests the filled variant/);
  });

  test('disallowFilled + outline variant produces no variant error', () => {
    const theme = mockTheme({ disallowFilled: true });
    const md = mockManifestData({
      manifestIcons: [{ name: 'x', variant: 'outline', dynamic: false }],
      manifestNames: new Set(['x']),
    });
    const sd = mockScanData({ referencedIcons: new Set(['x']) });
    const errors = computeIconErrors(theme, md, sd, new Set());
    assert.deepEqual(errors, []);
  });

  test('no disallowFilled constraint allows filled variants', () => {
    const theme = mockTheme();
    const md = mockManifestData({
      manifestIcons: [{ name: 'x', variant: 'filled', dynamic: false }],
      manifestNames: new Set(['x']),
    });
    const sd = mockScanData({ referencedIcons: new Set(['x']) });
    const errors = computeIconErrors(theme, md, sd, new Set());
    assert.deepEqual(errors, []);
  });

  test('legacy references produce error', () => {
    const theme = mockTheme();
    const md = mockManifestData({ manifestIcons: [], manifestNames: new Set() });
    const sd = mockScanData({ legacyReferences: ['src/foo.twig', 'src/bar.twig'] });
    const errors = computeIconErrors(theme, md, sd, new Set());
    assert.equal(errors.length, 1);
    assert.match(errors[0], /legacy xlink:href references remain in src\/foo\.twig, src\/bar\.twig/);
  });

  test('legacy references are deduplicated', () => {
    const theme = mockTheme();
    const md = mockManifestData();
    const sd = mockScanData({ legacyReferences: ['src/foo.twig', 'src/foo.twig'] });
    const errors = computeIconErrors(theme, md, sd, new Set());
    assert.match(errors[0], /src\/foo\.twig/);
    assert.doesNotMatch(errors[0], /src\/foo\.twig.*src\/foo\.twig/);
  });

  test('external sprite references produce error', () => {
    const theme = mockTheme();
    const md = mockManifestData({ manifestIcons: [], manifestNames: new Set() });
    const sd = mockScanData({ externalSpriteReferences: ['src/baz.twig'] });
    const errors = computeIconErrors(theme, md, sd, new Set());
    assert.equal(errors.length, 1);
    assert.match(errors[0], /external sprite references remain in src\/baz\.twig/);
  });

  test('missing icons (referenced but not in manifest) produce error sorted alphabetically', () => {
    const theme = mockTheme();
    const md = mockManifestData({
      manifestIcons: [{ name: 'home', variant: 'outline', dynamic: false }],
      manifestNames: new Set(['home']),
    });
    const sd = mockScanData({ referencedIcons: new Set(['zebra', 'home', 'apple']) });
    const errors = computeIconErrors(theme, md, sd, new Set());
    assert.equal(errors.length, 1);
    assert.match(errors[0], /missing manifest icons: apple, zebra/);
  });

  test('missing includes dynamic icons not in manifest', () => {
    const theme = mockTheme();
    const md = mockManifestData({
      manifestIcons: [{ name: 'home', variant: 'outline', dynamic: false }],
      manifestNames: new Set(['home']),
    });
    const sd = mockScanData({ referencedIcons: new Set(['home']) });
    const errors = computeIconErrors(theme, md, sd, new Set(['mystery']));
    assert.match(errors[0], /missing manifest icons: mystery/);
  });

  test('unused icons (in manifest but not referenced) produce error', () => {
    const theme = mockTheme();
    const md = mockManifestData({
      manifestIcons: [{ name: 'home', variant: 'outline', dynamic: false }],
      manifestNames: new Set(['home']),
    });
    const sd = mockScanData({ referencedIcons: new Set() });
    const errors = computeIconErrors(theme, md, sd, new Set());
    assert.match(errors[0], /unused manifest icons: home/);
  });

  test('unused does not flag dynamic manifest icons', () => {
    const theme = mockTheme();
    const md = mockManifestData({
      manifestIcons: [{ name: 'dyn', variant: 'outline', dynamic: true }],
      manifestNames: new Set(['dyn']),
      dynamicManifestNames: new Set(['dyn']),
    });
    const sd = mockScanData({ referencedIcons: new Set() });
    const errors = computeIconErrors(theme, md, sd, new Set());
    assert.deepEqual(errors, []);
  });

  test('undocumented dynamic icons produce error', () => {
    const theme = mockTheme();
    const md = mockManifestData({
      manifestIcons: [{ name: 'undocumented', variant: 'outline', dynamic: false }],
      declaredDynamicManifestNames: new Set(),
    });
    const sd = mockScanData({ referencedIcons: new Set(['undocumented']) });
    const errors = computeIconErrors(theme, md, sd, new Set(['undocumented']));
    assert.equal(errors.length, 1);
    assert.match(errors[0], /dynamic icons should be marked in the manifest: undocumented/);
  });

  test('declared dynamic icons do not trigger undocumented error', () => {
    const theme = mockTheme();
    const md = mockManifestData({
      manifestIcons: [{ name: 'declared', variant: 'outline', dynamic: true }],
      declaredDynamicManifestNames: new Set(['declared']),
    });
    const sd = mockScanData({ referencedIcons: new Set(['declared']) });
    const errors = computeIconErrors(theme, md, sd, new Set(['declared']));
    assert.deepEqual(errors, []);
  });

  test('multiple error types at once', () => {
    const theme = mockTheme({ disallowFilled: true });
    const md = mockManifestData({
      manifestIcons: [
        { name: 'filled-icon', variant: 'filled', dynamic: false },
        { name: 'unused-icon', variant: 'outline', dynamic: false },
      ],
      manifestNames: new Set(['filled-icon', 'unused-icon']),
    });
    const sd = mockScanData({
      referencedIcons: new Set(['missing-icon']),
      legacyReferences: ['legacy.twig'],
      externalSpriteReferences: ['sprite.twig'],
    });
    const dynamicIcons = new Set(['undocumented-dyn']);
    const errors = computeIconErrors(theme, md, sd, dynamicIcons);
    assert.ok(errors.length >= 5);
    const joined = errors.join('\n');
    assert.ok(joined.includes('filled variant'));
    assert.ok(joined.includes('legacy xlink:href'));
    assert.ok(joined.includes('external sprite'));
    assert.ok(joined.includes('missing manifest icons'));
    assert.ok(joined.includes('unused manifest icons'));
    assert.ok(joined.includes('dynamic icons should be marked'));
  });

  test('all error messages include theme code prefix', () => {
    const theme = mockTheme({ code: 'huraga' });
    const md = mockManifestData();
    const sd = mockScanData({ legacyReferences: ['x.twig'] });
    const errors = computeIconErrors(theme, md, sd, new Set());
    for (const e of errors) {
      assert.ok(e.startsWith('huraga: '), `expected prefix "huraga: " in "${e}"`);
    }
  });
});
