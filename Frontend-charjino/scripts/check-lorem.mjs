import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';

const ROOT = process.cwd();
const FORBIDDEN = /لورم|ایپسوم|اپیسوم|lorem\s*ipsum/iu;
const TEXT_EXT = new Set(['.html', '.rsc', '.txt']);
const SKIP_DIRS = new Set(['node_modules', '.git', 'cache']);

async function walk(dir, files) {
  let entries;
  try {
    entries = await readdir(dir, { withFileTypes: true });
  } catch {
    return;
  }
  for (const entry of entries) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (SKIP_DIRS.has(entry.name)) continue;
      await walk(full, files);
      continue;
    }
    if (!TEXT_EXT.has(path.extname(entry.name).toLowerCase())) continue;
    files.push(full);
  }
}

async function main() {
  const files = [];
  await walk(path.join(ROOT, '.next'), files);
  if (files.length === 0) {
    console.error('check-lorem: no HTML in .next; run after next build');
    process.exit(1);
  }
  const hits = [];
  for (const file of files) {
    let text;
    try {
      text = await readFile(file, 'utf8');
    } catch {
      continue;
    }
    if (FORBIDDEN.test(text)) {
      hits.push(path.relative(ROOT, file));
    }
  }
  if (hits.length > 0) {
    console.error(`check-lorem: forbidden placeholder text in:\n${hits.slice(0, 40).join('\n')}`);
    process.exit(1);
  }
  console.log(`check-lorem: ok (${files.length} files)`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
