import { brandHubBySlug } from '../data/brand-hubs';
import {
  SEO_BRANDS,
  SEO_CATEGORIES,
  SEO_FORBIDDEN_CLAIMS,
  SEO_REGIONS,
  SEO_SKIP_REGIONS,
  SEO_TITLE_BRAND,
  type SeoBrandName,
} from '../data/seo-catalog-names';
import { formatTomanNumber, tomanPrice } from './price';
import { isSteamGiftProduct, STEAM_GIFT_HANDLE_PREFIX } from './seo-rules';

export type SeoTitleProduct = {
  title: string;
  handle?: string | null;
  titleEn?: string | null;
  subtitle?: string | null;
  publisher?: string | null;
  platform?: string | null;
  region?: string | null;
  fulfillmentType?: string | null;
  minPrice?: number | null;
  seoTitle?: string | null;
  seoDescription?: string | null;
  category?: { slug?: string | null; title?: string | null; seoTitle?: string | null; seoDescription?: string | null } | null;
  buyGuide?: { seo?: { title?: string | null; description?: string | null } | null } | null;
  skus?: Array<{ region?: string | null; sku?: string | null; nameEn?: string | null }>;
};

const TARGET_LEN = 60;
const TARGET_MIN = 30;
const DESC_MIN = 110;
const DESC_MAX = 160;
const STEAM_GIFT_DESC_MIN = DESC_MIN;
const STEAM_GIFT_DESC_MAX = DESC_MAX;
const STEAM_GIFT_HEADING_LEAD = 'خرید گیفت استیم';
const STEAM_GIFT_GENERIC_NAME = /^(گیفت\s*استیم|steam\s*gifts?|steam)$/iu;
const STEAM_WALLET_PHRASE = /\bsteam\s*wallet\b/gi;
const STEAM_WALLET_PAREN = /\(\s*steam\s*wallet\s*\)/gi;
const STEAM_GIFT_TITLE_PREFIX = /^(خرید\s+)?گیفت\s+استیم\s+/u;
const PERSIAN = /[\u0600-\u06FF]/u;
const BRAND_SUFFIX = /\s*[|\-–—]\s*(شارژینو|Charjino)\s*$/iu;
const REGION_PAREN = /\s*\((?:us|usa|uk|gb|eu|tr|de|global|worldwide)\)\s*$/i;
const NOISE_PAREN = /\s*\((?:auto|fast|reserve)\)\s*$/i;
const TITLE_BRAND_SUFFIX = ` | ${SEO_TITLE_BRAND}`;

/** Free Steam games that still have a paid, sellable item. Keep in sync with SteamGiftIndexability::FREE_WITH_PAID_ITEMS. */
const STEAM_GIFT_PAID_ITEMS: Record<number, string> = {
  730: 'Prime',
};

const VERSION_LABELS: Record<string, string> = {
  'game-pass': 'گیم پس',
  switch: 'سوییچ',
  premium: 'پرمیوم',
  stars: 'استارز',
  diamond: 'الماس',
  diamonds: 'الماس',
  gold: 'گلد',
  fast: 'سریع',
  manual: 'دستی',
  exclusive: 'اختصاصی',
  adventure: 'ادونچر',
  windows: 'ویندوز',
  java: 'جاوا',
  bedrock: 'بدراک',
  minecoins: 'ماین‌کوین',
  robux: 'روباکس',
  goty: 'GOTY',
  remastered: 'ریمسترد',
  definitive: 'کامل',
  legends: 'لجندز',
  garena: 'گارنا',
  reserve: 'رزرو',
  special: 'ویژه',
  part: 'پارت',
  i: 'I',
  ii: 'II',
  iii: 'III',
  iv: 'IV',
};

const HANDLE_STOP = new Set([
  'steam',
  'edition',
  'editions',
  'wallet',
  'gift',
  'card',
  'cards',
  'key',
  'keys',
  'of',
  'and',
  'pack',
  'packs',
  'dlc',
  'tm',
  'global',
  'worldwide',
  'ww',
  'topup',
  'giftcard',
  'gamekey',
  'auto',
]);

const SLUG_KEPT = new Set(['the']);

type TypeRule = {
  verb: 'خرید' | 'شارژ';
  kindFa: string;
};

const TYPE_RULES: Record<string, TypeRule> = {
  giftcard: { verb: 'خرید', kindFa: 'گیفت کارت' },
  gamekey: { verb: 'خرید', kindFa: 'کلید بازی' },
  topup: { verb: 'خرید', kindFa: '' },
  'steam-topup': { verb: 'شارژ', kindFa: 'مستقیم کیف پول' },
  'steam-gift': { verb: 'خرید', kindFa: 'گیفت استیم' },
  'telegram-premium': { verb: 'خرید', kindFa: 'پرمیوم' },
  'telegram-stars': { verb: 'خرید', kindFa: 'استارز' },
};

function compact(text: string) {
  return text.replace(/\s+/g, ' ').trim();
}

function foldDigits(token: string) {
  return token.replace(/[۰-۹]/g, (digit) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(digit)));
}

function normalizeToken(token: string) {
  return foldDigits(token)
    .replace(/[يى]/g, 'ی')
    .replace(/ك/g, 'ک')
    .replace(/‌/g, '')
    .replace(/[()]/g, '')
    .trim()
    .toLowerCase();
}

function stripForbidden(text: string) {
  let next = text;
  for (const claim of SEO_FORBIDDEN_CLAIMS) {
    next = next.split(claim).join(' ');
  }
  return compact(next);
}

