# گزارش ممیزی و مرحلهٔ ۱ فید ترب (یک ردیف به ازای هر SKU)

تاریخ تولید روی سرور: 2026-10-26 21:27 Asia/Tehran  
منبع: کد `Backend-charjino` + پیمایش کامل محصولات listable روی production (نه حدس).  
جزئیات خام: `storage/app/torob/rollout/prod/AUDIT.json`

**هیچ دستهٔ بخش B فعال نشد.** steam-gifts و Manual Services فعال نمی‌شوند.

---

## ۱) یافته‌های بخش صفر (از کد)

### `--force`
فقط دروازهٔ نوشتن روی production است (`mayWrite()`). اگر `APP_ENV=production` باشد، بدون `--force` یا `--dry-run` هیچ runtime/canonical/کشی نوشته نمی‌شود.

`--force` معافیت‌ها را دور نمی‌زند. `enable` همیشه `decide($product, treatAsEnabled=true)` را صدا می‌زند. ترتیب تصمیم در `TorobSplitPolicy::evaluate`:

1. `exclude` (env/config)  
2. `not_enabled` — فقط وقتی `treatAsEnabled` و `simulateAll` خاموش باشند  
3. `single_sku`  
4. `over_max` (سقف `max_items_per_product`=30)  
5. `unknown_mapping` (برند/ریجن/واحد/مبلغ از `config/torob_split_copy.php` ناقص)  
6. `empty_labels` / `ambiguous_titles`  
7. در غیر این صورت split

اولویت عملی: **exclude > معافیت‌ها > enable**. تست: `test_force_does_not_bypass_over_max_or_unknown_mapping` (حتی با `env=production` و `--force`).

`--allow-steam-gifts` حذف شد. `blocked_group_slugs` الان `steam-gifts` و `manual-services` است؛ enable برای این گروه‌ها همیشه رد می‌شود.

فعال‌سازی واقعی دیگر با `--limit` یا `--group` تنها مجاز نیست؛ باید `--file` یا `--ids` باشد. `--limit` فقط برای dry-run گروهی مانده.

### `freeze-canonical`
- واحد: **یک `sku_id` برای هر `product_id`** (نه برای هر SKU).  
- فایل: `storage/app/torob/split-canonical.json` (`TorobSplitCanonicalStore`).  
- انتخاب: اگر در فایل باشد همان SKU؛ وگرنه `fazercards_offer_id=5_usd`؛ وگرنه ارزان‌ترین SKU لیست‌شده.  
- `page_unique` کانونیکال = `{product_id}` ؛ بقیه = `{product_id}_{sku_id}`.  
- **enable** (کد جدید): بعد از merge، `freezeListed` برای همان شناسه‌ها.  
- **disable** (کد جدید): canonical همان شناسه‌ها را `forget` می‌کند مگر هنوز از env/config فعال باشند (مثل ۸۸۷)؛ سپس کش تأییدشده پاک می‌شود.  
- **روی production فعلی (هنوز دیپلوی نشده):** disable فقط runtime را برمی‌دارد و freeze/کش را دست نمی‌زند. به همین خاطر disable کامل round-trip روی سرور زنده هنوز با کد قدیم است.

۵۱ ورودی freeze روی production: ۵۰ محصول مرحلهٔ ۱ + ۸۸۷. فهرست `frozen_sku_id` و `page_uniques` در `AUDIT.json` → `stage1_live.canonical`.

### کلیدهای کش (از کد + Redis زنده)
منطق Laravel:

- پاسخ: `torob:v3:resp:{sha256(schema, fingerprint, page/sort/cursor/urls/uniques)}`  
- جمع: `torob:v3:total:torob_api_v3_split_sku_v2:{ruleFingerprint}`  
- schema: `torob_api_v3_split_sku_v2`

کلید واقعی Redis = `Cache::getStore()->getPrefix()` + کلید منطقی.

روی production الان:

- `cache.default` = redis  
- `cache.prefix` / `store_prefix` = **`charzhyno-cache-`**  
- SCAN match تأییدشده: **`charzhyno-cache-torob:v3:*`**  
- تعداد کلید در لحظهٔ ممیزی: **۰** (TTL سیصد ثانیه؛ خالی بودن یعنی الان چیزی برای حذف نیست، نه اینکه پیشوند غلط باشد)

`forgetAll()` فقط همین match را `DEL` می‌کند؛ `cache:clear` سراسری در کد نیست.

ترتیب درست: **دیپلوی کد → سپس `torob:split-by-sku flush-cache --force`** (فقط اگر کلید وجود داشته باشد هم بی‌ضرر است). enable/disable جدید هم همان کلیدها را بعد از نوشتن پاک می‌کنند.

