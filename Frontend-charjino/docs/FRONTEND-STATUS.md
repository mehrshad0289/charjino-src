# وضعیت فرانت فروشگاه شارژینو

> به‌روز شده: ۲۶ سپتامبر ۲۰۲۶  
> پروژه: `Frontend-charjino` (`:3001`) · API: `BACKEND_URL` → Laravel `/api`  
> هم‌خانواده: `Admin-Front-charjino/docs/FRONTEND-STATUS.md`

---

## این مرحله از فرانت چه شد

### خرید واقعی (دیگر «به‌زودی» نیست)

جریان فروشگاه: ورود OTP → بررسی بازیکن → `POST /orders` (هدر `Idempotency-Key`) → `POST /orders/{number}/pay` → هدایت به درگاه mock → بازگشت به `/invoices/{number}`.

رمز بازی / OTP بازی هرگز پرسیده نمی‌شود. VPN یا فیلترشکن در محصول نیست.

| صفحه / قابلیت | API | وضعیت |
|----------------|-----|--------|
| هوم / دسته / محصول / جستجو | catalog | متصل |
| بررسی بازیکن | `POST /checkout/validate-player` | متصل |
| ثبت سفارش | `POST /orders` | متصل |
| شروع پرداخت | `POST /orders/{number}/pay` | متصل — درگاه mock |
| بازگشت درگاه | `GET /payments/callback/mock` | ۳۰۲ به `/invoices/{number}?from=psp` |
| فهرست سفارش | `GET /orders` | متصل — `/invoices` |
| جزئیات سفارش | `GET /orders/{number}` | متصل — `/invoices/[invoice]` |
| سلامت / قفل تحویل | `GET /health` → `liveOrderEnabled` | بنر «تحویل آزمایشی» وقتی `ALLOW_LIVE_ORDER=no` |

قیمت فقط تومان است. `costUsd` نشان داده نمی‌شود.

خطاهای `PLAYER_NOT_FOUND`، `REGION_MISMATCH`، `PRICE_CHANGED` / `PRICE_INCREASED` با متن فارسی ثابت نمایش داده می‌شوند.

---

## مانده

| مورد | الان |
|------|------|
| درگاه پرداخت واقعی (زرین‌پال و مشابه) | mock فقط وقتی `APP_ENV !== production` |
| تحویل زنده تأمین‌کننده | قفل با `ALLOW_LIVE_ORDER=no` — عمداً تغییر داده نشد |
| لیدربورد | «به‌زودی» بدون دادهٔ جعلی |
| denomination عمومی | فقط ارزان‌ترین UC و CP تا تست زنده سبز شود |

---

## خرید آزمایشی end-to-end (mock، live قفل)

`ALLOW_LIVE_ORDER` را **yes نکن**. مسیر ثبت‌شده در تست و UI:

1. ورود OTP (`DEV_OTP_CODE=123456` روی استیجینگ/لوکال).
2. صفحهٔ محصول → شناسه بازیکن → «بررسی بازیکن» → نام بازیکن.
3. «پرداخت و ثبت سفارش» → `draft` سپس `pending_payment`.
4. مرورگر به `GET /api/payments/callback/mock?authority=…&status=OK` می‌رود.
5. بک‌اند پرداخت را تأیید می‌کند، fulfill خشک (`dry_run=true`) می‌زند، بعد ۳۰۲ به `/invoices/{CH…}?from=psp`.
6. صفحهٔ فاکتور وضعیت را از `GET /orders/{number}` می‌خواند (دیگر متن «وضعیت واقعی نیست» نیست).
7. تا وقتی live قفل است، UI می‌گوید تحویل آزمایشی است و به تأمین‌کننده ارسال نشده.

قرارداد خودکار همین مسیر را قفل می‌کند:

```powershell
cd C:\Users\Asus\Desktop\charjino\Backend-charjino
php artisan test --filter=OrderHappyPathTest
```

انتظار: validate → سفارش → pay → callback mock → `fulfilling` با `dryRun=true` → webhook آزمایشی `delivered`؛ به‌علاوه GET مرورگر به فاکتور فروشگاه ریدایرکت می‌شود.

---

## لوکال

```powershell
cd C:\Users\Asus\Desktop\charjino\Frontend-charjino
# BACKEND_URL=http://127.0.0.1:3000/api
npm run dev
```

Laravel روی `:3000`. `SHOP_URL=http://127.0.0.1:3001` برای بازگشت درگاه.