function uniqueTokens(text: string) {
  const seen = new Set<string>();
  const out: string[] = [];
  for (const token of compact(text).split(' ')) {
    const hasParen = /[()]/.test(token);
    const key = normalizeToken(token);
    if (!key) {
      if (hasParen) out.push(token);
      continue;
    }
    if (!hasParen) {
      if (seen.has(key)) continue;
      seen.add(key);
    }
    out.push(token);
  }
  return out.join(' ');
}

function familyOf(product: SeoTitleProduct) {
  const type = product.fulfillmentType?.trim() ?? '';
  if (type && TYPE_RULES[type]) return type;
  const slug = product.category?.slug?.trim() ?? '';
  if (slug === 'gift-card') return 'giftcard';
  if (slug === 'game-keys') return 'gamekey';
  if (slug === 'steam-wallet') return 'steam-topup';
  if (slug === 'steam-gifts') return 'steam-gift';
  if (slug === 'game-topup') return 'topup';
  if (slug === 'telegram') return type.startsWith('telegram') ? type : 'telegram-premium';
  return type;
}

function aliasPattern(alias: string) {
  const escaped = alias.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\s+/g, '[\\s-]+');
  return new RegExp(`(^|[^a-z0-9])${escaped}([^a-z0-9]|$)`, 'i');
}

export function matchSeoBrand(product: SeoTitleProduct): SeoBrandName | null {
  const handle = (product.handle ?? '').trim().toLowerCase();
  const platform = (product.platform ?? '').trim().toLowerCase();
  const family = familyOf(product);
  const hay = [handle, product.publisher, product.titleEn, product.title]
    .filter((value): value is string => Boolean(value && value.trim()))
    .join(' ');
  const ranked = [...SEO_BRANDS].sort((left, right) => {
    const a = Math.max(...left.aliases.map((item) => item.length));
    const b = Math.max(...right.aliases.map((item) => item.length));
    return b - a;
  });
  for (const brand of ranked) {
    if (
      brand.aliases.some((alias) => {
        const id = alias.toLowerCase();
        if (handle === id || handle.startsWith(`${id}-`) || handle.endsWith(`-${id}`)) return true;
        if (family === 'giftcard') {
          return handle === `giftcard-${id}` || handle.startsWith(`giftcard-${id}-`);
        }
        return handle.includes(`-${id}-`);
      })
    ) {
      return brand;
    }
  }
  if (platform && family !== 'gamekey' && family !== 'giftcard') {
    for (const brand of ranked) {
      if (brand.aliases.some((alias) => alias.toLowerCase() === platform)) {
        return brand;
      }
    }
  }
  if (family !== 'giftcard') {
    for (const brand of ranked) {
      if (brand.aliases.some((alias) => aliasPattern(alias).test(hay))) {
        return brand;
      }
    }
  }
  return null;
}

export function seoRegionLabel(region?: string | null) {
  const raw = (region ?? '').trim();
  if (!raw) return '';
  const key = raw.toLowerCase();
  if (SEO_SKIP_REGIONS.has(key)) return '';
  return SEO_REGIONS[key] ?? '';
}

function skuRegionLabel(product: SeoTitleProduct) {
  for (const sku of product.skus ?? []) {
    const label = seoRegionLabel(sku.region);
    if (label) return label;
  }
  return '';
}

function inferredRegionLabel(product: SeoTitleProduct) {
  const direct = seoRegionLabel(product.region);
  if (direct) return direct;
  const fromSku = skuRegionLabel(product);
  if (fromSku) return fromSku;
  const handle = (product.handle ?? '').toLowerCase();
  const keys = Object.keys(SEO_REGIONS).sort((left, right) => right.length - left.length);
  for (const key of keys) {
    if (SEO_SKIP_REGIONS.has(key)) continue;
    const escaped = key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = key.length <= 3 ? `(?:^|-)${escaped}$` : `(?:^|-)${escaped}(?:-|$)`;
    if (new RegExp(pattern, 'i').test(handle)) {
      return SEO_REGIONS[key];
    }
  }
  return '';
}

function handleTokenList(handle?: string | null) {
  const raw = (handle ?? '').trim().toLowerCase();
  const tokens = raw.split('-').filter(Boolean);
  if (tokens[0] === 'giftcard' || tokens[0] === 'topup' || tokens[0] === 'gamekey') {
    return tokens.slice(1);
  }
  return tokens;
}

function removeTokenSeq(tokens: string[], seq: string[]) {
  if (seq.length === 0) return null;
  for (let index = 0; index <= tokens.length - seq.length; index += 1) {
    if (tokens.slice(index, index + seq.length).join('-') === seq.join('-')) {
      return [...tokens.slice(0, index), ...tokens.slice(index + seq.length)];
    }
  }
  return null;
}

function stripBrandTokens(tokens: string[], brand: SeoBrandName | null) {
  if (!brand) return tokens;
  const idSeq = brand.id.toLowerCase().split(/[\s-]+/).filter(Boolean);
  if (
    idSeq.length > 0 &&
    tokens.length >= idSeq.length &&
    tokens.slice(0, idSeq.length).join('-') === idSeq.join('-')
  ) {
    return tokens.slice(idSeq.length);
  }
  const strippedId = removeTokenSeq(tokens, idSeq);
  if (strippedId) return strippedId;
  const seqs = [...brand.aliases, brand.id]
    .map((alias) => alias.toLowerCase().split(/[\s-]+/).filter(Boolean))
    .sort((left, right) => right.length - left.length);
  for (const seq of seqs) {
    if (
      seq.length > 0 &&
      tokens.length >= seq.length &&
      tokens.slice(0, seq.length).join('-') === seq.join('-')
    ) {
      return tokens.slice(seq.length);
    }
  }
  for (const seq of seqs) {
    const stripped = removeTokenSeq(tokens, seq);
    if (stripped) return stripped;
  }
  return tokens;
}

