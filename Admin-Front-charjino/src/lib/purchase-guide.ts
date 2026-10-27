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

export function mediaTypeToGuideType(mediaType?: string, src?: string): PurchaseGuideItemType {
  if (mediaType === 'video' || isPurchaseGuideEmbed(src)) return 'video';
  if (mediaType === 'gif' || /\.gif(\?|$)/i.test(src ?? '')) return 'gif';
  if (/\.(mp4|webm|mov)(\?|$)/i.test(src ?? '')) return 'video';
  return 'image';
}
