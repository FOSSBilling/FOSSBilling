import { basename, resolve } from 'path';
import { copyFile, mkdir, readFile, readdir, rm, writeFile } from 'fs/promises';

export async function generateIconSprite(options) {
  const {
    manifestPath,
    outputDir,
    customIconsDir,
    rootDir,
  } = options;

  const { default: SVGSpriter } = await import('svg-sprite');
  const nodeModulesDir = resolve(rootDir, 'node_modules');
  const tablerIconsDir = (variant) => resolve(nodeModulesDir, '@tabler/icons/icons', variant);

  const manifest = JSON.parse(await readFile(manifestPath, 'utf8'));
  const defaultVariant = manifest.defaultVariant || 'outline';
  const icons = manifest.icons;

  const tempDir = resolve(outputDir, '.icon-temp');
  await mkdir(tempDir, { recursive: true });

  try {
    for (const entry of icons) {
      const name = typeof entry === 'string' ? entry : entry.name;
      const variant = (typeof entry === 'object' && entry.variant) || defaultVariant;

      let sourcePath = null;

      if (customIconsDir) {
        const customPath = resolve(customIconsDir, `${name}.svg`);
        try {
          await readFile(customPath);
          sourcePath = customPath;
        } catch {}
      }

      if (!sourcePath) {
        const tablerPath = resolve(tablerIconsDir(variant), `${name}.svg`);
        try {
          await readFile(tablerPath);
          sourcePath = tablerPath;
        } catch {
          if (variant !== 'outline') {
            const outlinePath = resolve(tablerIconsDir('outline'), `${name}.svg`);
            try {
              await readFile(outlinePath);
              sourcePath = outlinePath;
              console.warn(`  Icon "${name}" not available in "${variant}" variant, fell back to outline`);
            } catch {}
          }
        }
      }

      if (!sourcePath) {
        console.warn(`  Warning: Icon "${name}" not found, skipping`);
        continue;
      }

      await copyFile(sourcePath, resolve(tempDir, `${name}.svg`));
    }

    const spriter = new SVGSpriter({
      mode: {
        symbol: {
          dest: '.',
          sprite: 'icons-sprite.svg',
          example: false,
        },
      },
      shape: {
        id: {
          generator: (name) => basename(name, '.svg'),
        },
      },
    });

    const files = await readdir(tempDir);
    const svgFiles = files.filter((f) => f.endsWith('.svg'));

    if (svgFiles.length === 0) {
      console.error('No SVG icon files found');
      return;
    }

    for (const file of svgFiles) {
      const filePath = resolve(tempDir, file);
      const content = await readFile(filePath, 'utf8');
      spriter.add(filePath, file, content);
    }

    const result = await new Promise((res, rej) => {
      spriter.compile((error, result) => {
        if (error) rej(error);
        else res(result);
      });
    });

    await mkdir(outputDir, { recursive: true });
    await writeFile(resolve(outputDir, 'icons-sprite.svg'), result.symbol.sprite.contents);

    console.log(`  Generated sprite with ${svgFiles.length} icons`);
  } finally {
    await rm(tempDir, { recursive: true, force: true });
  }
}