function consumeRegionTokens(tokens: string[]) {
  const labels: string[] = [];
  let rest = [...tokens];
  let progressed = true;
  while (progressed && rest.length > 0) {
    progressed = false;
    for (let size = 3; size >= 1; size -= 1) {
      if (rest.length < size) continue;
      const gram = rest.slice(-size).join('-');
      if (SEO_SKIP_REGIONS.has(gram)) {
        rest = rest.slice(0, -size);
        progressed = true;
        break;
      }
      const label = SEO_REGIONS[gram];
      if (label) {
        if (!labels.includes(label)) labels.unshift(label);
        rest = rest.slice(0, -size);
        progressed = true;
        break;
      }
    }
  }
  const longDict: Record<string, string> = {};
  for (const [key, label] of Object.entries(SEO_REGIONS)) {
    if (key.length > 3) longDict[key] = label;
  }
  const nested = consumeLabeledTokens(rest, longDict);
  for (const label of nested.labels) {
    if (!labels.includes(label)) labels.unshift(label);
  }
  return { labels, rest: nested.rest };
}

function shrinkDistinguisherTail(tail: string, region: string) {
  const parts = compact(tail).split(' ').filter(Boolean);
  if (parts.length <= 2) return compact(tail);
  const versionValues = new Set(Object.values(VERSION_LABELS));
  const versions = parts.filter((part) => versionValues.has(part));
  const numbers = parts.filter((part) => /[0-9۰-۹]/.test(part) && !versionValues.has(part));
  return uniqueTokens([...versions, ...numbers, region].filter(Boolean).join(' '));
}

function consumeLabeledTokens(
  tokens: string[],
  dict: Record<string, string>,
  skip = new Set<string>(),
) {
  const labels: string[] = [];
  const rest: string[] = [];
  let index = 0;
  while (index < tokens.length) {
    let matched = false;
    for (let size = 3; size >= 1; size -= 1) {
      if (index + size > tokens.length) continue;
      const gram = tokens.slice(index, index + size).join('-');
      if (skip.has(gram) || SEO_SKIP_REGIONS.has(gram)) {
        index += size;
        matched = true;
        break;
      }
      const label = dict[gram];
      if (label) {
        if (!labels.includes(label)) labels.push(label);
        index += size;
        matched = true;
        break;
      }
    }
    if (!matched) {
      rest.push(tokens[index]);
      index += 1;
    }
  }
  return { labels, rest };
}

function brandSkipTokens(brand: SeoBrandName | null) {
  if (!brand) return new Set<string>();
  return new Set(
    [...brand.aliases, brand.id, brand.en, brand.fa, brand.unitEn ?? '', brand.unitFa ?? '']
      .filter(Boolean)
      .flatMap((alias) => alias.toLowerCase().split(/[\s-]+/).filter(Boolean)),
  );
}

function titleVariantLabels(product: SeoTitleProduct, brand: SeoBrandName | null) {
  const raw = compact((product.titleEn || product.title || '').replace(REGION_PAREN, ' ').replace(NOISE_PAREN, ' '));
  if (!raw || !brand) return '';
  const skip = brandSkipTokens(brand);
  const unitNoise = new Set(['points', 'point', 'vp', 'uc', 'cp']);
  const original = raw.split(/[^A-Za-z0-9\u0600-\u06FF+]+/).filter(Boolean);
  const kept = original.filter((token) => {
    const key = token.toLowerCase();
    if (skip.has(key) || HANDLE_STOP.has(key) || SEO_SKIP_REGIONS.has(key)) return false;
    if (unitNoise.has(key) && (brand.unitEn || brand.unitFa)) return false;
    return true;
  });
  const labeled = consumeLabeledTokens(
    kept.map((token) => token.toLowerCase()),
    VERSION_LABELS,
  );
  const rest = labeled.rest.map(
    (key) => original.find((item) => item.toLowerCase() === key) || key,
  );
  return uniqueTokens([...labeled.labels, ...rest].join(' '));
}

export function catalogDistinguisher(product: SeoTitleProduct) {
  if (isSteamGiftProduct(product)) return '';
  const brand = matchSeoBrand(product);
  const leftover = stripBrandTokens(handleTokenList(product.handle), brand);
  const regions = consumeRegionTokens(leftover);
  const versions = consumeLabeledTokens(regions.rest, VERSION_LABELS);
  const region =
    uniqueTokens(
      [inferredRegionLabel(product), ...regions.labels, handleQualifier(product.handle)].filter(Boolean).join(' '),
    ) || '';
  const version = uniqueTokens(versions.labels.join(' '));
  const fromTitle = brand?.fa ? titleVariantLabels(product, brand) : '';
  return uniqueTokens([version, fromTitle, region].filter(Boolean).join(' '));
}

function latinTokenNeedsSuffix(heading: string, token: string) {
  const idx = heading.toLowerCase().indexOf(token.toLowerCase());
  if (idx < 0) return true;
  const budget = TARGET_LEN - TITLE_BRAND_SUFFIX.length - 12;
  return idx + token.length > budget;
}

function appendDistinguisher(core: string, extra: string) {
  const head = compact(core);
  const tail = compact(extra);
  if (!tail) return head;
  if (!head) return tail;
  if (head.endsWith(tail)) return head;
  const coreSet = new Set(head.split(' ').map(normalizeToken));
  const versionValues = new Set(Object.values(VERSION_LABELS).map((item) => normalizeToken(item)));
  const keep = tail.split(' ').filter((token) => {
    const key = normalizeToken(token);
    if (!key) return false;
    if (coreSet.has(key)) return false;
    if (versionValues.has(key)) return true;
    if (/[0-9۰-۹]/.test(token)) return true;
    if (/^[a-z][a-z0-9]{3,}$/i.test(token)) return latinTokenNeedsSuffix(head, token);
    return true;
  });
  if (keep.length === 0) return head;
  return compact(`${head} ${keep.join(' ')}`);
}

