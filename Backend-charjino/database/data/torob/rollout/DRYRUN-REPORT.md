# dry-run پس از نگاشت قطعی و اصلاح سقف (بدون فعال‌سازی)

تولید روی production: `php artisan torob:rollout-plan --write` در **2026-10-26 22:03 Asia/Tehran**.  
دیپلوی کد: `docs-charjino/deploy/charjino/deploy-backend.ps1` (نه SCP).  
جزئیات: `DRYRUN-PLAN.json` — پیشنهاد باقی‌مانده: `MAPPING-PROPOSALS.json` — موارد تأییدنشده: `MAPPING-NEEDS-REVIEW.md`.  
فایل‌های مرحله روی سرور: `storage/app/torob/rollout/*-stage-*.json` (**۴ فایل**؛ فایل‌های ۹۷/۱۴۷تایی قبلی پاک شدند).

**هیچ enable اجرا نشد.** steam-gifts و Manual Services فعال نمی‌شوند.

---

## پوشش

`torob:rollout-plan` همان پیمایش کامل listable است: **۵۰٬۰۲۵ محصول، ۵۰۱ قطعهٔ ۱۰۰تایی، ۰ صفحه HTTP**.  
`report --simulate-all` همین پوشش کاتالوگ را دارد.

---

## قانون سقف (تغییر این نوبت)

قبلاً ضریب **۱.۵× برای هر دسته** هم اعمال می‌شد. baseline گیفت‌کارت زنده ۱۱۶۴ است؛ باقی‌مانده دسته را به حدود ۲× می‌رساند، بنابراین planner بعد از برخورد به سقف دسته مرحله‌های تک‌محصولی می‌ساخت (۹۷ یا ۱۴۷ فایل) و **همهٔ محصولات واجد را نمی‌شد زیر قانون دسته فعال کرد**.

حالا:

| سطح | قانون |
|---|---|
| کل فید | فقط **۱.۵× baseline ذخیره‌شده ۵۰٬۰۳۴** → سقف **۷۵٬۰۵۱** |
| هر مرحله | حداکثر **۵۰۰ محصول** و حداکثر **۱۵۰۰ ردیف اضافه** |
| ضریب دسته | فقط اطلاع‌رسانی است؛ توقف نیست |

`enable` همچنان اگر `feed_rows_projected > ceil(baseline * 1.5)` باشد متوقف می‌شود (`TorobSplitBySkuCommand`). `--force` معافیت‌ها را دور نمی‌زند.

---

## جدول قبل / بعد نگاشت

نگاشت اعمال‌شده: برند `airbnb`؛ کشورها `lu hr lb sk ru cn hu cr pe uy`؛ ارزهای `RUB HUF CNY CRC PEN UYU`. بقیه حدس زده نشد.

| دسته | معاف unknown قبل | معاف unknown بعد | واجد قبل | واجد بعد | ردیف اضافه قبل | ردیف اضافه بعد | ضریب کل اگر همین دسته فعال شود (vs ۵۰٬۶۱۵) |
|---|---:|---:|---:|---:|---:|---:|---:|
| Gift cards باقی | 330 | **302** (−۲۸) | 167 | **195** (+۲۸) | +1235 | **+1498** | 1.030 |
| Telegram Stars | 0 | 0 | 1 | 1 | +11 | +11 | 1.0002 |
| Steam Top-Up CIS | 0 | 0 | 0 | 0 | 0 | 0 | 1.0 |
| Service top-up | 259 | 259 | 15 | 15 | +83 | +83 | 1.0016 |
| Game Keys | 147 | 147 | 0 | 0 | 0 | 0 | 1.0 |

معافیت دیگر گیفت‌کارت: `ambiguous_titles` همچنان ۱. Service top-up: `over_max` همچنان ۲۵.

اگر **همهٔ scopes باقی** فعال شوند: ۵۰٬۶۱۵ → **۵۲٬۲۰۷** (×**۱.۰۳۱۵** نسبت به فید فعلی؛ ×**۱.۰۴۳۴** نسبت به ۵۰٬۰۳۴). زیر سقف ۷۵٬۰۵۱.

عنوان تلگرام روی production: `شارژ تلگرام 50 ستاره` (تکرار «تلگرام» حذف شد).

---

## فهرست مرحله‌ها (۴ فایل)

| ترتیب پیشنهادی | فایل | محصول | ردیف اضافه | فید کل پیش‌بینی | ضریب vs ۵۰٬۰۳۴ | سقف مرحله |
|---|---|---:|---:|---:|---:|---|
| 1 | `gift-card-remaining-stage-001.json` | 195 | 1498 | 52113 | 1.0416 | محصول و ردیف OK (۱۴۹۸ از ۱۵۰۰) |
| 2a | `telegram-stars-stage-001.json` | 1 | 11 | 50626* | 1.0118 | OK |
| 2b | `service-topup-stage-001.json` | 15 | 83 | 50698* | 1.0133 | OK |
| alt 2 | `telegram-plus-service-topup-stage-001.json` | 16 | ~94 | — | — | جایگزین ۲a+۲b چون مجموع ≤۵۰۰ |

