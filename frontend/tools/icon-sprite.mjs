import { basename, resolve } from 'path';
import { access, mkdir, readFile, writeFile } from 'fs/promises';

async function fileExists(filePath) {
  try {
    await access(filePath);

    return true;
  } catch {
    return false;
  }
}

function normalizeIconEntry(entry, defaultVariant) {
  if (typeof entry === 'string') {
    return {
      name: entry,
      variant: defaultVariant,
      optional: false,
    };
  }

  return {
    name: entry.name,
    variant: entry.variant || defaultVariant,
    optional: entry.optional || false,
    dynamic: entry.dynamic || false,
  };
}

export async function resolveIconFiles(options) {
  const {
    manifestPath,
    sources,
  } = options;

  const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
  const defaultVariant = manifest.defaultVariant || 'outline';
  const icons = manifest.icons.map((entry) => normalizeIconEntry(entry, defaultVariant));
  const iconFiles = [];
  const missingIcons = [];
  const sourceCounts = new Map();

  for (const icon of icons) {
    let resolvedIcon = null;

    for (const source of sources) {
      const iconPath = resolve(source.dir, source.variant ? `${icon.name}.svg` : `${icon.variant}/${icon.name}.svg`);

      if (await fileExists(iconPath)) {
        resolvedIcon = {
          name: icon.name,
          path: iconPath,
          source: source.name,
          variant: source.variant || icon.variant,
          dynamic: icon.dynamic,
        };
        break;
      }
    }

    if (!resolvedIcon && !icon.optional) {
      missingIcons.push(`${icon.name} (${icon.variant})`);
      continue;
    }

    if (!resolvedIcon) {
      continue;
    }

    sourceCounts.set(resolvedIcon.source, (sourceCounts.get(resolvedIcon.source) || 0) + 1);
    iconFiles.push(resolvedIcon);
  }

  if (missingIcons.length > 0) {
    throw new Error(`Missing required icons: ${missingIcons.join(', ')}`);
  }

  return {
    iconFiles,
    report: {
      total: iconFiles.length,
      sources: Object.fromEntries(sourceCounts),
    },
  };
}

export async function generateIconSprite(options) {
  const {
    outputDir,
    iconFiles,
    sprite = 'icons-sprite.svg',
  } = options;

  const { default: SVGSpriter } = await import('svg-sprite');

  const spriter = new SVGSpriter({
    mode: {
      symbol: {
        dest: '.',
        sprite,
        example: false,
      },
    },
    shape: {
      id: {
        generator: (name) => basename(name, '.svg'),
      },
    },
  });

  const svgFiles = iconFiles ?? [];

  if (svgFiles.length === 0) {
    console.error('No SVG icon files found');
    return;
  }

  for (const icon of svgFiles) {
    const filePath = typeof icon === 'string' ? icon : icon.path;
    const fileName = typeof icon === 'string' ? basename(icon) : `${icon.name}.svg`;
    const content = await readFile(filePath, 'utf8');

    spriter.add(filePath, fileName, content);
  }

  const result = await new Promise((res, rej) => {
    spriter.compile((error, result) => {
      if (error) rej(error);
      else res(result);
    });
  });

  await mkdir(outputDir, { recursive: true });
  await writeFile(resolve(outputDir, sprite), result.symbol.sprite.contents);

  console.log(`  Generated sprite with ${svgFiles.length} icons`);
}

function formatSourceReport(sources) {
  return Object.entries(sources)
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([source, count]) => `${source}=${count}`)
    .join(', ');
}

export async function buildIconSprite(options) {
  const {
    manifestPath,
    outputDir,
    sources,
    sprite,
  } = options;

  const { iconFiles, report } = await resolveIconFiles({
    manifestPath,
    sources,
  });

  await generateIconSprite({
    outputDir,
    iconFiles,
    sprite,
  });

  console.log(`  Icon sources: ${formatSourceReport(report.sources)}`);

  return report;
}
