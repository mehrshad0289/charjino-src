import { catalogRegionLabel } from '@/lib/catalog-region';

const BRAND = 'شارژینو';

export const BRAND_LOGO_ALT = BRAND;
export const ENAMAD_SEAL_ALT = 'نماد اعتماد الکترونیک';

const GENERIC_CATEGORY = new Set(['بازی', 'تلگرام', 'گیفت کارت', 'گیم', 'دسته', 'محصول']);

function compact(value?: string | null) {
  return (value ?? '').replace(/\s+/g, ' ').trim();
}

function stripBrandSuffix(value: string) {
  return compact(value.replace(/(?:\s*[-–—]\s*شارژینو)+\s*$/u, ''));
}

function regionAlreadyInName(name: string, region: string) {
  if (!region) return false;
  const hay = name.replace(/\s+/g, '');
  const needle = region.replace(/\s+/g, '');
  return Boolean(needle) && hay.includes(needle);
}

export function productImageAlt(name?: string | null, region?: string | null) {
  const title = stripBrandSuffix(compact(name) || 'محصول');
  const place = catalogRegionLabel(region);
  if (place && regionAlreadyInName(title, place)) {
    return title;
  }
  return compact([title, place].filter(Boolean).join(' '));
}

export function bannerImageAlt(banner: { title?: string | null; description?: string | null }) {
  const title = stripBrandSuffix(compact(banner.title));
  if (title) return title;
  return `بنر ${BRAND}`;
}

export function categoryIconAlt(name?: string | null) {
  const label = compact(name);
  if (!label || GENERIC_CATEGORY.has(label)) {
    return `دسته‌بندی ${label || 'محصولات'} در شارژینو`;
  }
  return label;
}

export function uniqueImageAlt(alt: string, seen?: Set<string>): { alt: string; decorative: boolean } {
  const next = compact(alt);
  if (!next) {
    return { alt: '', decorative: true };
  }
  if (!seen) {
    return { alt: next, decorative: false };
  }
  if (seen.has(next)) {
    return { alt: '', decorative: true };
  }
  seen.add(next);
  return { alt: next, decorative: false };
}
