import { catalogRegionLabel } from '@/lib/catalog-region';
import { ROUTES } from '@/lib/routes';

export type CodmRegionStatus = 'match' | 'mismatch' | 'unknown';

export type CodmRegionPayload = {
  status?: CodmRegionStatus | string | null;
  variantCountry?: string | null;
  nickname?: string | null;
  shortId?: string | null;
  accountCountry?: string | null;
  suggestedVariant?: string | null;
  suggestedHandle?: string | null;
  variantHandle?: string | null;
};

export const CODM_VARIANT_HANDLES: Record<string, string> = {
  US: 'cod-mobile',
  CA: 'topup-codm-activision-ca',
  IN: 'topup-codm-activision-in',
  KZ: 'topup-codm-activision-kz',
  SA: 'topup-codm-activision-sa',
};

export function codmCountryFa(code?: string | null) {
  const raw = typeof code === 'string' ? code.trim() : '';
  if (!raw) return '';
  return catalogRegionLabel(raw) || raw.toUpperCase();
}

export function codmSuggestedHref(payload?: CodmRegionPayload | null) {
  const handle =
    payload?.suggestedHandle?.trim() ||
    CODM_VARIANT_HANDLES[String(payload?.suggestedVariant ?? '').toUpperCase()] ||
    '';
  return handle ? ROUTES.catalog(handle) : null;
}

export function codmMatchCaption(payload?: CodmRegionPayload | null) {
  const name = payload?.nickname?.trim();
  const shortId = payload?.shortId?.trim();
  const region = codmCountryFa(payload?.accountCountry || payload?.variantCountry);
  if (!name) return region ? `اکانت تأیید شد — ریجن ${region}` : 'اکانت تأیید شد';
  const idPart = shortId ? ` (کد ${shortId})` : '';
  const regionPart = region ? ` — ریجن ${region}` : '';
  return `اکانت تأیید شد: ${name}${idPart}${regionPart}`;
}

export function codmMismatchCaption(payload?: CodmRegionPayload | null) {
  const account = codmCountryFa(payload?.accountCountry) || payload?.accountCountry || '';
  const variant = codmCountryFa(payload?.variantCountry) || payload?.variantCountry || '';
  if (payload?.suggestedVariant) {
    const suggest = codmCountryFa(payload.suggestedVariant);
    return `این اکانت مربوط به ریجن ${account} است و این محصول برای ریجن ${variant} است. برای این اکانت از محصول «سی‌پی کالاف دیوتی موبایل — ${suggest}» استفاده کنید.`;
  }
  return `این اکانت در ریجن ${account} ثبت شده و شارژ با شناسه (UID) برای این ریجن در شارژینو امکان‌پذیر نیست. لطفاً خرید نکنید؛ برای راهنمایی با پشتیبانی تماس بگیرید.`;
}

export function isCodmRegionCheck(product: {
  purchaseSchema?: { validation?: { mode?: string } } | null;
  codm?: { regionCheck?: boolean } | null;
}) {
  return product.purchaseSchema?.validation?.mode === 'codm_store' || Boolean(product.codm?.regionCheck);
}