function matchingPreserveTail(heading: string, extra: string) {
  const tail = compact(extra);
  const head = compact(heading);
  if (!tail) return regionTailOf(head);
  if (head.endsWith(tail)) return tail;
  const parts = tail.split(' ').filter(Boolean);
  while (parts.length > 0) {
    const candidate = parts.join(' ');
    if (head.endsWith(candidate)) return candidate;
    parts.shift();
  }
  return regionTailOf(head);
}

function fallbackEnglish(product: SeoTitleProduct) {
  const raw = compact(product.titleEn?.trim() || product.title.trim());
  return compact(raw.replace(NOISE_PAREN, '').replace(REGION_PAREN, ''));
}

function typeRule(product: SeoTitleProduct, brand: SeoBrandName | null): TypeRule {
  const family = familyOf(product);
  const base = TYPE_RULES[family] ?? { verb: 'خرید' as const, kindFa: '' };
  if (base.kindFa) return base;
  if (brand?.unitFa) return { ...base, kindFa: brand.unitFa };
  return base;
}

function shortenEnglish(english: string, core: string, region: string) {
  const current = compact(english);
  const fits = (value: string) =>
    compact([core.replace(/\s*\([^()]*\)\s*$/, '').trim(), value ? `(${value})` : '', region].join(' ')).length <=
    TARGET_LEN;
  if (!current || fits(current)) return current;
  return '';
}

function englishParen(brand: SeoBrandName | null, product: SeoTitleProduct, nameFa: string) {
  if (brand) {
    const family = familyOf(product);
    const includeUnit = !(TYPE_RULES[family]?.kindFa);
    const parts = [brand.en, includeUnit ? brand.unitEn : ''].filter((item): item is string => Boolean(item && item.trim()));
    const unique = uniqueTokens(parts.join(' '));
    if (!unique) return '';
    if (nameFa && normalizeToken(unique) === normalizeToken(nameFa)) return '';
    return unique;
  }
  const english = fallbackEnglish(product);
  if (!english || (nameFa && normalizeToken(english) === normalizeToken(nameFa))) return '';
  if (!nameFa) return '';
  return english;
}

function stripSteamWalletNoise(value: string) {
  return compact(value.replace(STEAM_WALLET_PAREN, ' ').replace(STEAM_WALLET_PHRASE, ' ').replace(STEAM_GIFT_TITLE_PREFIX, ''));
}

function dropIncompleteParens(text: string) {
  let next = compact(text).replace(/^\)+/, '').trim();
  let depth = 0;
  let lastOpen = -1;
  for (let index = 0; index < next.length; index += 1) {
    const char = next[index];
    if (char === '(') {
      if (depth === 0) lastOpen = index;
      depth += 1;
    } else if (char === ')') {
      if (depth === 0) {
        next = compact(`${next.slice(0, index)}${next.slice(index + 1)}`);
        index -= 1;
        continue;
      }
      depth -= 1;
      if (depth === 0) lastOpen = -1;
    }
  }
  if (depth > 0 && lastOpen >= 0) {
    next = compact(next.slice(0, lastOpen));
  }
  return next;
}

function stripHangingEnglish(text: string) {
  return compact(text.replace(/(?:\s+[:\-]|:\s*|\s+(?:the|of|a|an|on|and|or|for|to|in|with|de|la|el))\s*$/giu, ''));
}

function clipGameName(name: string, max: number, withEllipsis = true) {
  const raw = dropIncompleteParens(compact(name));
  if (raw.length <= max) return raw;
  const ellipsis = withEllipsis ? '…' : '';
  if (withEllipsis && max <= ellipsis.length) return raw.slice(0, Math.max(max, 1));
  const budget = Math.max(max - ellipsis.length, 1);
  const slice = raw.slice(0, budget);
  const cut = Math.max(slice.lastIndexOf(' '), slice.lastIndexOf('،'), slice.lastIndexOf('؛'));
  const atBoundary = cut >= 1 ? slice.slice(0, cut) : slice.replace(/[^\s…]+$/u, '');
  const head = dropIncompleteParens(stripHangingEnglish(atBoundary.trim()));
  if (!withEllipsis) return head;
  return `${head || slice.trim()}${ellipsis}`;
}

function regionTailOf(text: string) {
  const labels = [...new Set([...Object.values(SEO_REGIONS), 'رزرو'])].sort((left, right) => right.length - left.length);
  for (const label of labels) {
    if (text.endsWith(` ${label}`)) {
      return label;
    }
  }
  return '';
}

