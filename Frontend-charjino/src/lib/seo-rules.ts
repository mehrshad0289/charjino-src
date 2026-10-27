export const EXCLUDE_PATTERNS: RegExp[] = [/🔞/u];

const HANDLE_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/i;
const CJK_CHAR = /[\u3040-\u30FF\u3400-\u9FFF\uF900-\uFAFF]/u;
const INDEXABLE_SCRIPT = /[A-Za-z0-9\u0600-\u06FF]/u;

export const STEAM_GIFT_HANDLE_PREFIX = 'steam-gift-';
export const STEAM_GIFTS_CATEGORY = 'steam-gifts';

export type IndexableProductLike = {
  handle?: string | null;
  title?: string | null;
  fulfillmentType?: string | null;
  indexable?: boolean | null;
};

export function isSteamGiftProduct(product: IndexableProductLike): boolean {
  const handle = product.handle?.trim().toLowerCase() ?? '';
  if (handle.startsWith(STEAM_GIFT_HANDLE_PREFIX)) {
    return true;
  }
  return product.fulfillmentType === 'steam-gift';
}

export function isIndexableProduct(product: IndexableProductLike): boolean {
  const handle = product.handle?.trim() ?? '';
  const title = product.title?.trim() ?? '';
  if (!handle || !HANDLE_PATTERN.test(handle) || !title) {
    return false;
  }
  if (EXCLUDE_PATTERNS.some((pattern) => pattern.test(title) || pattern.test(handle))) {
    return false;
  }
  if (CJK_CHAR.test(title) && !INDEXABLE_SCRIPT.test(title)) {
    return false;
  }
  if (product.indexable === false) {
    return false;
  }
  if (isSteamGiftProduct(product) && product.indexable !== true) {
    return false;
  }
  return true;
}
