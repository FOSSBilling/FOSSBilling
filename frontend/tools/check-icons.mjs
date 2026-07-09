import { readFile } from 'fs/promises';
import { dirname, extname, join, relative, resolve } from 'path';
import { readdirSync } from 'fs';
import { fileURLToPath } from 'url';

import { parseManifest, extractIconReferencesFromContent, computeIconErrors } from './icon-check-helpers.mjs';

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
  const manifestData = parseManifest(manifest);

  const referencedIcons = new Set();
  const legacyReferences = [];
  const externalSpriteReferences = [];

  for (const scanPath of theme.scanPaths) {
    for (const file of walkFiles(scanPath)) {
      if (!isAreaTemplate(file, theme.area)) {
        continue;
      }

      const contents = await readFile(file, 'utf8');
      const result = extractIconReferencesFromContent(contents);

      result.references.forEach((icon) => referencedIcons.add(icon));

      if (result.hasLegacy) {
        legacyReferences.push(relative(rootDir, file));
      }

      if (result.hasExternalSprite) {
        externalSpriteReferences.push(relative(rootDir, file));
      }
    }
  }

  const dynamicIcons = theme.dynamicNavigation ? await getDynamicNavigationIcons() : new Set();
  const errors = computeIconErrors(theme, manifestData, { referencedIcons, legacyReferences, externalSpriteReferences }, dynamicIcons);

  console.log(`${theme.code}: ${manifestData.manifestNames.size} manifest icons, ${referencedIcons.size} static references, ${manifestData.dynamicManifestNames.size} dynamic icons`);

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
