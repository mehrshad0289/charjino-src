import { readdir, readFile, stat } from 'node:fs/promises';
import path from 'node:path';

const ROOT = process.cwd();
const FORBIDDEN = ['چارژینو', 'چارجینو'];
const TEXT_EXT = new Set([
  '.ts',
  '.tsx',
  '.js',
  '.jsx',
  '.mjs',
  '.cjs',
  '.json',
  '.md',
  '.css',
  '.html',
  '.svg',
  '.txt',
  '.xml',
  '.webmanifest',
  '.map',
]);
const SKIP_DIRS = new Set(['node_modules', '.git', '.next/cache', 'coverage']);

function shouldSkipDir(rel) {
  const normalized = rel.replaceAll('\\', '/');
  return (
    normalized === 'node_modules' ||
    normalized.startsWith('node_modules/') ||
    normalized === '.git' ||
    normalized.startsWith('.git/') ||
    normalized.includes('/.git/') ||
    normalized === '.next/cache' ||
    normalized.startsWith('.next/cache/') ||
    normalized === '.next/dev' ||
    normalized.startsWith('.next/dev/')
  );
}

async function walk(dir, files) {
  let entries;
  try {
    entries = await readdir(dir, { withFileTypes: true });
  } catch {
    return;
  }
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    const rel = path.relative(ROOT, full);
    if (entry.isDirectory()) {
      if (shouldSkipDir(rel) || SKIP_DIRS.has(entry.name)) {
        continue;
      }
      await walk(full, files);
      continue;
    }
    if (!TEXT_EXT.has(path.extname(entry.name).toLowerCase())) {
      continue;
    }
    files.push(full);
  }
}

async function main() {
  const files = [];
  for (const folder of ['src', 'public']) {
    await walk(path.join(ROOT, folder), files);
  }
  try {
    await stat(path.join(ROOT, '.next'));
    await walk(path.join(ROOT, '.next'), files);
  } catch {
    /* build output optional */
  }

  const hits = [];
  for (const file of files) {
    let text;
    try {
      text = await readFile(file, 'utf8');
    } catch {
      continue;
    }
    for (const needle of FORBIDDEN) {
      if (text.includes(needle)) {
        hits.push(`${path.relative(ROOT, file)}: ${needle}`);
      }
    }
  }

  if (hits.length > 0) {
    console.error('Brand spelling errors (use شارژینو):');
    for (const hit of hits) {
      console.error(`  ${hit}`);
    }
    process.exit(1);
  }

  console.log(`check:brand ok (${files.length} files)`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