### `report --simulate-all`
`eachListableCursor(100, ..., withRelations=true)` همهٔ محصولات listable را می‌خواند. **صفحهٔ HTTP فید ترب خوانده نمی‌شود.**

پیمایش معادل روی production (این نوبت):

| معیار | مقدار | کامل؟ |
|---|---|---|
| محصولات listable | 50025 | بله، کاتالوگ listable |
| قطعه‌های ۱۰۰تایی شناسه | 501 | بله |
| SKU لیست‌شده | 253849 | بله |
| SKU قابل سفارش | 246538 | بله |
| صفحات زندهٔ فید HTTP | 0 | **خیر — ممیزی صفحه‌بندی زنده نیست** |
| نمونهٔ معافیت artisan | حداکثر ۲۰ در هر دلیل | ناقص برای فهرست کامل معاف‌ها |

این گزارش را «ممیزی کامل فید HTTP» معرفی نکنید. کامل است فقط برای تصمیم split روی کاتالوگ listable.

### محصول واجد شرایط / ترتیب / حذف تکراری / صفحه‌بندی
واجد شرایط برای split (وقتی enable فرض شود):

- در `storefrontListableQuery` (فعال، visible یا اخیراً ناموجود، گروه visible، نه `manual-*` / family MANUAL)  
- نه در `TOROB_EXCLUDED_PRODUCT_IDS`  
- handle بدون `game-pass`  
- گروه در `blocked_group_slugs` نباشد  
- `decide(treatAsEnabled)` = split

ترتیب dry-run گروهی قدیمی: امتیاز برند → تعداد مبلغ → max_price → sku_count → id.  
ترتیب فعال‌سازی جدید: **ترتیب شناسه در فایل** (فایل‌های remaining بر اساس id صعودی ساخته شده‌اند).  
حذف تکراری: `uniqueInts` روی id.

صفحه‌بندی فید: ردیفی، `page_size=100`. صفحهٔ غیرآخر باید دقیقاً ۱۰۰ ردیف باشد. محصول split می‌تواند بین دو صفحه قطع شود.

### baseline و سقف ۱.۵
- `storage/app/torob/split-baseline.json` → **`feed_rows_before_rollout=50034`** (اولین enable مرحلهٔ ۱).  
- فید فعلی: **50615**.  
- توقف کل: `ceil(50034 * 1.5) = 75051`. فعلی OK.  
- baseline جداگانهٔ هر دسته در فایل ذخیره نشده؛ نسبت دسته نسبت به **شمارش فعلی همان گروه** در این گزارش آمده.

### قواعد عنوان
از `TorobFeedCatalogCopy::assertCompletePersianTitle`: بدنه فقط `\p{Arabic}` + رقم + فاصله + ZWNJ/ZWJ؛ تنها لاتین مجاز پرانتز پایانی `([A-Z]{2,8})` مثل `(US)` یا `(CIS)`. نگاشت برند/کشور/واحد/ارز فقط از `config/torob_split_copy.php`. بدون نگاشت → `unknown_mapping` → همان یک ردیف قدیمی.

استثناء میراث: محصول ۸۸۷ / handle `giftcard-rewarble-usd` عنوان legacy لاتین Rewarble را نگه می‌دارد.

---

## ۲) نتیجهٔ A — مرحلهٔ ۱ (۵۰ گیفت‌کارت)

لیست تأییدشدهٔ قبلی با جایگزینی **فقط** `1112→1134` و `1105→1115`:

SET فعال‌شده روی production (۵۰ id به‌علاوه ۸۸۷ از env) **برابر همان SET است.**  
`only_in_enabled=[]` ، `only_in_expected=[]`. **متوقف نشدم.**

ترتیب positional با round-robin قبلی یکی نیست؛ مقایسه روی عضویت SET بود نه ترتیب نوبتی.

1134 و 1115 روی فید زنده‌اند (dry-run جدا لازم نبود):

- 1134 `giftcard-xbox-za` freeze sku **12544** ، ۱۲ ردیف  
  نمونه: `گیفت کارت ایکس باکس آفریقای جنوبی 50 راند (ZA)`  
- 1115 `giftcard-xbox-br` freeze sku **12450** ، ۱۱ ردیف  
  نمونه: `گیفت کارت ایکس باکس برزیل 60 رئال (BR)`

**فعال‌سازی مجدد انجام نشد** (قبلاً زنده بود).

`torob:verify-feed --ids=887,1134,1115 --sample=3` همین نوبت: **pass=yes** ، rows=33 ، uniques/canonical/availability/lookups/prices/pagination/timings/recent_errors همه ok.