\* پیش‌بینی مرحلهٔ جدا نسبت به فید **فعلی** است، نه پس از مرحلهٔ ۱. اگر گیفت‌کارت اول فعال شود، فید پایه همان ۵۲٬۱۱۳ می‌شود و اضافهٔ تلگرام/تاپ‌آپ روی آن می‌نشیند؛ باز هم زیر ۷۵٬۰۵۱.

Game Keys و Steam CIS باقی: **۰ مرحله**.

---

## گوگل پلی و آیتونز

۴۹ محصول listable با handle شامل `google-play` / `itunes` / `app-store`.

### `giftcard-google-play-uk`

- شناسه **۷۲۴**
- **جزو ۵۱ فعال مرحلهٔ ۱ است** (`in_stage1_enabled=true`)
- ۶ SKU / ۶ ردیف زنده روی فید
- در هیچ فایل remaining نیست

### گوگل پلی / آیتونز داخل ۵۱ فعال

| id | handle | ردیف زنده |
|---:|---|---:|
| 724 | giftcard-google-play-uk | 6 |
| 725 | giftcard-google-play-us | 10 |
| 712 | giftcard-google-play-de | 5 |
| 715 | giftcard-google-play-id | 22 |
| 718 | giftcard-google-play-jp | 6 |
| 722 | giftcard-google-play-sa | 11 |
| 1514 | giftcard-app-store-itunes-us | 29 |
| 630 | giftcard-app-store-itunes-uk | 19 |
| 609 | giftcard-app-store-itunes-de | 24 |
| 603 | giftcard-app-store-itunes-au | 26 |
| 602 | giftcard-app-store-itunes-at | 22 |
| 629 | giftcard-app-store-itunes-tr | 23 |

### باقی کشورها (هنوز enable نشده)

همهٔ splitپذیرها در **`gift-card-remaining-stage-001.json`** هستند، از جمله آیتونز چین/روسیه/لوکزامبورگ که با نگاشت ارز/کشور این نوبت باز شدند.

- گوگل پلی باقی: AE, AT, BE, BR, ES, FR, IN, KR, NL, PL, TR
- گوگل پلی ایتالیا (`717` `giftcard-google-play-it`): **تک‌SKU** → split نمی‌شود، در فایل مرحله نیست
- آیتونز باقی: AE, BE, BR, CA, CH, **CN**, ES, FI, FR, HK, ID, IE, IN, IT, JP, **LU**, MX, NL, NO, NZ, PL, PT, **RU**, SA, SE

---

## MAPPING-NEEDS-REVIEW

فهرست کامل در `MAPPING-NEEDS-REVIEW.md`. اعمال نشد: Acash، Airalo، AstroPay، Appota، Apex Legends Mobile، همهٔ topupهای نمونه (۸ بال پول، AFK Journey، …)، کلیدهای GTA/RDR2/Sims، واحد پرمیوم تلگرام، و همهٔ `unknown_amount`.

---

## تست‌ها

`php artisan test --filter=Torob` → **۹۳ passed** (۵۲۷ assertion).

شامل: تکرارنشدن «تلگرام»، عنوان Airbnb/آیتونز چین/روسیه/مجارستان/گوگل‌پلی UK، سقف مرحله بدون ضریب دسته، شکاف برند ناشناخته.

---

## Redis / کش

بدون `cache:clear`. پیشوند تأییدشده: **`charzhyno-cache-torob:v3:*`**.  
flush فقط بعد از enable تأییدشده: `php artisan torob:split-by-sku flush-cache --force`

---

## ریسک‌ها

1. مرحلهٔ گیفت‌کارت **۱۴۹۸ ردیف اضافه** دارد (۲ تا زیر سقف ۱۵۰۰)؛ نگاشت بعدی ممکن است آن را دو مرحله کند.
2. تلگرام پرمیوم (۵۵۸۱۳) هنوز با واحد ستاره نباید enable شود.
3. Discord top-up واجد است با واحد «الماس» — ممکن است غلط باشد.
4. Game keys همچنان ۰ واجد تا الگوی مبلغ/عنوان جدا تأیید شود.
5. `--force` فقط دروازهٔ نوشتن است؛ معافیت را باز نمی‌کند.

---

## دستور فعال‌سازی (تا تأیید شما اجرا نشود)

روی production، از `/var/www/charjino`:

```bash
# همیشه اول dry-run همان فایل:
php artisan torob:split-by-sku enable --file=gift-card-remaining-stage-001.json --dry-run --json

# پس از تأیید صریح (مرحلهٔ ۱ گیفت‌کارت باقی):
# php artisan torob:split-by-sku enable --file=gift-card-remaining-stage-001.json --force
# php artisan torob:split-by-sku flush-cache --force
# php artisan torob:verify-feed

# تلگرام ستاره (بعد از تأیید جدا):
# php artisan torob:split-by-sku enable --file=telegram-stars-stage-001.json --dry-run --json
# php artisan torob:split-by-sku enable --file=telegram-stars-stage-001.json --force

# شارژ سرویس:
# php artisan torob:split-by-sku enable --file=service-topup-stage-001.json --dry-run --json
# php artisan torob:split-by-sku enable --file=service-topup-stage-001.json --force

# یا به‌جای دو فایل بالا، یک فهرست مشترک:
# php artisan torob:split-by-sku enable --file=telegram-plus-service-topup-stage-001.json --dry-run --json
```
