import { absoluteUrl } from '@/lib/site';

export type SitemapUrl = {
  loc: string;
  lastmod?: string;
};

function escapeXml(value: string) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;');
}

export function sitemapLastmod(value?: string | null): string | undefined {
  if (!value?.trim()) {
    return undefined;
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return undefined;
  }
  return date.toISOString().slice(0, 10);
}

function isSitemapIndexableLoc(loc: string) {
  try {
    const url = new URL(loc);
    if (url.search) {
      return false;
    }
    const path = url.pathname.replace(/\/+$/, '') || '/';
    if (path === '/uncredited' || path.startsWith('/profile') || path.startsWith('/auth') || path.startsWith('/invoices')) {
      return false;
    }
    return true;
  } catch {
    return false;
  }
}

export function urlsetXml(urls: SitemapUrl[]): string {
  const body = urls
    .filter((url) => isSitemapIndexableLoc(url.loc))
    .map((url) => {
      const lastmod = url.lastmod ? `\n    <lastmod>${escapeXml(url.lastmod)}</lastmod>` : '';
      return `  <url>\n    <loc>${escapeXml(url.loc)}</loc>${lastmod}\n  </url>`;
    })
    .join('\n');
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${body}\n</urlset>\n`;
}

export function sitemapIndexXml(entries: Array<string | { loc: string; lastmod?: string }>): string {
  const body = entries
    .map((entry) => {
      const loc = typeof entry === 'string' ? entry : entry.loc;
      const lastmod = typeof entry === 'string' ? undefined : entry.lastmod;
      const lastmodXml = lastmod ? `\n    <lastmod>${escapeXml(lastmod)}</lastmod>` : '';
      return `  <sitemap>\n    <loc>${escapeXml(loc)}</loc>${lastmodXml}\n  </sitemap>`;
    })
    .join('\n');
  return `<?xml version="1.0" encoding="UTF-8"?>\n<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${body}\n</sitemapindex>\n`;
}

export function chunkPublicUrl(id: string) {
  return absoluteUrl(`/sitemap/${id}.xml`);
}
