import { readdir, copyFile, mkdir, rm } from 'fs/promises';
import { join } from 'path';

/**
 * Ensure a directory exists, creating it recursively if needed
 * @param {string} dir - Directory path to ensure exists
 */
export async function ensureDir(dir) {
  await mkdir(dir, { recursive: true });
}

/**
 * Recursively copy assets from source to destination directory
 * @param {string} srcDir - Source directory path
 * @param {string} destDir - Destination directory path
 * @param {object} options - Options object
 * @param {Set<string>} options.exclude - Set of filenames to exclude from copying
 */
export async function copyAssets(srcDir, destDir, options = {}) {
  const exclude = options.exclude || new Set();
  try {
    const entries = await readdir(srcDir, { withFileTypes: true });
    for (const entry of entries) {
      if (exclude.has(entry.name)) continue;
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
    if (error.code !== 'ENOENT') throw error;
  }
}

/**
 * Remove all contents of a directory without removing the directory itself
 * @param {string} dir - Directory path to clean
 */
export async function removeDirContents(dir) {
  try {
    const entries = await readdir(dir, { withFileTypes: true });
    for (const entry of entries) {
      const entryPath = join(dir, entry.name);
      await rm(entryPath, { recursive: true, force: true });
    }
  } catch (error) {
    // Ignore errors when the directory does not exist or cannot be read
  }
}
