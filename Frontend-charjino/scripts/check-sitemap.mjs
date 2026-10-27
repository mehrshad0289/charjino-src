const BASE = (process.env.BASE || 'http://127.0.0.1:3001').replace(/\/+$/, '');
const MAX_URLS = 5000;
const MAX_BYTES = 50 * 1024 * 1024;

function locs(xml) {
  return [...xml.matchAll(/<loc>\s*([^<]+)\s*<\/loc>/gi)].map((match) => match[1].trim());
}

async function load(path) {
  const started = Date.now();
  const response = await fetch(`${BASE}${path}`, { headers: { Accept: 'application/xml' } });
  const text = await response.text();
  return { status: response.status, text, ms: Date.now() - started, bytes: Buffer.byteLength(text) };
}

const errors = [];
const index = await load('/sitemap.xml');
if (index.status !== 200) {
  errors.push(`sitemap.xml HTTP ${index.status}`);
}
if (!index.text.includes('<sitemapindex')) {
  errors.push('sitemap.xml is not a sitemapindex');
}
if (index.text.includes('<price')) {
  errors.push('sitemap.xml contains <price');
}

const sitemapLocs = locs(index.text);
if (sitemapLocs.length === 0) {
  errors.push('sitemap index has no <loc>');
}

const allUrls = [];
for (const loc of sitemapLocs) {
  const path = loc.replace(/^https?:\/\/[^/]+/i, '') || '/';
  const chunk = await load(path.startsWith('/') ? path : `/${path}`);
  if (chunk.status !== 200) {
    errors.push(`${path} HTTP ${chunk.status}`);
    continue;
  }
  if (chunk.bytes > MAX_BYTES) {
    errors.push(`${path} is larger than 50MB (${chunk.bytes})`);
  }
  const urlCount = (chunk.text.match(/<url>/g) || []).length;
  if (urlCount > MAX_URLS) {
    errors.push(`${path} has ${urlCount} URLs`);
  }
  if (chunk.text.includes('<price')) {
    errors.push(`${path} contains <price`);
  }
  allUrls.push(...locs(chunk.text));
}

const unique = new Set(allUrls);
if (unique.size !== allUrls.length) {
  errors.push(`duplicate URLs: ${allUrls.length - unique.size}`);
}

const secret = process.env.REVALIDATE_SECRET;
if (secret) {
  const statusRes = await fetch(`${BASE}/api/seo/sitemap-status?secret=${encodeURIComponent(secret)}`);
  if (statusRes.ok) {
    const payload = await statusRes.json();
    const excluded = (payload.data?.chunks ?? []).reduce((sum, chunk) => sum + (chunk.excluded || 0), 0);
    const productUrls = allUrls.filter((url) => url.includes('/catalog/') && !url.includes('/catalog/category')).length;
    const mainTotal = payload.data?.productTotalCached;
    const steamTotal = payload.data?.steamGiftsTotalCached;
    const total =
      (typeof mainTotal === 'number' ? mainTotal : 0) + (typeof steamTotal === 'number' ? steamTotal : 0);
    if (total > 0) {
      const expected = total - excluded;
      if (productUrls < expected * 0.9) {
        errors.push(`product URLs ${productUrls} vs catalog total ${total} minus excluded ${excluded}`);
      }
    }
  }
}

if (errors.length) {
  console.error(errors.join('\n'));
  process.exit(1);
}

console.log(
  JSON.stringify(
    {
      ok: true,
      sitemaps: sitemapLocs.length,
      urls: allUrls.length,
    },
    null,
    2,
  ),
);
