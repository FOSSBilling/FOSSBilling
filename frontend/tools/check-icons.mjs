import { readFile } from 'fs/promises';
import { dirname, extname, join, relative, resolve } from 'path';
import { readdirSync } from 'fs';
import { fileURLToPath } from 'url';

const rootDir = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

const themes = [
  {
    code: 'huraga',
    area: 'client',
    themePath: resolve(rootDir, 'src/themes/huraga'),
    scanPaths: [
      resolve(rootDir, 'src/themes/huraga/html'),
      resolve(rootDir, 'src/themes/huraga/assets'),
      resolve(rootDir, 'src/modules'),
    ],
    disallowFilled: true,
  },
  {
    code: 'admin_default',
    area: 'admin',
    themePath: resolve(rootDir, 'src/themes/admin_default'),
    scanPaths: [
      resolve(rootDir, 'src/themes/admin_default/html'),
      resolve(rootDir, 'src/themes/admin_default/assets'),
      resolve(rootDir, 'src/modules'),
    ],
    dynamicNavigation: true,
  },
];

const sourceExtensions = new Set(['.twig', '.js', '.mjs', '.php']);

function walkFiles(dir) {
  const files = [];

  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);

    if (path.includes('/assets/build/') || path.includes('/vendor/')) {
      continue;
    }

    if (entry.isDirectory()) {
      files.push(...walkFiles(path));
      continue;
    }

    if (sourceExtensions.has(extname(entry.name))) {
      files.push(path);
    }
  }

  return files;
}

function isAreaTemplate(path, area) {
  if (!path.includes('/src/modules/')) {
    return true;
  }

  return path.includes(`/templates/${area}/`);
}

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

async function getDynamicNavigationIcons() {
  const icons = new Set();

  for (const file of walkFiles(resolve(rootDir, 'src/modules'))) {
    if (!file.endsWith('/Controller/Admin.php')) {
      continue;
    }

    const contents = await readFile(file, 'utf8');

    if (!contents.includes('fetchNavigation')) {
      continue;
    }

    for (const match of contents.matchAll(/['"]class['"]\s*=>\s*['"]([^'"]+)['"]/g)) {
      if (match[1]) {
        icons.add(match[1]);
      }
    }
  }

  return icons;
}

async function checkTheme(theme) {
  const manifest = JSON.parse(await readFile(join(theme.themePath, 'icon-manifest.json'), 'utf8'));
  const defaultVariant = manifest.defaultVariant || 'outline';
  const manifestIcons = manifest.icons.map((entry) => normalizeIconEntry(entry, defaultVariant));
  const manifestNames = new Set(manifestIcons.map((icon) => icon.name));
  const declaredDynamicManifestNames = new Set(manifestIcons.filter((icon) => icon.dynamic).map((icon) => icon.name));
  const dynamicManifestNames = new Set(declaredDynamicManifestNames);
  const referencedIcons = new Set();
  const errors = [];
  const legacyReferences = [];
  const externalSpriteReferences = [];

  for (const icon of manifestIcons) {
    if (theme.disallowFilled && icon.variant === 'filled') {
      errors.push(`${theme.code}: "${icon.name}" requests the filled variant, but this theme styles icons as outline SVGs.`);
    }
  }

  for (const scanPath of theme.scanPaths) {
    for (const file of walkFiles(scanPath)) {
      if (!isAreaTemplate(file, theme.area)) {
        continue;
      }

      const contents = await readFile(file, 'utf8');

      if (contents.includes('xlink:href')) {
        legacyReferences.push(relative(rootDir, file));
      }

      if (contents.includes('icons-sprite.svg#')) {
        externalSpriteReferences.push(relative(rootDir, file));
      }

      for (const match of contents.matchAll(/<use\b[^>]*\b(?:href|xlink:href)=["'](?:[^#"']*)#([A-Za-z0-9_-]+)["']/g)) {
        referencedIcons.add(match[1]);
      }
    }
  }

  const dynamicIcons = theme.dynamicNavigation ? await getDynamicNavigationIcons() : new Set();
  const missing = [...referencedIcons, ...dynamicIcons].filter((icon) => !manifestNames.has(icon)).sort();
  const unused = [...manifestNames].filter((icon) => !referencedIcons.has(icon) && !dynamicManifestNames.has(icon)).sort();
  const undocumentedDynamic = [...dynamicIcons].filter((icon) => !declaredDynamicManifestNames.has(icon)).sort();

  if (legacyReferences.length > 0) {
    errors.push(`${theme.code}: legacy xlink:href references remain in ${[...new Set(legacyReferences)].join(', ')}`);
  }

  if (externalSpriteReferences.length > 0) {
    errors.push(`${theme.code}: external sprite references remain in ${[...new Set(externalSpriteReferences)].join(', ')}`);
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

  console.log(`${theme.code}: ${manifestNames.size} manifest icons, ${referencedIcons.size} static references, ${dynamicManifestNames.size} dynamic icons`);

  return errors;
}

const allErrors = [];

for (const theme of themes) {
  allErrors.push(...await checkTheme(theme));
}

if (allErrors.length > 0) {
  for (const error of allErrors) {
    console.error(error);
  }

  process.exit(1);
}
