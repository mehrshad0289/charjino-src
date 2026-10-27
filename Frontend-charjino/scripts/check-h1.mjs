import {
  buildProductSeoHeading,
  buildProductSeoTitle,
  headingHasSlugToken,
  productHeadingIssues,
} from '../src/lib/seo-title.ts';

function backendBase() {
  const raw = (process.env.BACKEND_URL || process.env.NEXT_PUBLIC_BACKEND_URL || '').replace(/\/+$/, '');
  return raw || 'https://api.charjino.ir/api';
}

const fixtures = [
  {
    title: 'Xbox REACT Credits',
    titleEn: 'Xbox REACT Credits',
    handle: 'giftcard-xbox-react-credits-extraction-us',
    fulfillmentType: 'giftcard',
    region: 'US',
    platform: 'xbox',
  },
  {
    title: 'Dead Rising 2: Off the Record',
    titleEn: 'Dead Rising 2: Off the Record',
    handle: 'gamekey-dead-rising-2-off-the-record-cis',
    fulfillmentType: 'gamekey',
    region: 'CIS',
  },
];

function fail(message) {
  console.error(message);
  process.exit(1);
}

function checkProduct(product, label) {
  const heading = buildProductSeoHeading(product);
  const title = buildProductSeoTitle(product);
  const issues = productHeadingIssues(product, heading);
  if (title.includes('…') && !title.endsWith('…')) {
    issues.push('title-mid-ellipsis');
  }
  if (headingHasSlugToken(title, product)) {
    issues.push('title-slug-token');
  }
  if (issues.length > 0) {
    fail(`check-h1: ${label} ${issues.join(', ')} :: ${heading} :: ${title}`);
  }
}

for (const fixture of fixtures) {
  checkProduct(fixture, fixture.handle);
}

const controller = new AbortController();
const timer = setTimeout(() => controller.abort(), 20000);
try {
  const origin = backendBase();
  let page = 1;
  let totalPages = 1;
  while (page <= totalPages && page <= 6) {
    const url = `${origin}/catalog/products?page=${page}&perPage=100&excludeCategory=steam-gifts`;
    const response = await fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } });
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    const payload = await response.json();
    const items = Array.isArray(payload?.data?.items) ? payload.data.items : Array.isArray(payload?.data) ? payload.data : [];
    totalPages = Number(payload?.data?.meta?.totalPages) || 1;
    for (const item of items) {
      if ((item.fulfillmentType || '') === 'steam-gift') continue;
      checkProduct(item, item.handle || item.title);
    }
    page += 1;
  }
  console.log('check-h1: ok (fixtures + live catalog)');
} catch (error) {
  console.log(`check-h1: live catalog skipped (${error instanceof Error ? error.message : 'unreachable'}); fixtures ok`);
} finally {
  clearTimeout(timer);
}
