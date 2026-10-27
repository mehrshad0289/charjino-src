# Runbook فید ترب v3 (split-by-SKU)

تاریخ به‌روز: 2026-10-26 22:47 Asia/Tehran  
سرور: `/var/www/charjino` — دیپلوی فقط با `docs-charjino/deploy/charjino/deploy-backend.ps1`.

## وضعیت نهایی (پس از این نوبت)

| معیار | مقدار |
|---|---|
| محصولات فعال | **۵۲** = ۵۱ مرحلهٔ ۱ + `55812` تلگرام ستاره |
| ردیف فید | **۵۰٬۶۲۶** (قبل از نوبت ۵۰٬۶۱۵) |
| baseline / سقف | ۵۰٬۰۳۴ / ۷۵٬۰۵۱ (×1.012 vs baseline) |
| `verify-feed` | سبز — `20261026-224711.json` rows=653 |
| مرحلهٔ service-topup | **فعال نشد**؛ enable شد، verify قرمز، **disable فوری** |
| مرحلهٔ gift-card remaining | **اجرا نشد** (توقف پس از شکست مرحلهٔ قبل) |
| پرمیوم تلگرام ۵۵۸۱۳ / steam-gifts / Manual Services | هرگز |

Canonical و page_unique پنجاه‌ویک محصول مرحلهٔ ۱ با snapshot قبل از مرحلهٔ ۲ **یکسان** ماند.

Snapshot: `storage/app/torob/rollout/SNAPSHOT-BEFORE-STAGE2.json` (و کپی در `database/data/torob/rollout/`).

## دستورهای عملیاتی

همه روی production، از `/var/www/charjino`. `--force` فقط دروازهٔ نوشتن است؛ معافیت را دور نمی‌زند.

```bash
php artisan torob:split-by-sku status
php artisan torob:verify-feed --sample=3

# فعال‌سازی (فقط پس از تأیید و dry-run سبز)
php artisan torob:split-by-sku enable --file=<stage>.json --dry-run --json
php artisan torob:split-by-sku enable --file=<stage>.json --force
php artisan torob:split-by-sku freeze-canonical --file=<stage>.json --force
php artisan torob:split-by-sku flush-cache --force
php artisan torob:verify-feed --ids=<csv> --sample=3

# بازگشت یک مرحله
php artisan torob:split-by-sku disable --file=<stage>.json --force
php artisan torob:split-by-sku flush-cache --force
php artisan torob:verify-feed --sample=3
```

بازگشت تلگرام ستاره (فایل در `database/data/torob/rollout/telegram-stars-stage-001.json`):

```bash
php artisan torob:split-by-sku disable --file=telegram-stars-stage-001.json --force
```

**ممنوع:** `php artisan cache:clear` و `cache:flush` سراسری. فقط `flush-cache` که `charzhyno-cache-torob:v3:*` را می‌زند.

فید HTTP ترب **POST** است (`/api/torob/v3/products`)، نه GET. JWT ترب را این پروژه امضا نمی‌کند؛ بررسی صفحه از مسیر کنترلر پس از JWT یا `TorobProductQueryService::paginated` است.

## ساختار فایل مرحله

مسیر خواندن: `storage/app/torob/rollout/` سپس `database/data/torob/rollout/`.

```json
{
  "scope": "gift-card-remaining",
  "stage": 1,
  "enable_allowed": false,
  "product_ids": [561, 562],
  "count": 2,
  "projected_extra_rows": 10
}
```

شناسه‌ها مرتب و قابل بازتولید با `php artisan torob:rollout-plan --write`. سقف هر فایل: ≤۵۰۰ محصول و ≤۱۵۰۰ ردیف اضافه.

## افزودن نگاشت جدید

1. فقط در `config/torob_split_copy.php` (`brands` / `regions` / `currencies` / `brand_units`).
2. Needle باید یکتای handle باشد (نه زیررشتهٔ کوتاه مثل `ea` که `arena` را می‌گیرد).
3. تست عنوان فارسی + یکتایی + ثبات محصولات فعال.
4. `php artisan test --filter=Torob`
5. دیپلوی `deploy-backend.ps1` سپس `torob:rollout-plan --write` و `torob:verify-feed`.
6. **enable نکن** تا تأیید جدا.

## معافیت‌ها (دلیل)

| دلیل | معنی |
|---|---|
| `unknown_mapping` | برند/کشور/مبلغ/واحد در config نیست یا عنوان فارسی کامل نمی‌شود |
| `over_max` | بیش از ۳۰ SKU |
| `ambiguous_titles` | دو SKU پس از نرمال‌سازی عنوان یکسان |
| `empty_labels` | برچسب خالی یا برابر عنوان محصول |
| `single_sku` | یک SKU قابل سفارش → یک ردیف کافی است |

## چرا service-topup برگشت خورد

عنوان الگوی شارژ کشور ندارد. چند محصول ولورانت (ID/KH/MY/…) و چند محصول پابجی موبایل (fast/manual/reserve) عنوان SKU یکسان ساختند. verify: `FAIL uniques`. همچنین `cod-mobile` یک SKU با قیمت فید ۰ در برابر کاتالوگ غیرصفر.

**تا الگوی عنوان منطقه‌ای تأیید نشود این ۱۵ محصول را دوباره enable نکنید.**

هشدار: `discord` به‌صورت substring روی `topup-legacy-of-discord-furiouswings` می‌نشیند → «شارژ دیسکورد … الماس» غلط است. واحد الماس برای Discord Nitro اعمال نشد.

## Game Keys

اعمال نشده. پیشنهاد: `کلید بازی {عنوان کاتالوگ} ({ریجن})` — امروز عنوان کاتالوگ انگلیسی است و با قاعدهٔ «بدون ترکیب انگلیسی/فارسی» جور نیست. نمونه: `GAMEKEYS-TITLE-PROPOSAL.json`.