| | قبل از مرحلهٔ ۱ | بعد (الان) |
|---|---|---|
| کل فید | 50034 | 50615 |
| ۵۰ محصول هدف | ۵۰ ردیف (یکتا per product) | **631** ردیف |
| ۸۸۷ | ۱۰ ردیف split | ۱۰ ردیف (بدون تغییر الگو) |
| enabled | 887 | 887 + ۵۰ |
| freeze | ۸۸۷ | ۵۱ |

قبل: `page_unique={id}` برای هر کدام از ۵۰. بعد: کانونیکال همان `{id}` قفل‌شده روی `frozen_sku_id`؛ بقیه `{id}_{sku_id}`. جزئیات در AUDIT.

برندهای عمدهٔ گیفت‌کارت (۱۰ برند اول بر اساس تعداد SKU در کل دسته، شامل معاف‌ها):

| برند | SKU | محصول | ۳ عنوان نمونه (فقط اگر split می‌شود) |
|---|---|---|---|
| unmapped | 2126 | 288 | ندارد (معاف unknown_mapping) |
| app-store-itunes | 475 | 31 | گیفت کارت اپل آیتونز امارات 500/50/75 درهم (AE) |
| roblox | 318 | 27 | گیفت کارت روبلاکس امارات 20/50/100 درهم (AE) |
| playstation | 310 | 43 | گیفت کارت پلی استیشن امارات 70/100/160 دلار (AE) |
| amazon | 234 | 17 | گیفت کارت آمازون امارات 300/400/2500 درهم (AE) |
| steam-wallet | 188 | 28 | گیفت کارت استیم امارات 50/400/200 درهم (AE) |
| xbox | 176 | 37 | گیفت کارت ایکس باکس امارات 25/15/50 دلار (AE) |
| razer-gold | 174 | 24 | گیفت کارت ریزر گلد استرالیا 10/20/50 دلار (AU) |
| valorant | 133 | 21 | گیفت کارت ولورانت کانادا 10/25/50 دلار (CA) |
| google-play | 110 | 18 | گیفت کارت گوگل پلی امارات 30/300/100 درهم (AE) |

فایل فهرست مرحلهٔ ۱: `storage/app/torob/rollout/gift-card-stage1.json` و `database/data/torob/rollout/gift-card-stage1.json`.

---

## ۳) جدول B — dry-run بدون فعال‌سازی

پیمایش: همهٔ محصولات listable (۵۰۰۲۵)، SKUهای لیست‌شده، ۰ صفحهٔ HTTP.  
صفحات خوانده‌شده = ۵۰۱ قطعهٔ محصول ۱۰۰تایی (نه صفحات فید).

| دسته (slug کد) | بررسی‌شده | SKU لیست | SKU سفارش‌پذیر | صفحات HTTP | معاف‌ها | واجد باقی | ردیف فعلی | ردیف اگر remaining فعال شود | نسبت به فعلی دسته | نسبت به baseline کل 50034 |
|---|---|---|---|---|---|---|---|---|---|---|
| Gift cards باقی (`gift-card`) | 584 | 4700 | 4529 | 0 | unknown_mapping 330، single_sku 25، ambiguous 1، game-pass 10 | 167 / 1402 SKU | 1174 | 2409 (+1235) | **2.052 — بالای ۱.۵** | 1.036 |
| Telegram Stars+Premium (`telegram`) | 2 | 15 | 15 | 0 | — | 2 / 15 SKU | 2 | 15 (+13) | **7.5 — بالای ۱.۵ دسته** | 1.012 |
| Steam Top-Up (`steam-wallet`) | 1 | 1 | 1 | 0 | single_sku 1 | 0 | 1 | 1 | 1.0 | 1.012 |
| Service top-up (`game-topup`) | 299 | 4704 | 4557 | 0 | unknown_mapping 259، over_max 25 | 15 / 98 SKU | 299 | 382 (+83) | 1.278 | 1.013 |
| Game Keys (`game-keys`) | 370 | 733 | 723 | 0 | single_sku 223، unknown_mapping 147 | **0** | 370 | 370 | 1.0 | 1.012 |
| Steam gifts (گزارش) | 48769 | 243696 | 236713 | 0 | unknown_mapping 47701، over_max 1059، single_sku 9 | 0 (مسدود) | 48769 | — | — | — |
| Manual services (گزارش) | 2 در DB | — | — | 0 | از فید خارج‌اند | 0 | 0 در فید | — | — | — |

