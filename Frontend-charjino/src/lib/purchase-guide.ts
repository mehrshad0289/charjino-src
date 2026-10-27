export type PurchaseGuideItemType = 'text' | 'image' | 'video' | 'gif';

export type PurchaseGuideItem = {
  type: PurchaseGuideItemType;
  title?: string | null;
  body?: string | null;
  src?: string | null;
  caption?: string | null;
};

export function normalizePurchaseGuideItems(
  items?: PurchaseGuideItem[] | null,
  fallback?: string | null,
): PurchaseGuideItem[] {
  if (Array.isArray(items) && items.length > 0) {
    return items.filter((item) => item && (item.body?.trim() || item.src?.trim() || item.title?.trim()));
  }
  const text = fallback?.trim();
  return text ? [{ type: 'text', body: text }] : [];
}

export function isPurchaseGuideEmbed(src?: string | null) {
  const value = src?.trim() ?? '';
  return /youtube\.com|youtu\.be|aparat\.com/i.test(value);
}

export function purchaseGuideEmbedSrc(src: string) {
  const value = src.trim();
  const youtube = value.match(
    /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([A-Za-z0-9_-]{6,})/,
  );
  if (youtube?.[1]) {
    return `https://www.youtube.com/embed/${youtube[1]}`;
  }
  return value;
}
