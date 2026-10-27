import type {
  BuyGuide,
  BuyGuideFaq,
  BuyGuideNote,
  BuyGuideSection,
  BuyGuideSource,
  BuyGuideStep,
} from '@/types/buy-guide';

const BLOCKED_HOSTS = new Set([
  'iranmojo.com',
  'iranicard.ir',
  'gifteto.com',
  'igift24.net',
  'giftro.ir',
  'play360.shop',
  'cdkey.ir',
  'nikogem.com',
  'matrixgame.ir',
  'mojogift.com',
  'seagm.com',
]);

const INTERNAL =
  /رقیب|فایل داخلی|fazercards|\bfazer\b|iranmojo|iranicard|gifteto|igift24|\b(?:GET|POST)\s+\/api|\bfast\b.{0,120}\breserve\b|\breserve\b.{0,120}\bfast\b|pubg-mobile-manual|\*-manual|\*-login|محصولات لاگینی|محصولات کاتالوگ که این راهنما|طبق مستندات تأمین|charjino\.ir\/catalog\//iu;

export function stripGuideMarks(text: string): string {
  return text.replace(/[*`]/g, '').trim();
}

export function isInternalGuideCopy(text: string): boolean {
  return INTERNAL.test(text);
}

function hostOf(url: string): string {
  try {
    return new URL(url).hostname.replace(/^www\./i, '').toLowerCase();
  } catch {
    return '';
  }
}

export function isCustomerGuideSource(url: string, label = ''): boolean {
  if (!url.trim() || isInternalGuideCopy(`${label} ${url}`)) {
    return false;
  }
  const host = hostOf(url);
  if (!host) {
    return false;
  }
  if (BLOCKED_HOSTS.has(host)) {
    return false;
  }
  for (const blocked of BLOCKED_HOSTS) {
    if (host.endsWith(`.${blocked}`)) {
      return false;
    }
  }
  return true;
}

function decodeEntities(text: string): string {
  return text
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;|&apos;/g, "'");
}

function plainFromHtml(html: string): string {
  const withBreaks = html.replace(/<br\s*\/?>/gi, '\n');
  return stripGuideMarks(decodeEntities(withBreaks.replace(/<[^>]+>/g, ' ')).replace(/\s+\n/g, '\n').trim());
}

export function presentIntroHtml(html?: string | null): string | null {
  const raw = html?.trim() ?? '';
  if (!raw) {
    return null;
  }
  const blocks = [...raw.matchAll(/<p\b[^>]*>([\s\S]*?)<\/p>/gi)];
  const paragraphs = (blocks.length ? blocks.map((match) => match[1]) : [raw])
    .map((chunk) => plainFromHtml(chunk))
    .filter((text) => text && !isInternalGuideCopy(text));
  if (paragraphs.length === 0) {
    return null;
  }
  return paragraphs.map((text) => `<p>${escapeHtml(text).replace(/\n/g, '<br>')}</p>`).join('');
}

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function stripMarksDeep<T>(value: T): T {
  if (typeof value === 'string') {
    return stripGuideMarks(value) as T;
  }
  if (!value || typeof value !== 'object') {
    return value;
  }
  if (Array.isArray(value)) {
    return value.map((item) => stripMarksDeep(item)) as T;
  }
  const out: Record<string, unknown> = {};
  for (const [key, item] of Object.entries(value)) {
    if (['url', 'src', 'srcset', 'href', 'type', 'source', 'product_handle'].includes(key) && typeof item === 'string') {
      out[key] = item;
      continue;
    }
    out[key] = stripMarksDeep(item);
  }
  return out as T;
}

export function presentBuyGuide(guide?: BuyGuide | null): BuyGuide | null {
  if (!guide) {
    return null;
  }
  const summary = stripGuideMarks(guide.summary ?? '');
  const sections = (guide.sections ?? [])
    .map((section) => presentSection(section))
    .filter((section): section is BuyGuideSection => Boolean(section));
  return {
    ...guide,
    summary: summary && !isInternalGuideCopy(summary) ? summary : null,
    introHtml: presentIntroHtml(guide.introHtml),
    sections,
  };
}

function presentSection(section: BuyGuideSection): BuyGuideSection | null {
  if (section.type === 'sources') {
    const items = ((section.items ?? []) as BuyGuideSource[]).filter((item) =>
      isCustomerGuideSource(item.url, item.label ?? ''),
    );
    if (items.length === 0) {
      return null;
    }
    return stripMarksDeep({ ...section, items });
  }
  if (section.type === 'faq') {
    const items = ((section.items ?? []) as BuyGuideFaq[]).filter((item) => {
      const q = stripGuideMarks(item.q ?? '');
      const a = stripGuideMarks(item.a ?? '');
      return q && a && !isInternalGuideCopy(`${q} ${a}`);
    });
    if (items.length === 0) {
      return null;
    }
    return stripMarksDeep({ ...section, items });
  }
  if (section.type === 'before_buy') {
    const notes = (section.notes ?? []).filter((note: BuyGuideNote) => {
      const text = stripGuideMarks(note.text ?? '');
      return text && !isInternalGuideCopy(text);
    });
    if (notes.length === 0) {
      return null;
    }
    return stripMarksDeep({ ...section, notes });
  }
  return stripMarksDeep(section);
}

export function hasStructuredBuyGuide(guide?: BuyGuide | null): boolean {
  const presented = presentBuyGuide(guide);
  if (!presented) return false;
  if (presented.introHtml?.trim()) return true;
  if ((presented.sections ?? []).length > 0) return true;
  return false;
}

export function hasLegacyBuyGuide(guide?: BuyGuide | null): boolean {
  return Boolean(guide?.legacyItems && guide.legacyItems.length > 0 && !hasStructuredBuyGuide(guide));
}

export function sectionOf(guide: BuyGuide | null | undefined, type: string): BuyGuideSection | undefined {
  return presentBuyGuide(guide)?.sections?.find((item) => item.type === type);
}

export function faqItems(guide?: BuyGuide | null): BuyGuideFaq[] {
  const section = sectionOf(guide, 'faq');
  return (section?.items ?? []).filter((item): item is BuyGuideFaq => {
    return Boolean(item && typeof item === 'object' && 'q' in item && 'a' in item);
  });
}

export function howToSteps(guide?: BuyGuide | null): { name: string; steps: BuyGuideStep[] } | null {
  const presented = presentBuyGuide(guide);
  const redeem = sectionOf(presented, 'redeem');
  const tab = redeem?.tabs?.[0];
  if (tab && tab.steps?.length) {
    return { name: tab.label || 'فعال‌سازی', steps: tab.steps };
  }
  const purchase = sectionOf(presented, 'purchase_steps');
  if (purchase?.steps?.length) {
    return { name: 'مراحل خرید', steps: purchase.steps };
  }
  return null;
}