### نکات دسته‌ای (بدون حدس)
- **Steam Top-Up (CIS):** گروه `steam-wallet` روی فید **یک محصول تک‌SKU** است؛ چیزی برای split ندارد. CIS گیفت‌کارت استیم در گروه `gift-card` است؛ **946 `giftcard-steam-cis` همین حالا در مرحلهٔ ۱ فعال است** (۲۲ ردیف، freeze 11032).
- **Telegram:** 55812 `telegram-stars-stars` (۱۲ SKU ستاره) و 55813 `telegram-premium-premium` (۳ SKU ماه). عنوان شبیه‌سازی‌شدهٔ ستاره: `شارژ تلگرام تلگرام 50 ستاره` (تکرار برند). پرمیوم با `brand_units.telegram=ستاره` واحد غلط می‌گیرد. **enable این فهرست ممنوع تا نگاشت جدا شود.**
- **Game keys:** برند steam روی handle هست ولی `persianSkuTitle` برای کلیدها null است (مبلغ/ارز/الگوی gift_card). هر ۱۴۷ چندSKU → unknown_mapping؛ split نمی‌شوند.
- **Gift cards باقی:** ۱۶۷ id در `gift-card-remaining-1.json`. فعال‌سازی یکجای این فهرست دسته را از ۱۱۷۴ به ۲۴۰۹ می‌رساند (**≥۱.۵ برابر فعلی دسته**). حتی نسبت به تخمین قبل از مرحلهٔ ۱ (~۵۹۳) خیلی بالاتر است.
- **Service top-up:** ۱۵ id در `game-topup-remaining-1.json`. ۲۵۹ برند بدون نگاشت (نمونه: `topup-8-ball-pool`, `topup-afk-journey`, …). نمونهٔ دیسکورد با واحد «الماس» است.
- **steam-gifts:** هرگز.  
- **Manual Services:** `manual-call-of-duty-mobile` (1513)، `manual-roblox` (56455)؛ `visible_products=0`؛ در `listableQuery` نیستند. هرگز.

برند/واحد بدون نگاشت → محصول یک ردیف قدیمی را نگه می‌دارد. نمونه‌ها در AUDIT `unmapped_handles` / `exempt_samples`.

فایل‌های ≤۵۰۰:

| فایل | تعداد | enable؟ |
|---|---|---|
| `gift-card-stage1.json` | 50 | قبلاً فعال |
| `gift-card-remaining-1.json` | 167 | نه تا تأیید؛ سقف دسته ۱.۵ نقض می‌شود |
| `telegram-stars-1.json` | 1 | نه تا اصلاح عنوان |
| `telegram-premium-1.json` | 1 | نه (نگاشت واحد غلط) |
| `game-topup-remaining-1.json` | 15 | نه تا تأیید |
| `steam-wallet-remaining-1.json` | 0 | چیزی نیست |
| `game-keys-remaining-1.json` | 0 | چیزی نیست |
| `steam-gifts-REPORT-ONLY-1.json` | 0 | هرگز |
| `manual-services-REPORT-ONLY.json` | 2 (خارج فید) | هرگز |

ترتیب پیشنهادی اجرا **بعد از تأیید و بعد از دیپلوی کد** (فعلاً هیچ‌کدام را نزن):  
1) Gift cards — فقط اگر فهرست کوچک‌تری زیر سقف ۱.۵ دسته ساخته شود  
2) Telegram Stars — بعد از اصلاح عنوان  
3) Steam Top-Up — فعلاً صفر  
4) Service top-up (`game-topup`) — ۱۵ محصول، نسبت دسته ۱.۲۷۸  
5) Game Keys — صفر تا نگاشت مبلغ/واحد

---

## ۴) تست‌ها

محلی، PHPUnit 11، ۸۵ تست مرتبط Torob: **OK (491 assertions)**.

پوشش درخواست‌شده:

