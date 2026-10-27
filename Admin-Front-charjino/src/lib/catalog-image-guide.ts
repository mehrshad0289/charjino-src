const SQUARE = 'مربع ۶۰۰×۶۰۰ پیکسل، نسبت ۱:۱';
const STEAM = 'افقی ۹۲۰×۴۳۰ پیکسل، نسبت ۴۶۰:۲۱۵';

const BY_SLUG: Record<string, string> = {
  'steam-gifts': `استاندارد گیفت استیم: ${STEAM}. کارت فروشگاه همین نسبت را می‌پوشاند.`,
  'game-keys': `استاندارد کلید بازی: ${STEAM}، یا ${SQUARE}.`,
  'game-topup': `استاندارد شارژ بازی: ${SQUARE}.`,
  'gift-card': `استاندارد گیفت‌کارت: ${SQUARE}.`,
  telegram: `استاندارد تلگرام: ${SQUARE}.`,
  'steam-wallet': `استاندارد شارژ استیم: ${SQUARE}.`,
  'manual-services': `استاندارد سرویس دستی: ${SQUARE}.`
};

const BY_FULFILLMENT: Record<string, string> = {
  'steam-gift': BY_SLUG['steam-gifts'],
  gamekey: BY_SLUG['game-keys'],
  'game-key': BY_SLUG['game-keys'],
  topup: BY_SLUG['game-topup'],
  'game-topup': BY_SLUG['game-topup'],
  giftcard: BY_SLUG['gift-card'],
  'gift-card': BY_SLUG['gift-card'],
  'telegram-stars': BY_SLUG.telegram,
  'telegram-premium': BY_SLUG.telegram,
  'steam-topup': BY_SLUG['steam-wallet'],
  manual: BY_SLUG['manual-services']
};

export const HOME_BANNER_GUIDE =
  'استاندارد بنر صفحهٔ خانه با اولویت موبایل: ۱۲۸۰×۷۲۰ پیکسل، نسبت ۱۶:۹. فروشگاه کل فایل را نشان می‌دهد؛ در موبایل و دسکتاپ برش نمی‌خورد.';

export function catalogImageGuide(slug?: string | null, fulfillmentType?: string | null) {
  if (slug && BY_SLUG[slug]) return BY_SLUG[slug];
  if (fulfillmentType && BY_FULFILLMENT[fulfillmentType]) return BY_FULFILLMENT[fulfillmentType];
  return `استاندارد تصویر محصول: ${SQUARE}.`;
}
