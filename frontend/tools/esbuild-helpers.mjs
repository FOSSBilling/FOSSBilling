import autoprefixer from 'autoprefixer';
import postcss from 'postcss';
import * as sass from 'sass';
import { PurgeCSS } from 'purgecss';
import { dirname, join, resolve } from 'path';
import { mkdir, readFile, readdir, rm, stat, writeFile } from 'fs/promises';
import * as esbuild from 'esbuild';

export const sharedLoaders = {
  '.svg': 'file',
  '.woff': 'file',
  '.woff2': 'file',
  '.ttf': 'file',
  '.eot': 'file',
  '.webp': 'file',
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

export function getThemeBuildPaths(themePath) {
  const buildDir = resolve(themePath, 'assets/build');

  return {
    buildDir,
    jsDir: join(buildDir, 'js'),
    cssDir: join(buildDir, 'css'),
    symbolDir: join(buildDir, 'symbol'),
  };
}

export async function prepareThemeBuildDirs(paths) {
  await removeDirContents(paths.buildDir);
  await ensureDir(paths.jsDir);
  await ensureDir(paths.cssDir);
  await ensureDir(paths.symbolDir);
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
    throw new Error(`PurgeCSS failed for ${cssFilePath.split('/').pop()}: ${error.message}`);
  }
}

function formatBytes(bytes) {
  if (bytes < 1024) {
    return `${bytes}b`;
  }

  const units = ['kb', 'mb'];
  let size = bytes / 1024;
  let unit = units.shift();

  while (size >= 1024 && units.length > 0) {
    size /= 1024;
    unit = units.shift();
  }

  return `${size.toFixed(1)}${unit}`;
}

export async function logFileSize(filePath, label = filePath.split('/').pop()) {
  const fileStat = await stat(filePath);
  console.log(`  ${label}: ${formatBytes(fileStat.size)}`);
}

export async function buildCssFile(options) {
  const {
    entryPoint,
    outfile,
    nodeModulesDir,
    isProduction,
    loader = sharedLoaders,
    themePath,
    purge,
  } = options;

  await esbuild.build({
    entryPoints: [entryPoint],
    bundle: true,
    outfile,
    loader,
    plugins: [sassPlugin(nodeModulesDir, isProduction)],
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info',
    define: { 'process.env.NODE_ENV': isProduction ? '"production"' : '"development"' },
    treeShaking: true,
    legalComments: 'none',
  });

  await postprocessCssFile(outfile, isProduction);

  if (purge) {
    await purgeCssFile(outfile, {
      themePath,
      enabled: isProduction,
      ...purge,
    });
  }

  await logFileSize(outfile, `${outfile.split('/').pop()} after CSS post-processing`);
}

export async function buildJsFile(options) {
  const {
    entryPoint,
    outfile,
    outdir,
    entryNames,
    chunkNames,
    isProduction,
    loader = sharedLoaders,
    drop = isProduction ? ['console', 'debugger'] : [],
    splitting = false,
    format = splitting ? 'esm' : undefined,
  } = options;

  const buildOptions = {
    entryPoints: [entryPoint],
    bundle: true,
    platform: 'browser',
    target: 'es2018',
    loader,
    define: { 'process.env.NODE_ENV': isProduction ? '"production"' : '"development"' },
    minify: isProduction,
    sourcemap: !isProduction,
    logLevel: 'info',
    treeShaking: true,
    legalComments: 'none',
    drop,
    format,
    splitting,
  };

  if (outdir) {
    buildOptions.outdir = outdir;
  } else {
    buildOptions.outfile = outfile;
  }

  if (entryNames) {
    buildOptions.entryNames = entryNames;
  }

  if (chunkNames) {
    buildOptions.chunkNames = chunkNames;
  }

  await esbuild.build(buildOptions);
}

export async function writeAssetManifest(buildDir, manifest) {
  await writeFile(join(buildDir, 'manifest.json'), JSON.stringify(manifest, null, 2));
}
