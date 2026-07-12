function normalizeIconEntry(entry, defaultVariant) {
  if (typeof entry === 'string') {
    return {
      name: entry,
      variant: defaultVariant,
      dynamic: false,
    };
  }

  return {
    name: entry.name,
    variant: entry.variant || defaultVariant,
    dynamic: entry.dynamic || false,
  };
}

export function parseManifest(manifest) {
  const defaultVariant = manifest.defaultVariant || 'outline';
  const manifestIcons = manifest.icons.map((entry) => normalizeIconEntry(entry, defaultVariant));
  const manifestNames = new Set(manifestIcons.map((icon) => icon.name));
  const dynamicManifestNames = new Set(manifestIcons.filter((icon) => icon.dynamic).map((icon) => icon.name));

  return { manifestIcons, manifestNames, dynamicManifestNames };
}

const ICON_REF_REGEX = /<use\b[^>]*\b(?:href|xlink:href)=["'](?:[^#"']*)#([A-Za-z0-9_-]+)["']/g;

export function extractIconReferencesFromContent(content) {
  const references = [];

  for (const match of content.matchAll(ICON_REF_REGEX)) {
    if (match[1]) {
      references.push(match[1]);
    }
  }

  return {
    references,
    hasLegacy: content.includes('xlink:href'),
    hasExternalSprite: content.includes('icons-sprite.svg#'),
  };
}

export function computeIconErrors(theme, manifestData, scanData, dynamicIcons) {
  const errors = [];

  for (const icon of manifestData.manifestIcons) {
    if (theme.disallowFilled && icon.variant === 'filled') {
      errors.push(`${theme.code}: "${icon.name}" requests the filled variant, but this theme styles icons as outline SVGs.`);
    }
  }

  const missing = [...new Set([...scanData.referencedIcons, ...dynamicIcons])].filter((icon) => !manifestData.manifestNames.has(icon)).sort();
  const unused = [...manifestData.manifestNames].filter((icon) => !scanData.referencedIcons.has(icon) && !manifestData.dynamicManifestNames.has(icon)).sort();
  const undocumentedDynamic = [...dynamicIcons].filter((icon) => !manifestData.dynamicManifestNames.has(icon)).sort();

  if (scanData.legacyReferences.length > 0) {
    errors.push(`${theme.code}: legacy xlink:href references remain in ${[...new Set(scanData.legacyReferences)].join(', ')}`);
  }

  if (scanData.externalSpriteReferences.length > 0) {
    errors.push(`${theme.code}: external sprite references remain in ${[...new Set(scanData.externalSpriteReferences)].join(', ')}`);
  }

  if (missing.length > 0) {
    errors.push(`${theme.code}: missing manifest icons: ${missing.join(', ')}`);
  }

  if (unused.length > 0) {
    errors.push(`${theme.code}: unused manifest icons: ${unused.join(', ')}`);
  }

  if (undocumentedDynamic.length > 0) {
    errors.push(`${theme.code}: dynamic icons should be marked in the manifest: ${undocumentedDynamic.join(', ')}`);
  }

  return errors;
}
