# بستن نگاشت و مراحل بعدی (بدون enable)

تولید production: **2026-10-26 ~22:30 Asia/Tehran** — `torob:mapping-audit --write` + `torob:rollout-plan --write` + `verify-feed`.  
کامیت کد: `41df56e` (دیپلوی با `deploy-backend.ps1`).

## ۱) وضعیت فعلی

| معیار | مقدار |
|---|---|
| محصولات فعال split | **۵۱** (۵۰ مرحلهٔ ۱ + ۸۸۷) — `frozen_canonical_count=51` |
| مرحلهٔ ۲ (گیفت‌کارت باقی / تلگرام / top-up) | **هنوز enable نشده** (همان ۵۱ id در `status`) |
| ردیف فید listable کل | **۵۰٬۶۱۵** (`global_current` در rollout-plan) |
| baseline ذخیره‌شده | **۵۰٬۰۳۴** — سقف **۷۵٬۰۵۱** |
| `verify-feed` | **pass=yes** — `rows=641` (محصولات split فعال)، `20261026-223025.json` |

## ۲) نگاشت این نوبت

**اعمال‌شده (قطعی):** ریجن `bd` => بنگلادش (ISO).

**اعمال نشده:** برندهای Astropay، Apex، topupهای بدون نگاشت، game keys — فهرست کامل `MAPPING-STILL-UNKNOWN.md` + `MAPPING-AUDIT.json`.

### جدول دسته — معاف / واجد / ردیف (simulate-all، غیرفعال)

| دسته | معاف (ممیزی محصول) | واجد split | ردیف اضافه اگر فعال شوند | unknown_mapping (planner) | تغییر نسبت به dry-run قبل |
|---|---:|---:|---:|---:|---|
| Gift cards باقی | 328 | **195** | +1498 | 302 | واجد همان ۱۹۵؛ فقط تفکیک شکاف در audit |
| Telegram Stars | 0 | 1 | +11 | 0 | بدون تغییر |
| Service top-up | 284 | 15 | +83 | 259 | بدون تغییر |
| Game Keys | 370 | **0** | 0 | 147 | بدون تغییر |
| Steam CIS | — | 0 | 0 | — | — |

**اگر همهٔ scopes باقی فعال شوند:** ۵۰٬۶۱۵ → **۵۲٬۲۰۷** (×**۱.۰۴۳** vs ۵۰٬۰۳۴).

### شکاف per محصول معاف (خلاصه)

- **Gift card:** `unknown_brand` 259، `unknown_amount` 43، `single_sku` 25، `ambiguous_titles` 1
- **Game top-up:** `unknown_brand` 238، `over_max` 25، `unknown_amount` 21
- **Game keys:** `single_sku` 223، `unknown_amount` 144، `unknown_brand` 3

## ۳) Game Keys / Service top-up

- **Game keys:** split با الگوی gift_card مناسب نیست (SKU = نسخه/پلتفرم). تا عنوان فارسی در کاتالوگ نباشد معاف. الگوی پیشنهادی: `کلید بازی {نام فارسی} ({ریجن})` — در `MAPPING-STILL-UNKNOWN.md`.
- **Service top-up:** split مناسب است وقتی برند+واحد در config باشد. الگو: `شارژ {برند} {N} {واحد}` — نمونه: پابجی یوسی، فری فایر الماس، ولورانت پوینت.

## ۴) پایداری محصولات فعال

تست `TorobEnabledTitleStabilityTest` + عدم تغییر needle روی ۵۱ handle فعال: **عنوان split محصولات فعال با این diff عوض نمی‌شود** (فقط `bd` اضافه شد؛ هیچ محصول فعالی astropay/bd ندارد).

## ۵) فایل‌های مرحله (۴ فایل — مرتب‌سازی کم‌ریسک در stage-001)

| ترتیب پیشنهادی enable | فایل | محصول | ردیف اضافه | ضریب تجمعی vs ۵۰٬۰۳۴ |
|---|---|---:|---:|---:|
| 1 | `telegram-stars-stage-001.json` | 1 | +11 | 1.0118 |
| 2 | `service-topup-stage-001.json` | 15 | +83 | 1.0133 |
| 3 | `gift-card-remaining-stage-001.json` | 195 | +1498 | 1.0416 |
| alt 1+2 | `telegram-plus-service-topup-stage-001.json` | 16 | ~94 | — |

همه مرحله‌ها `under_global_1_5_cap` و زیر سقف ۵۰۰ محصول / ۱۵۰۰ ردیف اضافه.

## ۶) تست‌ها

`php artisan test --filter=Torob` → **۹۵ passed** (محلی، قبل از دیپلوی).

## ۷) دستور enable (تا تأیید شما)

```bash
php artisan torob:split-by-sku enable --file=telegram-stars-stage-001.json --dry-run --json
# php artisan torob:split-by-sku enable --file=telegram-stars-stage-001.json --force
# php artisan torob:split-by-sku flush-cache --force
# php artisan torob:verify-feed
```

همین الگو برای `service-topup-stage-001.json` و در پایان `gift-card-remaining-stage-001.json`.
