import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';

const ROOT = process.cwd();
const GUIDES = path.resolve(ROOT, '../Backend-charjino/database/data/blog-guides');
const HREF = /href="(\/[^"]+)"/g;

function backendBase() {
  const raw = (process.env.BACKEND_URL || process.env.NEXT_PUBLIC_BACKEND_URL || '').replace(/\/+$/, '');
  return raw || 'http://127.0.0.1:3000/api';
}

function siteOrigin() {
  return (process.env.NEXT_PUBLIC_SITE_URL || 'https://charjino.ir').replace(/\/+$/, '');
}

async function fetchJson(url) {
  const response = await fetch(url, {
    headers: { Accept: 'application/json' },
    signal: AbortSignal.timeout(20000),
  });
  if (!response.ok) {
    throw new Error(`${response.status} ${url}`);
  }
  return response.json();
}

function classify(href) {
  const url = new URL(href, siteOrigin());
  return url.pathname.replace(/\/+$/, '') || '/';
}

async function main() {
  let files;
  try {
    files = (await readdir(GUIDES)).filter((name) => name.endsWith('.php') && !name.startsWith('_'));
  } catch {
    console.error('check-blog-links: blog-guides folder missing');
    process.exit(1);
  }
  const hrefs = new Set();
  for (const name of files) {
    const text = await readFile(path.join(GUIDES, name), 'utf8');
    for (const match of text.matchAll(HREF)) {
      hrefs.add(match[1]);
    }
  }

  let slugs = new Set();
  try {
    const categories = await fetchJson(`${backendBase()}/catalog/categories`);
    for (const item of categories?.data ?? []) {
      if (item?.slug) slugs.add(String(item.slug));
    }
  } catch {
    console.warn('check-blog-links: catalog API unreachable; skip');
    return;
  }

  const failures = [];
  for (const href of [...hrefs].sort()) {
    const pathname = classify(href);
    if (pathname.startsWith('/catalog/category/')) {
      const slug = pathname.slice('/catalog/category/'.length);
      if (!slugs.has(slug)) {
        failures.push(`${href} (unknown category)`);
      }
      continue;
    }
    if (!pathname.startsWith('/catalog/') || pathname === '/catalog/category') {
      continue;
    }
    try {
      const resolved = await fetchJson(`${backendBase()}/catalog/resolve?path=${encodeURIComponent(pathname)}`);
      const status = resolved?.data?.status;
      if (status === 'ok' || status === 'redirect') continue;
      failures.push(`${href} (${status || 'missing'})`);
    } catch (error) {
      failures.push(`${href} (${error instanceof Error ? error.message : 'fetch'})`);
    }
  }
  if (failures.length > 0) {
    console.error(`check-blog-links: broken internal links:\n${failures.join('\n')}`);
    process.exit(1);
  }
  console.log(`check-blog-links: ok (${hrefs.size} hrefs)`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
