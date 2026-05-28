import autoprefixer from 'autoprefixer';
import postcss from 'postcss';
import * as sass from 'sass';
import { PurgeCSS } from 'purgecss';
import { dirname, join, resolve } from 'path';
import { copyFile, mkdir, readFile, readdir, rm, writeFile } from 'fs/promises';

export const sharedLoaders = {
  '.svg': 'file',
  '.woff': 'file',
  '.woff2': 'file',
  '.ttf': 'file',
  '.eot': 'file',
};

export function sassPlugin(nodeModulesDir, isProduction) {
  return {
    name: 'sass',
    setup(build) {
      build.onLoad({ filter: /\.scss$/ }, async (args) => {
        const result = await sass.compileAsync(args.path, {
          loadPaths: [nodeModulesDir],
          style: 'expanded',
          sourceMap: !isProduction,
          sourceMapIncludeSources: !isProduction,
        });

        return {
          contents: result.css,
          loader: 'css',
          resolveDir: dirname(args.path),
        };
      });
    },
  };
}

export async function ensureDir(dir) {
  await mkdir(dir, { recursive: true });
}

export async function removeDirContents(dir) {
  try {
    const entries = await readdir(dir, { withFileTypes: true });
    for (const entry of entries) {
      await rm(join(dir, entry.name), { recursive: true, force: true });
    }
  } catch (error) {
    if (error.code !== 'ENOENT') {
      throw error;
    }
  }
}

export async function copyAssets(srcDir, destDir, options = {}) {
  const exclude = options.exclude || new Set();

  try {
    const entries = await readdir(srcDir, { withFileTypes: true });
    for (const entry of entries) {
      if (exclude.has(entry.name)) {
        continue;
      }

      const srcPath = join(srcDir, entry.name);
      const destPath = join(destDir, entry.name);

      if (entry.isDirectory()) {
        await ensureDir(destPath);
        await copyAssets(srcPath, destPath, options);
      } else {
        await copyFile(srcPath, destPath);
      }
    }
  } catch (error) {
    if (error.code !== 'ENOENT') {
      throw error;
    }
  }
}

export async function postprocessCssFile(cssPath, isProduction) {
  const css = await readFile(cssPath, 'utf8');
  const mapPath = `${cssPath}.map`;
  let prevMap;

  if (!isProduction) {
    try {
      prevMap = await readFile(mapPath, 'utf8');
    } catch (error) {
      if (error.code !== 'ENOENT') {
        throw error;
      }
    }
  }

  const result = await postcss([autoprefixer]).process(css, {
    from: cssPath,
    to: cssPath,
    map: isProduction ? false : { inline: false, annotation: true, prev: prevMap || undefined },
  });

  await writeFile(cssPath, result.css);
  if (result.map) {
    await writeFile(mapPath, result.map.toString());
  }
}

export async function purgeCssFile(cssFilePath, options = {}) {
  const {
    themePath,
    enabled = false,
    area = 'admin',
    additionalStandardSafelist = [],
  } = options;

  if (!enabled) {
    return;
  }

  try {
    const css = await readFile(cssFilePath, 'utf8');
    const modulesPath = resolve(themePath, '../../modules');
    const moduleArea = area === 'client' ? 'client' : 'admin';

    const purgeCSSResult = await new PurgeCSS().purge({
      content: [
        `${themePath}/html/**/*.twig`,
        `${themePath}/assets/**/*.js`,
        `${modulesPath}/*/templates/${moduleArea}/**/*.twig`,
      ],
      css: [{ raw: css, extension: 'css' }],
      safelist: {
        standard: [
          /^fi-/,
          /^toast/,
          /^modal/,
          /^dropdown/,
          /^collapse/,
          /^alert/,
          /^spinner/,
          /^active$/,
          /^show$/,
          /^fade$/,
          /^nav-/,
          /^data-bs-/,
          /^btn-/,
          /^card-/,
          /^badge-/,
          /^form-/,
          /^text-/,
          /^bg-/,
          /^d-/,
          /^m-/,
          /^p-/,
          /^w-/,
          /^h-/,
          /^border-/,
          /^flex-/,
          /^justify-/,
          /^align-/,
          /^offcanvas-/,
          /^accordion-/,
          /^carousel-/,
          ...additionalStandardSafelist,
        ],
        deep: [/tom-select/, /ts-/],
        greedy: [/^theme-/],
      },
      defaultExtractor: content => content.match(/[A-Za-z0-9-_:/]+/g) || [],
    });

    if (purgeCSSResult?.[0]) {
      await writeFile(cssFilePath, purgeCSSResult[0].css);
      console.log(`PurgeCSS applied to ${cssFilePath.split('/').pop()}`);
    }
  } catch (error) {
    console.warn(`PurgeCSS failed for ${cssFilePath.split('/').pop()}:`, error.message);
  }
}