function clipPreservingTail(name: string, max: number, preserveTail = '') {
  const raw = dropIncompleteParens(compact(name));
  if (raw.length <= max) return raw;
  const requested = compact(preserveTail);
  const matching = matchingPreserveTail(raw, requested);
  const region = regionTailOf(requested) || regionTailOf(matching) || regionTailOf(raw);
  const withoutParens = compact(raw.replace(/\s*\([^()]*\)\s*/g, ' '));
  const head =
    matching && raw.endsWith(matching)
      ? compact(raw.slice(0, raw.length - matching.length))
      : raw;
  if (!requested && !region) return clipGameName(withoutParens.length <= max ? withoutParens : raw, max, false);
  const candidates = [
    requested,
    shrinkDistinguisherTail(requested || matching, region || ''),
    uniqueTokens(
      [
        ...(requested || matching)
          .split(' ')
          .filter((part) => new Set(Object.values(VERSION_LABELS)).has(part) || /[0-9۰-۹]/.test(part)),
        region,
      ]
        .filter(Boolean)
        .join(' '),
    ),
    region,
  ].filter((item): item is string => Boolean(item && item.trim()));
  const heads = [head, compact(head.replace(/\s*\([^()]*\)\s*/g, ' '))];
  const versionValues = new Set(Object.values(VERSION_LABELS));
  const hasVersion = (text: string) => text.split(' ').some((part) => versionValues.has(part));
  const keepsImportant = (text: string) =>
    Boolean((region && text.endsWith(region)) || hasVersion(text));

  let best = '';
  for (const currentHead of heads) {
    if (!currentHead) continue;
    if (currentHead.length <= max && currentHead.length > best.length) {
      best = currentHead;
    }
    for (const tail of candidates) {
      const next = dropIncompleteParens(compact(`${currentHead} ${tail}`));
      if (next.length <= max && next.length >= best.length) {
        best = next;
      }
    }
  }
  if (best && keepsImportant(best)) return dropIncompleteParens(best);

  for (const currentHead of heads) {
    for (const tail of candidates) {
      const suffix = tail ? ` ${tail}` : '';
      if (!suffix || suffix.length >= max - 8) continue;
      if (!keepsImportant(tail) && best) continue;
      const clipped = clipGameName(currentHead, Math.max(max - suffix.length, 8), false);
      if (!clipped || clipped.startsWith('…')) continue;
      const next = dropIncompleteParens(compact(`${clipped}${suffix}`));
      if (next.includes('…') || next.length > max) continue;
      if (!best || keepsImportant(next)) return next;
    }
  }
  if (best) return dropIncompleteParens(best);

  const suffix = region ? ` ${region}` : '';
  return dropIncompleteParens(
    compact(`${clipGameName(heads[1] || head, Math.max(max - suffix.length, 8), false)}${suffix}`),
  );
}

function documentTitleLength(inner: string) {
  return `${inner}${TITLE_BRAND_SUFFIX}`.length;
}

function titleFitsDocument(inner: string) {
  return documentTitleLength(inner) <= TARGET_LEN;
}

function stripBrandSuffix(value: string) {
  return compact(value.replace(BRAND_SUFFIX, ''));
}

function storedSeoTitle(product: SeoTitleProduct) {
  const raw = compact(product.seoTitle ?? '');
  if (!raw) return '';
  return stripForbidden(stripBrandSuffix(raw));
}

function storedSeoDescription(product: SeoTitleProduct) {
  return compact(product.seoDescription ?? '');
}

function dropTitleSuffixes(inner: string) {
  return compact(
    inner
      .replace(/\s*\|\s*تحویل فوری(?:\s+با تومان)?\s*$/u, '')
      .replace(/\s+ارزان(?:\s*\|\s*)?$/u, ''),
  );
}

function sanitizeTitle(text: string) {
  return dropIncompleteParens(stripHangingEnglish(compact(text)));
}

/** Full `<title>`: keep « | شارژینو» when it fits; otherwise drop brand, then suffixes, then clip at a word/paren boundary. */
export function finalizeDocumentTitle(heading: string, preserveTail = '') {
  const core = sanitizeTitle(heading);
  if (!core) return core;
  const stripped = sanitizeTitle(core.replace(/\s*\([^()]*\)\s*/g, ' '));
  const extras = [' ارزان | تحویل فوری', ' | تحویل فوری', ''];
  const bases = stripped && stripped !== core ? [core, stripped] : [core];
  const candidates = extras.flatMap((extra) => bases.map((base) => sanitizeTitle(`${base}${extra}`)));
  for (const inner of candidates) {
    const branded = `${inner}${TITLE_BRAND_SUFFIX}`;
    if (branded.length <= TARGET_LEN) return branded;
  }
  for (const inner of candidates) {
    if (inner.length <= TARGET_LEN) return inner;
  }
  for (const inner of candidates.map(dropTitleSuffixes).map(sanitizeTitle)) {
    if (inner.length <= TARGET_LEN) return inner;
  }
  const source = stripped || core;
  return sanitizeTitle(clipPreservingTail(source, TARGET_LEN, preserveTail));
}

/** Inner title (layout adds « | شارژینو»). Prefer 30–60 characters including the brand suffix. */
export function fitDocumentTitle(heading: string, preserveTail = '') {
  const core = compact(heading);
  if (!core) return core;
  const stripped = compact(core.replace(/\s*\([^)]*\)\s*/g, ' '));
  const extras =
    titleFitsDocument(core) && documentTitleLength(core) >= TARGET_MIN
      ? ['']
      : [' ارزان | تحویل فوری', ' | تحویل فوری', ''];
  const bases = stripped && stripped !== core ? [core, stripped] : [core];
  const scored = extras.flatMap((extra) =>
    bases.map((base) => {
      const inner = compact(`${base}${extra}`);
      return { inner, len: documentTitleLength(inner) };
    }),
  );
  const inRange = scored.filter((item) => item.len >= TARGET_MIN && item.len <= TARGET_LEN);
  if (inRange.length > 0) {
    return inRange[0].inner;
  }
  const fitting = scored.filter((item) => item.len <= TARGET_LEN);
  if (fitting.length > 0) {
    return fitting[0].inner;
  }
  const budget = TARGET_LEN - TITLE_BRAND_SUFFIX.length;
  const source = stripped || core;
  return clipPreservingTail(source, Math.max(budget, 8), preserveTail);
}

