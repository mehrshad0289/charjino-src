export const CUSTOMER_ERROR_MESSAGES: Record<string, string> = {
  PLAYER_NOT_FOUND: 'شناسهٔ بازیکن پیدا نشد.',
  REGION_MISMATCH: 'منطقهٔ این بازیکن با این بسته هم‌خوان نیست.',
  CODM_REGION_MISMATCH: 'ریجن این اکانت کالاف با این محصول یکی نیست.',
  PLAYER_ID_INVALID: 'UID باید فقط عدد باشد (حدود ۲۰ رقم، زیر نیک‌نیم در Player Profile بازی).',
  PRICE_CHANGED: 'قیمت این بسته تغییر کرده است. صفحه را تازه کن و دوباره بخر.',
  PRICE_INCREASED: 'قیمت این بسته تغییر کرده است. صفحه را تازه کن و دوباره بخر.',
  MISSING_REQUIRED_FIELD: 'شناسهٔ بازیکن را کامل وارد کن.',
  FORBIDDEN_BUYER_FIELD: 'فقط شناسهٔ بازیکن لازم است. رمز بازی را وارد نکن.',
  ID_VALIDATION_UNSUPPORTED: 'این بازی اعتبارسنجی خودکار ندارد. شناسه را دوباره بررسی کن.',
  OUT_OF_STOCK: 'این بسته فعلاً موجود نیست.',
  PRODUCT_UNAVAILABLE: 'این بسته فعلاً موجود نیست.',
  SKU_UNAVAILABLE: 'این بسته برای فروش فعال نیست.',
  IDEMPOTENCY_CONFLICT: 'این کلید قبلاً برای سفارش دیگری استفاده شده است.',
  ORDER_NOT_PAYABLE: 'این سفارش قابل پرداخت نیست.',
  PAYMENT_EXPIRED: 'مهلت پرداخت این سفارش تمام شده است.',
  PAYMENT_FAILED: 'پرداخت ناموفق بود.',
  PAYMENT_NOT_FOUND: 'پرداخت پیدا نشد.',
  PSP_UNAVAILABLE: 'درگاه پرداخت در دسترس نیست.',
  SHOP_PAYMENT_REQUIRED: 'تحویل زنده فقط بعد از پرداخت در فروشگاه انجام می‌شود.',
  LIVE_ORDER_LOCKED: 'تحویل زنده غیرفعال است. این خرید آزمایشی است.',
  INVALID_QUANTITY: 'تعداد این بسته مجاز نیست.',
  AMOUNT_OUT_OF_RANGE: 'مبلغ واردشده در بازهٔ مجاز نیست.',
  AMOUNT_DECIMALS: 'تعداد رقم اعشار این مبلغ مجاز نیست.',
  INVALID_FORMAT: 'قالب این فیلد درست نیست.',
  ID_NOT_CONFIRMED: 'شناسه تأیید نشد؛ دوباره بررسی کن یا با پشتیبانی تماس بگیر',
  ID_ACK_REQUIRED: 'نسخه و شناسه را تأیید کن.',
  REGION_ACK_REQUIRED: 'ریجن حساب استیم باید با همین محصول یکی باشد.',
  STEAM_LOGIN_CANNOT_REFILL: 'این لاگین استیم قابل شارژ نیست.',
  SUPPLIER_BLOCKED: 'اتصال به تأمین‌کننده ممکن نیست. پشتیبانی شارژینو پیگیری می‌کند.',
  SUPPLIER_RATE_LIMITED: 'تعداد درخواست بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.',
  SUPPLIER_TIMEOUT: 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.',
  SUPPLIER_5XX: 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.',
  SUPPLIER_REJECTED: 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.',
  INVALID_INVITE_LINK: 'لینک دعوت استیم نامعتبر است.',
  TELEGRAM_USERNAME_INVALID: 'نام کاربری تلگرام نامعتبر است.',
  TEMPORARILY_UNAVAILABLE: 'این محصول فعلاً قابل خرید نیست.',
  FAZER_FAILED: 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.',
  WALLET_INSUFFICIENT: 'موجودی کیف پول شارژینو کافی نیست.',
  COUPON_NOT_FOUND: 'کد تخفیف معتبر نیست.',
  COUPON_EXPIRED: 'کد تخفیف منقضی شده است.',
  COUPON_INACTIVE: 'این کد تخفیف غیرفعال است.',
  COUPON_USAGE_LIMIT: 'سهمیهٔ استفاده از این کد تخفیف تمام شده است.',
  COUPON_MIN_ORDER: 'مبلغ خرید به حداقل این کد تخفیف نمی‌رسد.',
  COUPON_INVALID: 'این کد تخفیف برای این سفارش قابل استفاده نیست.',
  RATE_LIMIT_EXCEEDED: 'تعداد درخواست بیش از حد مجاز است.',
};

const GENERIC = 'سفارش انجام نشد. پشتیبانی شارژینو پیگیری می‌کند.';

export function customerErrorMessage(code?: string | null, fallback?: string) {
  if (code) {
    const mapped = CUSTOMER_ERROR_MESSAGES[code.toUpperCase()];
    if (mapped) return mapped;
  }
  const text = (fallback ?? '').trim();
  if (text && /^[A-Za-z]/.test(text)) return GENERIC;
  if (text) return text;
  return GENERIC;
}
