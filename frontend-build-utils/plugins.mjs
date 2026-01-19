import autoprefixer from 'autoprefixer';
import postcss from 'postcss';
import * as sass from 'sass';
import { dirname } from 'path';
import { readFile, writeFile } from 'fs/promises';

/**
 * esbuild plugin for compiling SCSS files
 * @param {string} nodeModulesDir - Path to node_modules directory for resolving imports
 * @param {boolean} isProduction - Whether building for production
 * @returns {object} esbuild plugin
 */
export function sassPlugin(nodeModulesDir, isProduction) {
  return {
    name: 'sass',
    setup(build) {
      build.onLoad({ filter: /\.scss$/ }, async (args) => {
        const result = await sass.compileAsync(args.path, {
          loadPaths: [nodeModulesDir],
          style: 'expanded',
          sourceMap: !isProduction,
          sourceMapIncludeSources: !isProduction
        });

        return {
          contents: result.css,
          loader: 'css',
          resolveDir: dirname(args.path)
        };
      });
    }
  };
}

/**
 * Post-process CSS file with autoprefixer
 * @param {string} cssPath - Path to CSS file to process
 * @param {boolean} isProduction - Whether building for production (affects source maps)
 */
export async function postprocessCssFile(cssPath, isProduction) {
  const css = await readFile(cssPath, 'utf8');
  const mapPath = `${cssPath}.map`;
  let prevMap;

  if (!isProduction) {
    try {
      prevMap = await readFile(mapPath, 'utf8');
    } catch (error) {
      // No existing source map, continue without it
    }
  }

  const result = await postcss([autoprefixer]).process(css, {
    from: cssPath,
    to: cssPath,
    map: isProduction ? false : { inline: false, annotation: true, prev: prevMap || undefined }
  });

  await writeFile(cssPath, result.css);
  if (result.map) {
    await writeFile(mapPath, result.map.toString());
  }
}