const STEAM_GIFT_CHEAP = 'ارزان';
const STEAM_GIFT_DELIVERY = 'تحویل فوری با تومان';

function fitSteamGiftDocumentTitle(heading: string) {
  const core = compact(heading);
  if (!core) return core;
  if (titleFitsDocument(core) && documentTitleLength(core) >= TARGET_MIN) {
    return core;
  }
  const withCheapAndDelivery = compact(`${core} ${STEAM_GIFT_CHEAP} | ${STEAM_GIFT_DELIVERY}`);
  const withDelivery = compact(`${core} | ${STEAM_GIFT_DELIVERY}`);
  for (const inner of [withCheapAndDelivery, withDelivery, core]) {
    if (titleFitsDocument(inner)) {
      return inner;
    }
  }
  const prefix = `${STEAM_GIFT_HEADING_LEAD} `;
  const name = core.startsWith(prefix) ? core.slice(prefix.length) : core;
  const budget = TARGET_LEN - TITLE_BRAND_SUFFIX.length - prefix.length;
  return compact(`${prefix}${clipGameName(name, Math.max(budget, 8))}`);
}

export function headingFromSeoTitle(title: string) {
  return compact(
    stripBrandSuffix(title)
      .replace(/\s*\|\s*تحویل فوری(?:\s+با تومان)?\s*$/u, '')
      .replace(/\s+ارزان\s*$/u, ''),
  );
}

export function steamGiftAppId(product: SeoTitleProduct) {
  const handle = (product.handle ?? '').trim().toLowerCase();
  const match = handle.startsWith(STEAM_GIFT_HANDLE_PREFIX)
    ? handle.slice(STEAM_GIFT_HANDLE_PREFIX.length).match(/^(\d+)/)
    : null;
  return match ? Number(match[1]) : 0;
}

export function steamGiftPaidItem(product: SeoTitleProduct) {
  const appId = steamGiftAppId(product);
  return appId > 0 ? STEAM_GIFT_PAID_ITEMS[appId] ?? '' : '';
}

export function steamGiftGameName(product: SeoTitleProduct) {
  const title = stripSteamWalletNoise(product.title ?? '');
  const titleEn = stripSteamWalletNoise(product.titleEn ?? '');
  if (title && !STEAM_GIFT_GENERIC_NAME.test(title)) return title;
  if (titleEn && !STEAM_GIFT_GENERIC_NAME.test(titleEn)) return titleEn;
  return title || titleEn || 'استیم';
}

export function buildSteamGiftSeoHeading(product: SeoTitleProduct) {
  const name = steamGiftGameName(product);
  const paid = steamGiftPaidItem(product);
  if (paid) {
    return compact(`خرید گیفت ${name} ${paid}`);
  }
  return compact(`${STEAM_GIFT_HEADING_LEAD} ${name}`);
}

export function buildSteamGiftSeoTitle(product: SeoTitleProduct) {
  return fitSteamGiftDocumentTitle(buildSteamGiftSeoHeading(product));
}

function clipMetaDescription(text: string) {
  const compactText = compact(text);
  if (compactText.length <= DESC_MAX) {
    return compactText;
  }
  const slice = compactText.slice(0, DESC_MAX - 1);
  const cut = Math.max(slice.lastIndexOf(' '), slice.lastIndexOf('،'), slice.lastIndexOf('؛'));
  return `${(cut >= DESC_MIN ? slice.slice(0, cut) : slice).trim()}…`;
}

export function fillMetaDescription(text: string) {
  let next = compact(text);
  const pads = [
    ' پرداخت ریالی است.',
    ' بدون رمز اکانت بازی.',
    ' سفارش فقط از خود سایت ثبت می‌شود.',
    ' پشتیبانی از همین سایت پاسخ می‌دهد.',
    ' تحویل دیجیتال از شارژینو.',
  ];
  for (const pad of pads) {
    if (next.length >= DESC_MIN) break;
    const token = compact(pad).replace(/[.،؛]+$/u, '');
    const stem = token.split(' ').slice(0, 2).join(' ');
    if (stem && next.includes(stem)) continue;
    if (`${next}${pad}`.length <= DESC_MAX) {
      next += pad;
    }
  }
  return clipMetaDescription(next);
}

export function productKindLabel(product: SeoTitleProduct) {
  const family = familyOf(product);
  if (family === 'telegram-stars') return 'استارز';
  if (family === 'telegram-premium') return 'پرمیوم';
  return TYPE_RULES[family]?.kindFa || '';
}

export function productPlatformLabel(product: SeoTitleProduct) {
  const key = (product.platform ?? '').trim().toLowerCase();
  if (key) {
    const ranked = [...SEO_BRANDS].sort((left, right) => right.id.length - left.id.length);
    for (const brand of ranked) {
      if (brand.id === key || brand.aliases.some((alias) => alias.toLowerCase() === key)) {
        return brand.fa;
      }
    }
  }
  return matchSeoBrand(product)?.fa || '';
}

function handleStemTokens(handle?: string | null) {
  return handleTokenList(handle).filter((token) => {
    const key = token.toLowerCase();
    if (HANDLE_STOP.has(key) || SEO_SKIP_REGIONS.has(key) || SEO_REGIONS[key] || VERSION_LABELS[key]) return false;
    if (SLUG_KEPT.has(key) || /^\d+(-\d+)*$/.test(key)) return false;
    return /^[a-z][a-z0-9]{2,}$/i.test(key);
  });
}

