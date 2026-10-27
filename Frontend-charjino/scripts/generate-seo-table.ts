import { readFileSync, writeFileSync } from 'node:fs';
import {
  buildCategorySeoDescription,
  buildCategorySeoHeading,
  buildCategorySeoTitle,
  buildProductSeoDescription,
  buildProductSeoHeading,
  buildProductSeoTitle,
} from '../src/lib/seo-title';

type Row = {
  handle: string;
  title: string;
  nameEn?: string | null;
  type?: string | null;
  platform?: string | null;
  region?: string | null;
  minPrice?: number | null;
  category?: string | null;
  popular?: boolean;
  sold?: number;
};

type Snapshot = {
  groups: Array<{ slug: string; title: string; name_en?: string | null }>;
  top: Row[];
  old?: Record<string, { title?: string; description?: string }>;
};

const BRAND = ' | شارژینو';
const inputPath = process.argv[2];
if (!inputPath) {
  throw new Error('usage: npx tsx scripts/generate-seo-table.ts <snapshot.json>');
}

const data = JSON.parse(readFileSync(inputPath, 'utf8')) as Snapshot;

function fullTitle(inner: string) {
  if (inner.includes('شارژینو')) return inner;
  return `${inner}${BRAND}`;
}

function keywordCore(title: string) {
  return title
    .replace(/\s*\([^)]*\)\s*/g, ' ')
    .replace(/\s*\|\s*تحویل فوری(?:\s+با تومان)?/g, '')
    .replace(/\s+ارزان\s*/g, ' ')
    .replace(/\s*\|\s*شارژینو\s*$/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

const categoryRows = [
  {
    url: 'https://charjino.ir/catalog/category',
    slug: 'shop',
    oldTitle: data.old?.['/catalog/category']?.title ?? '',
    newInner: 'خرید شارژ بازی و گیفت کارت ارزان | تحویل فوری',
    newMeta: 'خرید شارژ بازی، گیفت کارت و کلید بازی با تومان و تحویل فوری از شارژینو. پشتیبانی پاسخگوست؛ پرداخت ریالی بدون رمز اکانت.',
  },
  ...data.groups
    .filter((group) => group.slug !== 'manual-services')
    .map((group) => {
      const path = `/catalog/category/${group.slug}`;
      const inner = buildCategorySeoTitle(group.title, group.slug);
      return {
        url: `https://charjino.ir${path}`,
        slug: group.slug,
        oldTitle: data.old?.[path]?.title ?? '',
        newInner: inner,
        heading: buildCategorySeoHeading(group.title, group.slug),
        newMeta: buildCategorySeoDescription({ title: group.title, slug: group.slug }),
      };
    }),
];

const productRows = data.top.map((row) => {
  const product = {
    title: row.title,
    titleEn: row.nameEn,
    handle: row.handle,
    fulfillmentType: row.type,
    platform: row.platform,
    region: row.region,
    minPrice: row.minPrice,
    category: { slug: row.category },
  };
  const path = `/catalog/${row.handle}`;
  return {
    url: `https://charjino.ir${path}`,
    handle: row.handle,
    oldTitle: data.old?.[path]?.title ?? '',
    newInner: buildProductSeoTitle(product),
    heading: buildProductSeoHeading(product),
    newMeta: buildProductSeoDescription(product),
    category: row.category,
  };
});

const keywordMap = new Map<string, string[]>();
for (const row of [...categoryRows, ...productRows]) {
  const title = fullTitle(row.newInner);
  const key = keywordCore(title);
  const list = keywordMap.get(key) ?? [];
  list.push(row.url);
  keywordMap.set(key, list);
}
const cannibal = [...keywordMap.entries()].filter(([, urls]) => urls.length > 1);

const lines: string[] = [];
lines.push('# SEO titles');
lines.push('');
lines.push('## Categories');
lines.push('');
lines.push('| URL | عنوان قبلی | عنوان جدید | متای جدید | len |');
lines.push('|---|---|---|---|---|');
for (const row of categoryRows) {
  const title = fullTitle(row.newInner);
  lines.push(`| ${row.url} | ${row.oldTitle} | ${title} | ${row.newMeta} | ${title.length} |`);
}
lines.push('');
lines.push('## Products');
lines.push('');
lines.push('| URL | عنوان قبلی | عنوان جدید | متای جدید | len | meta len |');
lines.push('|---|---|---|---|---|---|');
for (const row of productRows) {
  const title = fullTitle(row.newInner);
  lines.push(`| ${row.url} | ${row.oldTitle} | ${title} | ${row.newMeta} | ${title.length} | ${row.newMeta.length} |`);
}
lines.push('');
lines.push('## Cannibalization');
lines.push('');
if (cannibal.length === 0) {
  lines.push('هیچ کلمهٔ کلیدی یکسانی بیش از یک صفحه را هدف نگرفته است.');
} else {
  for (const [key, urls] of cannibal) {
    lines.push(`- **${key}**: ${urls.join(' , ')}`);
  }
}

const outPath = inputPath.replace(/\.json$/i, '-table.md');
writeFileSync(outPath, lines.join('\n'), 'utf8');
writeFileSync(
  inputPath.replace(/\.json$/i, '-computed.json'),
  JSON.stringify({ categoryRows, productRows, cannibal }, null, 2),
  'utf8',
);
console.log(`wrote ${outPath}`);
console.log(`categories=${categoryRows.length} products=${productRows.length} cannibal=${cannibal.length}`);
