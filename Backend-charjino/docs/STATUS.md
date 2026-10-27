# وضعیت شارژینو — ۲۶ سپتامبر ۲۰۲۶

> یک صفحه. رازها (`S2T_KEY`) اینجا نیستند. `ALLOW_LIVE_ORDER` را yes نکن مگر تأیید صریح + کیف پول شارژشده.

## Production-ready (بدون سفارش زنده تأمین)

- فروشگاه فارسی RTL (`lang=fa` `dir=rtl`) با توکن تیرهٔ طرح `ui ux مورد نظر`.
- کاتالوگ عمومی، بررسی بازیکن، سفارش، درگاه mock، فاکتور، خطاهای فارسی (`PLAYER_NOT_FOUND` / `REGION_MISMATCH` / `PRICE_CHANGED`).
- ادمین: گروه/محصول/SKU، سفارش، داشبورد، تأمین، آلارم موجودی (`supplierWalletLow`).
- Adapter: `GET /account`، sku-map PUBG+COD، `requirements`، validate، UUID در `fulfillment_attempts` **قبل از** `orders/create`، وب‌هوک HMAC + poll `charjino:poll-fulfillment`.
- فروش عمومی denomination: فقط ارزان‌ترین **۱۰ UC** (`item_id` ۱۲) و **۱۶۰ CP** (`item_id` ۱۰۰۲). بقیهٔ پک‌های Shop2 و پک‌های اضافهٔ سید مخفی می‌مانند تا تست زنده سبز شود.
- خط قرمز: بدون VPN/فیلترشکن، بدون رمز بازی، بدون کش‌اوت، بدون کوین نتیجه‌محور / جعبه شانس خریدنی.

## قفل `ALLOW_LIVE_ORDER` (فعلاً `no`)

| مورد | وضعیت |
|------|--------|
| `POST` تأمین `orders/create` | قفل — fulfillment خشک (`dryRun=true`) |
| تست واقعی ارزان UC و CP | **اجرا نشد** — تأیید صریح کاربر نبود |
| باز کردن بقیهٔ denomination | قفل تا تست زنده سبز |
| درگاه واقعی PSP | mock وقتی `APP_ENV !== production` |

## Smoke زنده (بدون create)

`php artisan charjino:s2t-smoke` — `GET /account` موفق، کیف پول **۰.۰۰۰۰۰۰ USD**، sku-map MVP `item_id` ۱۲ و ۱۰۰۲، `requirements` هر دو category یک فیلد (`player_id`). سفارش زنده زده نشد؛ موجودی برای UC/CP واقعی کافی نیست.

OpenAPI `0.1.7` → `npm run api:types` در Frontend و Admin؛ `supplierWalletLow` در هر دو `api.gen.ts` هم‌خوان است.