export function headingHasDuplicateWords(heading: string) {
  const seen = new Set<string>();
  for (const token of compact(heading).split(' ').filter(Boolean)) {
    const hasParen = /[()]/.test(token);
    const key = normalizeToken(token);
    if (!key) continue;
    if (!hasParen) {
      if (seen.has(key)) return true;
      seen.add(key);
    }
  }
  return false;
}

export function headingHasSlugToken(heading: string, product: SeoTitleProduct) {
  const allowed = new Set(
    compact([product.title, product.titleEn, product.subtitle, catalogDistinguisher(product)].filter(Boolean).join(' '))
      .split(/[^A-Za-z0-9\u0600-\u06FF]+/)
      .filter(Boolean)
      .map(normalizeToken),
  );
  const headingKeys = new Set(
    compact(heading)
      .replace(/[()]/g, ' ')
      .split(' ')
      .filter(Boolean)
      .map(normalizeToken),
  );
  for (const token of handleStemTokens(product.handle)) {
    const key = normalizeToken(token);
    if (!key || allowed.has(key)) continue;
    if (headingKeys.has(key)) return true;
  }
  return false;
}

export function productHeadingIssues(product: SeoTitleProduct, heading = buildProductSeoHeading(product)) {
  const issues: string[] = [];
  if (headingHasDuplicateWords(heading)) issues.push('duplicate-word');
  if (headingHasSlugToken(heading, product)) issues.push('slug-token');
  if (heading.includes('…') && !heading.endsWith('…')) issues.push('mid-ellipsis');
  return issues;
}

function pricePhrase(product: SeoTitleProduct) {
  const amount = tomanPrice(product.minPrice);
  if (amount > 0) {
    return `از ${formatTomanNumber(amount)} تومان`;
  }
  return 'با قیمت تومانی';
}

export function buildSteamGiftSeoDescription(product: SeoTitleProduct) {
  const stored = storedSeoDescription(product);
  if (stored) {
    return fillMetaDescription(stored);
  }
  const paid = steamGiftPaidItem(product);
  const name = compact([steamGiftGameName(product), paid].filter(Boolean).join(' '));
  const lead = `گیفت استیم ${name} را ${pricePhrase(product)} با تحویل فوری از شارژینو بخرید.`;
  const extra = ' پرداخت ریالی است و پشتیبانی پاسخگوست.';
  const more = ` بازی به کتابخانه استیم ارسال می‌شود.`;
  let text = lead;
  if (`${text}${extra}`.length <= STEAM_GIFT_DESC_MAX) {
    text += extra;
  }
  if (text.length < STEAM_GIFT_DESC_MIN && `${text}${more}`.length <= STEAM_GIFT_DESC_MAX) {
    text += more;
  }
  return fillMetaDescription(text);
}

export function editorialProductTitle(product: SeoTitleProduct) {
  const raw = product.buyGuide?.seo?.title?.trim() ?? '';
  return compact(stripForbidden(raw.replace(BRAND_SUFFIX, '')));
}

export function productsMissingPersianName(product: SeoTitleProduct) {
  if (isSteamGiftProduct(product)) return false;
  if (editorialProductTitle(product)) return false;
  const brand = matchSeoBrand(product);
  return !brand?.fa && !brand?.unitFa;
}

function handleQualifier(handle?: string | null) {
  const raw = (handle ?? '').toLowerCase();
  if (/(^|-)reserve(-|$)/.test(raw)) return 'رزرو';
  return '';
}

function generatedProductHeading(product: SeoTitleProduct) {
  if (isSteamGiftProduct(product)) {
    return buildSteamGiftSeoHeading(product);
  }
  const brand = matchSeoBrand(product);
  const rule = typeRule(product, brand);
  const nameFa =
    brand?.fa ||
    (rule.kindFa && brand?.unitFa && brand.unitFa !== rule.kindFa ? brand.unitFa : '');
  const extra = catalogDistinguisher(product);
  const english = englishParen(brand, product, nameFa);
  const lead = uniqueTokens([rule.verb, rule.kindFa, nameFa].filter(Boolean).join(' '));
  const englishUsed = nameFa ? english : english || fallbackEnglish(product);
  const core = nameFa
    ? (() => {
        const shortened = shortenEnglish(englishUsed, lead, extra);
        return shortened ? `${lead} (${shortened})` : lead;
      })()
    : uniqueTokens([lead, englishUsed].join(' '));
  const titled = appendDistinguisher(core, extra);
  return uniqueTokens(stripForbidden(titled) || fallbackEnglish(product) || product.title.trim());
}

function productInnerHeading(product: SeoTitleProduct) {
  if (isSteamGiftProduct(product)) {
    return buildSteamGiftSeoHeading(product);
  }
  const extra = catalogDistinguisher(product);
  const stored = storedSeoTitle(product);
  return stored ? uniqueTokens(appendDistinguisher(stored, extra)) : generatedProductHeading(product);
}

export function buildProductSeoTitle(product: SeoTitleProduct) {
  if (isSteamGiftProduct(product)) {
    return buildSteamGiftSeoTitle(product);
  }
  const extra = catalogDistinguisher(product);
  return fitDocumentTitle(productInnerHeading(product), extra);
}

export function buildProductDocumentTitle(product: SeoTitleProduct) {
  return finalizeDocumentTitle(productInnerHeading(product), catalogDistinguisher(product));
}

export function buildProductSeoHeading(product: SeoTitleProduct) {
  if (isSteamGiftProduct(product)) {
    return buildSteamGiftSeoHeading(product);
  }
  const extra = catalogDistinguisher(product);
  const stored = storedSeoTitle(product);
  if (stored) {
    return uniqueTokens(
      appendDistinguisher(headingFromSeoTitle(stored) || generatedProductHeading(product), extra),
    );
  }
  return generatedProductHeading(product);
}

