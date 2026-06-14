import { readdirSync } from 'fs';
import { extname, join, resolve } from 'path';
import { spawnSync } from 'child_process';
import { dirname } from 'path';
import { fileURLToPath } from 'url';

const rootDir = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const scanRoots = [
  resolve(rootDir, 'frontend'),
  resolve(rootDir, 'src/themes/admin_default'),
  resolve(rootDir, 'src/themes/huraga'),
];
const sourceExtensions = new Set(['.js', '.mjs']);

function walkFiles(dir) {
  const files = [];

  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);

    if (path.includes('/assets/build/') || path.includes('/node_modules/')) {
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

const files = scanRoots.flatMap(walkFiles).sort();
const failures = [];

for (const file of files) {
  const result = spawnSync(process.execPath, ['--check', file], {
    encoding: 'utf8',
  });

  if (result.status !== 0) {
    failures.push({ file, stderr: result.stderr.trim() });
  }
}

if (failures.length > 0) {
  for (const failure of failures) {
    console.error(`${failure.file}\n${failure.stderr}`);
  }

  process.exit(1);
}

console.log(`Checked JavaScript syntax for ${files.length} files`);