| مورد | تست |
|---|---|
| یکتایی عنوان | `test_split_row_titles_are_unique_and_keep_trailing_region_code` ، `test_normalized_titles_must_be_unique` |
| فارسی + استثنای `(US)` | همان + `test_split_titles_cover_persian_brands_regions_and_currencies` |
| برند/واحد بدون نگاشت = معاف | `test_unknown_brand_keeps_a_single_normal_row` ، `test_unmapped_topup_unit_keeps_a_single_row` |
| `--force` معافیت را دور نمی‌زند | `test_force_does_not_bypass_over_max_or_unknown_mapping` |
| سقف ۳۰ | `test_over_max_items_keeps_a_single_normal_row` + force test با ۳۱ SKU |
| page_unique ثابت / canonical قفل | `test_frozen_canonical_does_not_move_when_prices_change` |
| صفحه‌بندی اول/میانی/آخر، جمع، بدون تکرار، غیرآخر=۱۰۰ | `test_row_pagination_first_middle_last_has_no_gaps_or_duplicates` ، `test_page_size_is_exactly_one_hundred` ، `test_row_pages_can_cut_inside_a_split_product` |
| disable round-trip (فید، runtime، canonical، page_unique) | `test_enable_then_disable_is_reversible` |
| steam-gifts / manual-services | `test_enable_refuses_steam_gifts` ، `test_enable_refuses_manual_services` |
| enable فقط با فایل/ids | `test_real_enable_requires_file_or_ids` ، `test_enable_from_rollout_file_keeps_file_order` |
| verify-feed شبیه‌سازی‌شده | `test_verify_feed_passes_for_split_and_telegram_group` |
| verify-feed زنده | production `--ids=887,1134,1115` pass=yes |

محدودیت: `verify-feed` کامل روی کل فید ۵۰۶۱۵ ردیفی در این نوبت اجرا نشد؛ نمونهٔ ۳ محصول + pagination چک داخلی.

---

## ۵) ریسک‌ها و محدودیت‌ها

1. **دیپلوی این نوبت انجام نشد.** درخت Backend مخلوط با WIP کالاف (CODM) است. `deploy-backend.ps1` کل درخت را می‌فرستد. کد جدید (`--file`، block manual-services، disable→unfreeze+flush، singleton استورها) **روی سرور نیست.** مرحلهٔ ۱ با کد قبلی زنده است.  
2. Redis الان ۰ کلید `charzhyno-cache-torob:v3:*` دارد؛ flush در این لحظه no-op است.  
3. `report --simulate-all` صفحهٔ HTTP فید را نمی‌خواند.  
4. baseline جداگانهٔ هر دسته ذخیره نشده.  
5. عنوان تلگرام و واحد پرمیوم برای enable آماده نیست.  
6. Game keys بدون نگاشت مبلغ/ارز split نمی‌شوند.  
7. Gift-card remaining یکجا سقف ۱.۵ دسته را می‌شکند.  
8. RuntimeStore قبلاً در همان فرآیند PHP بعد از enable کهنه می‌شد؛ singleton در AppServiceProvider این را بعد از دیپلوی Torob-only درست می‌کند.  
9. استثناء عنوان `(A-Z]{2,8})` از `(US)` گشادتر است (`GLOBAL`/`CIS` هم قبول می‌شود).

---

## ۶) دستور دقیق فعال‌سازی هر دسته (برای تأیید — اجرا نشود)

پیش‌نیاز: دیپلوی **فقط فایل‌های ترب** (نه WIP کالاف)، بعد:

```bash
php artisan torob:split-by-sku flush-cache --force
php artisan torob:split-by-sku status
```

سپس برای هر فایل، اول dry-run، بعد فقط با تأیید:

```bash
# Gift cards remaining — الان توصیه نمی‌شود (نسبت دسته 2.052)
php artisan torob:split-by-sku enable --file=gift-card-remaining-1.json --dry-run --json
# php artisan torob:split-by-sku enable --file=gift-card-remaining-1.json --force

# Telegram stars — نه تا اصلاح عنوان
php artisan torob:split-by-sku enable --file=telegram-stars-1.json --dry-run --json

# Telegram premium — نزن (واحد ستاره غلط است)

# Steam Top-Up — فایل خالی است
php artisan torob:split-by-sku enable --file=steam-wallet-remaining-1.json --dry-run --json

# Service top-up
php artisan torob:split-by-sku enable --file=game-topup-remaining-1.json --dry-run --json
# php artisan torob:split-by-sku enable --file=game-topup-remaining-1.json --force

# Game keys — فایل خالی است
php artisan torob:split-by-sku enable --file=game-keys-remaining-1.json --dry-run --json
```

`--file` به ترتیب در `storage/app/torob/rollout/` و `database/data/torob/rollout/` جستجو می‌شود.

بعد از هر enable موفق (فقط پس از تأیید):

```bash
php artisan torob:split-by-sku flush-cache --force
php artisan torob:verify-feed
```

برگشت:

```bash
php artisan torob:split-by-sku disable --file=<همان-فایل> --force
php artisan torob:split-by-sku flush-cache --force
```

اگر بعد از enable ردیف کل ≥ 75051 یا ردیف آن دسته ≥ ۱.۵ برابر شمارش ثبت‌شدهٔ همان دسته شد: همان لحظه disable.

steam-gifts و manual-services: دستوری برای enable داده نمی‌شود.
