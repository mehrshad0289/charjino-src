export const SITEMAP_KINDS = ['categories', 'products', 'steam-gifts'] as const;

export type SitemapKind = (typeof SITEMAP_KINDS)[number];

export type ParsedSitemapFile = {
  kind: SitemapKind;
  chunk: number;
};

export function sitemapFileId(kind: SitemapKind, chunk = 1): string {
  if (kind === 'categories') {
    return 'categories';
  }
  return chunk <= 1 ? kind : `${kind}-${chunk}`;
}

export function parseSitemapFileId(id: string): ParsedSitemapFile | null {
  const raw = id.trim().toLowerCase().replace(/\.xml$/i, '');
  if (raw === 'categories' || raw === '0') {
    return { kind: 'categories', chunk: 1 };
  }
  const match = /^(products|steam-gifts)(?:-(\d+))?$/.exec(raw);
  if (!match) {
    return null;
  }
  const chunk = match[2] ? Number.parseInt(match[2], 10) : 1;
  if (!Number.isFinite(chunk) || chunk < 1) {
    return null;
  }
  return { kind: match[1] as SitemapKind, chunk };
}

export function productFileIds(kind: Exclude<SitemapKind, 'categories'>, total: number, chunkSize: number): string[] {
  const n = Math.max(1, Math.ceil(Math.max(0, total) / chunkSize));
  return Array.from({ length: n }, (_, index) => sitemapFileId(kind, index + 1));
}
