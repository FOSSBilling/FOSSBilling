#!/usr/bin/env node

import fs from 'node:fs';
import { mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import path from 'path';
import { fileURLToPath } from 'url';
import * as esbuild from 'esbuild';
import { sassPlugin } from 'esbuild-sass-plugin';
import postcss from 'postcss';
import autoprefixer from 'autoprefixer';
import crypto from 'node:crypto';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const BUILD_DIR = path.join(__dirname, 'build');
const ENTRY_NAME = 'fossbilling';
const SYMBOL_DIR = path.join(BUILD_DIR, 'symbol');

function ensureDirSync(dir) {
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
}

async function ensureCleanBuildDir() {
  await rm(BUILD_DIR, { recursive: true, force: true });
  await mkdir(BUILD_DIR, { recursive: true });
}

async function buildSprite() {
  const iconsDir = path.join(__dirname, 'assets', 'icons');
  ensureDirSync(SYMBOL_DIR);
  let files = [];
  try { files = await readdir(iconsDir); } catch (e) { files = []; }
  const symbols = [];

  for (const file of (files || []).sort()) {
    if (!file.endsWith('.svg')) continue;
    const raw = await readFile(path.join(iconsDir, file), 'utf8');
    const sanitized = raw.replace(/^\uFEFF/, '').replace(/<\?xml[^>]*>/gi, '').replace(/<!DOCTYPE[^>]*>/gi, '');
    const match = sanitized.match(/<svg\b([^>]*)>([\s\S]*?)<\/svg>/i);
    if (!match) continue;
    const attrs = (match[1] || '').replace(/\s+xmlns(:\w+)?="[^"]*"/gi, '').trim();
    const body = (match[2] || '').trim();
    const attrSegment = attrs ? ` ${attrs}` : '';
    const id = path.basename(file, '.svg');
    symbols.push(`<symbol id="${id}"${attrSegment}>${body}</symbol>`);
  }

  const sprite = ['<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="position:absolute;width:0;height:0;overflow:hidden;">', ...symbols, '</svg>'].join('\n');
  await writeFile(path.join(SYMBOL_DIR, 'icons-sprite.svg'), sprite + '\n', 'utf8');
}

function createSassPlugin() {
  return sassPlugin({
    embedded: true,
    loadPaths: [path.join(__dirname, 'assets', 'scss'), path.join(__dirname, '../../..', 'node_modules')],
    async transform(source, resolveDir) {
      const result = await postcss([autoprefixer]).process(source, { from: undefined });
      return { contents: result.css, loader: 'css', resolveDir };
    },
  });
}

function makeIntegrity(buf) {
  return `sha384-${crypto.createHash('sha384').update(buf).digest('base64')}`;
}

async function runEsbuild() {
  const isProduction = process.env.NODE_ENV === 'production';
  const result = await esbuild.build({
    entryPoints: {
      'js/fossbilling': path.join(__dirname, 'assets', 'fossbilling.js'),
      'css/fossbilling': path.join(__dirname, 'assets', 'scss', 'fossbilling.scss'),
    },
    bundle: true,
    minify: isProduction,
    treeShaking: true,
    target: ['es2019'],
    splitting: false,
    outdir: BUILD_DIR,
    entryNames: '[dir]/[name]-bundle.[hash]',
    assetNames: '[dir]/[name].[hash]',
    metafile: true,
    logLevel: 'info',
    plugins: [createSassPlugin()],
    define: { 'process.env.NODE_ENV': JSON.stringify(isProduction ? 'production' : 'development') },
    loader: { '.svg': 'file', '.png': 'file', '.jpg': 'file', '.jpeg': 'file' },
  });

  return result.metafile;
}

async function writeBuildArtifacts(metafile) {
  const outputs = metafile.outputs || {};
  const entryFiles = [];
  const entrypoints = { entrypoints: {}, integrity: {} };

  for (const [outPath, data] of Object.entries(outputs)) {
    if (!data.entryPoint) continue;
    entryFiles.push({ outPath, data });
  }

  entryFiles.sort((a, b) => {
    const aIsCss = a.data.entryPoint.startsWith('css/');
    const bIsCss = b.data.entryPoint.startsWith('css/');
    if (aIsCss === bIsCss) return a.data.entryPoint.localeCompare(b.data.entryPoint);
    return aIsCss ? -1 : 1;
  });

  for (const { outPath, data } of entryFiles) {
    const absolute = path.isAbsolute(outPath) ? outPath : path.join(__dirname, outPath);
    if (!fs.existsSync(absolute)) continue;
    const rel = outPath.split(path.sep).slice(-2).join('/');
    const publicPath = `/themes/admin_default/build/${rel}`;
    const type = data.entryPoint.startsWith('css/') ? 'css' : 'js';
    if (!entrypoints.entrypoints[ENTRY_NAME]) entrypoints.entrypoints[ENTRY_NAME] = { css: [], js: [] };
    entrypoints.entrypoints[ENTRY_NAME][type].push(publicPath);
    try {
      const buf = fs.readFileSync(absolute);
      entrypoints.integrity[publicPath] = makeIntegrity(buf);
    } catch (e) {
      // ignore
    }
  }

  // ensure arrays exist
  if (!entrypoints.entrypoints[ENTRY_NAME]) entrypoints.entrypoints[ENTRY_NAME] = { css: [], js: [] };

  await writeFile(path.join(BUILD_DIR, 'entrypoints.json'), JSON.stringify(entrypoints, null, 2) + '\n', 'utf8');
}

async function main() {
  await ensureCleanBuildDir();
  await buildSprite();
  const metafile = await runEsbuild();
  await writeBuildArtifacts(metafile);
  console.log('admin_default build finished:', BUILD_DIR);
}

main().catch((err) => { console.error(err); process.exit(1); });
  console.log('admin_default build finished:', BUILD_DIR);
