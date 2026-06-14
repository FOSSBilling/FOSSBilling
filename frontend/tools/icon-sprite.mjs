import { basename, resolve } from 'path';
import { mkdir, readFile, writeFile } from 'fs/promises';

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