export function buildProductSeoDescription(product: SeoTitleProduct) {
  const stored = storedSeoDescription(product);
  if (stored) {
    const kind = productKindLabel(product);
    const platform = productPlatformLabel(product);
    const extra = uniqueTokens([kind, platform].filter(Boolean).join(' '));
    const next = extra && !stored.includes(kind) ? compact(`${stored} ${extra}.`) : stored;
    return fillMetaDescription(next);
  }
  if (isSteamGiftProduct(product)) {
    return buildSteamGiftSeoDescription(product);
  }
  const heading = headingFromSeoTitle(buildProductSeoHeading(product));
  const brand = matchSeoBrand(product);
  const name = compact(brand?.fa || heading.replace(/^(خرید|شارژ)\s+/u, ''));
  const region = compact(seoRegionLabel(product.region) || inferredRegionLabel(product));
  const kind = productKindLabel(product);
  const platform = productPlatformLabel(product);
  const extra = catalogDistinguisher(product);
  const feature =
    extra && extra !== region
      ? extra
      : familyOf(product) === 'giftcard'
        ? 'کد ردیم دیجیتال است'
        : familyOf(product) === 'gamekey'
          ? 'کلید بازی است'
          : familyOf(product) === 'telegram-stars'
            ? 'استارز تلگرام است'
            : 'شارژ با شناسه بازیکن است';
  const amount = tomanPrice(product.minPrice);
  const fromPrice = amount > 0 ? `از ${formatTomanNumber(amount)} تومان` : 'با قیمت تومانی';
  const core = compact(
    `خرید ${kind} ${name} ${platform} ${region} ${fromPrice}، تحویل فوری، بدون رمز اکانت. ${feature}.`,
  );
  return fillMetaDescription(core);
}

export function buildCategorySeoHeading(categoryTitle?: string | null, slug?: string | null) {
  const hub = brandHubBySlug(slug);
  if (hub) {
    return compact(`خرید ${hub.fa} با تومان`);
  }
  const mapped = SEO_CATEGORIES.find((item) => item.slug === slug)?.fa;
  const raw = compact(mapped || categoryTitle || '');
  if (!raw) return 'خرید شارژ بازی و گیفت کارت';
  if (/^(خرید|شارژ)(\s|$)/u.test(raw)) {
    return stripForbidden(uniqueTokens(raw.startsWith('شارژ بازی') ? `خرید ${raw}` : raw));
  }
  return stripForbidden(uniqueTokens(`خرید ${raw}`));
}

export function buildCategorySeoTitle(
  categoryTitle?: string | null,
  slug?: string | null,
  seoTitle?: string | null,
) {
  const hub = brandHubBySlug(slug);
  if (hub) {
    const candidates = [
      `خرید ${hub.fa} (${hub.en}) با تومان | تحویل فوری`,
      `خرید ${hub.fa} (${hub.en}) با تومان`,
      `خرید ${hub.fa} با تومان | تحویل فوری`,
      `خرید ${hub.fa} با تومان`,
    ].map(compact);
    const fitting = candidates.find((item) => titleFitsDocument(item));
    if (fitting) return fitting;
    return fitDocumentTitle(candidates[3], hub.fa);
  }
  const stored = compact(seoTitle ?? '');
  if (stored) {
    return stripForbidden(stripBrandSuffix(stored));
  }
  return fitDocumentTitle(buildCategorySeoHeading(categoryTitle, slug));
}

export function buildCategorySeoDescription(input: {
  title?: string | null;
  slug?: string | null;
  seoDescription?: string | null;
}) {
  const stored = compact(input.seoDescription ?? '');
  if (stored) {
    return fillMetaDescription(stored);
  }
  const heading = buildCategorySeoHeading(input.title, input.slug);
  const bySlug: Record<string, string> = {
    'game-topup':
      'شارژ بازی با تومان و تحویل فوری: یوسی پابجی، سی‌پی کالاف و الماس فری فایر با Player ID. پشتیبانی شارژینو پاسخگوست؛ پرداخت ریالی بدون رمز اکانت.',
    'gift-card':
      'خرید گیفت کارت با تومان و تحویل فوری؛ پلی‌استیشن، استیم و گوگل پلی. پشتیبانی شارژینو پاسخگوست؛ پرداخت ریالی بدون رمز اکانت فروشگاه.',
    'game-keys':
      'خرید کلید بازی استیم و کنسول را با تومان و تحویل فوری از شارژینو بخرید. پشتیبانی پاسخگوست؛ پرداخت ریالی بدون رمز اکانت لانچر.',
    telegram:
      'خرید پرمیوم و استارز تلگرام با تومان و تحویل فوری. پشتیبانی شارژینو پاسخگوست؛ فقط یوزرنیم عمومی لازم است و پرداخت ریالی است.',
    'steam-wallet':
      'شارژ کیف پول استیم را با تومان و تحویل فوری انجام دهید. پشتیبانی شارژینو پاسخگوست؛ پرداخت ریالی بدون رمز ورود و Steam Guard.',
    'steam-gifts':
      'خرید گیفت استیم با تومان و تحویل فوری به کتابخانه استیم. پشتیبانی شارژینو پاسخگوست؛ پرداخت ریالی بدون رمز اکانت گیرنده.',
  };
  const slug = input.slug?.trim() ?? '';
  if (bySlug[slug]) {
    return fillMetaDescription(bySlug[slug]);
  }
  return fillMetaDescription(
    `${heading} را با تومان و تحویل فوری از شارژینو بخرید. پشتیبانی پاسخگوست؛ پرداخت ریالی بدون رمز اکانت.`,
  );
}
