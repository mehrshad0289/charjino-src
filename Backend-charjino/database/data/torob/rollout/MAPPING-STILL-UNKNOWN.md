# نگاشت‌های هنوز نامشخص (بدون اعمال روی config)

تولید: `php artisan torob:mapping-audit --write`
قانون: فقط نگاشت **قطعی** در `config/torob_split_copy.php` می‌آید؛ موارد زیر معاف می‌مانند.

## gift-card

| معیار | مقدار |
|---|---:|
| بررسی‌شده (غیرفعال) | 523 |
| واجد split | 204 |
| معاف | 319 |

### شکاف‌ها (تعداد محصول)

- `unknown_brand`: 184
- `unknown_amount`: 108
- `single_sku`: 25
- `unknown_region`: 1
- `ambiguous_titles`: 1

### نمونه محصولات معاف (حداکثر ۵۰ در JSON کامل)

| id | handle | دلیل | شکاف | برند needle |
|---:|---|---|---|---|
| 558 | `giftcard-acash-my` | unknown_mapping | unknown_brand | — |
| 559 | `giftcard-adobe-digital-code` | single_sku | single_sku | — |
| 560 | `giftcard-airalo-voucher` | unknown_mapping | unknown_brand | — |
| 585 | `giftcard-amazon-in` | single_sku | single_sku | amazon |
| 594 | `giftcard-apex-legends-mobile-id` | unknown_mapping | unknown_brand | — |
| 595 | `giftcard-apex-legends-mobile-ph` | unknown_mapping | unknown_brand | — |
| 596 | `giftcard-apex-legends-mobile-sg` | unknown_mapping | unknown_brand | — |
| 597 | `giftcard-apex-legends-mobile-th` | unknown_mapping | unknown_brand | — |
| 598 | `giftcard-apex-legendstm-ea` | single_sku | single_sku | — |
| 599 | `giftcard-apex-legendstm-xbox` | unknown_mapping | unknown_amount | xbox |
| 600 | `giftcard-appota-vn` | unknown_mapping | unknown_brand | — |
| 631 | `giftcard-arena-breakout` | unknown_mapping | unknown_brand | — |
| 632 | `giftcard-astropay-bd` | unknown_mapping | unknown_brand | — |
| 633 | `giftcard-astropay-br` | unknown_mapping | unknown_brand | — |
| 634 | `giftcard-astropay-cn` | unknown_mapping | unknown_brand | — |
| 635 | `giftcard-astropay-eg` | unknown_mapping | unknown_brand | — |
| 636 | `giftcard-astropay-id` | unknown_mapping | unknown_brand | — |
| 637 | `giftcard-astropay-in` | unknown_mapping | unknown_brand | — |
| 638 | `giftcard-astropay-jp` | unknown_mapping | unknown_brand | — |
| 639 | `giftcard-astropay-my` | unknown_mapping | unknown_brand | — |
| 640 | `giftcard-astropay-ph` | unknown_mapping | unknown_brand | — |
| 641 | `giftcard-astropay-pk` | unknown_mapping | unknown_brand | — |
| 642 | `giftcard-astropay-sg` | unknown_mapping | unknown_brand | — |
| 643 | `giftcard-astropay-th` | unknown_mapping | unknown_brand | — |
| 644 | `giftcard-astropay-us` | unknown_mapping | unknown_brand | — |

### پیشنهاد نگاشت (نیاز به تأیید شما)

- `astropay` → پیشنهاد برند «آستروپی» (ترجمهٔ رسمی نام AstroPay) + کشور از پسوند handle
- `apex-legends-mobile` → پیشنهاد برند «ایپکس لجندز موبایل» (املای فارسی تأیید شود)
- `airalo-voucher` → بدون ریجن در handle؛ تا تعریف ریجن/نوع voucher معاف
- `unknown_amount` → SKU بدون `N_ccy` در offer یا نام؛ نیاز به نمونه SKU یا قانون جدا (مثلاً Nintendo eShop)

## game-topup

| معیار | مقدار |
|---|---:|
| بررسی‌شده (غیرفعال) | 299 |
| واجد split | 15 |
| معاف | 284 |

### شکاف‌ها (تعداد محصول)

- `unknown_brand`: 238
- `over_max`: 25
- `unknown_amount`: 21

### نمونه محصولات معاف (حداکثر ۵۰ در JSON کامل)

| id | handle | دلیل | شکاف | برند needle |
|---:|---|---|---|---|
| 255 | `topup-8-ball-pool` | unknown_mapping | unknown_brand | — |
| 256 | `topup-acecraft` | unknown_mapping | unknown_brand | — |
| 257 | `topup-ace-racer` | unknown_mapping | unknown_brand | — |
| 258 | `topup-afk-journey` | unknown_mapping | unknown_brand | — |
| 259 | `topup-age-of-empire-mobile` | unknown_mapping | unknown_brand | — |
| 260 | `topup-age-of-magic` | over_max | over_max | — |
| 261 | `topup-arena-breakout` | unknown_mapping | unknown_brand | — |
| 262 | `topup-arena-breakout-infinite` | unknown_mapping | unknown_brand | — |
| 263 | `topup-arena-of-valor-eu` | unknown_mapping | unknown_brand | — |
| 264 | `topup-arena-of-valor-id` | unknown_mapping | unknown_brand | — |
| 265 | `topup-arena-of-valor-th` | unknown_mapping | unknown_brand | — |
| 267 | `topup-asphalt-9-legends` | unknown_mapping | unknown_brand | — |
| 268 | `topup-astral-guardians-cyber-fantasy` | unknown_mapping | unknown_brand | — |
| 269 | `topup-azur-lane` | unknown_mapping | unknown_brand | — |
| 270 | `topup-badlanders` | unknown_mapping | unknown_brand | — |
| 271 | `topup-ballistic-hero-vng` | unknown_mapping | unknown_brand | — |
| 272 | `topup-be-the-king` | unknown_mapping | unknown_brand | — |
| 273 | `topup-bigo-live` | unknown_mapping | unknown_brand | — |
| 274 | `topup-black-clover-m` | unknown_mapping | unknown_brand | — |
| 275 | `topup-blade-x-odyssey-of-heroes` | unknown_mapping | unknown_brand | — |
| 276 | `topup-bleach-soul-resonance-americas` | unknown_mapping | unknown_brand | — |
| 277 | `topup-bleach-soul-resonance-europe` | unknown_mapping | unknown_brand | — |
| 278 | `topup-bleach-soul-resonance-sea` | unknown_mapping | unknown_brand | — |
| 279 | `topup-blood-strike` | unknown_mapping | unknown_brand | — |
| 280 | `topup-blood-strike-mena` | unknown_mapping | unknown_brand | — |

### پیشنهاد نگاشت (نیاز به تأیید شما)

- هر `topup-{game}` → برند فارسی از عنوان فروشگاه یا تأیید شما؛ واحد از `brand_units`
- `unknown_amount` → عدد در نام SKU نیست؛ split تا اصلاح دادهٔ کاتالوگ ممکن نیست
- دیسکورد: واحد «الماس» احتمالاً alias اشتباه؛ پیشنهاد واحد «نیترو» پس از تأیید SKU

## game-keys

| معیار | مقدار |
|---|---:|
| بررسی‌شده (غیرفعال) | 370 |
| واجد split | 0 |
| معاف | 370 |

### شکاف‌ها (تعداد محصول)

- `single_sku`: 223
- `unknown_amount`: 144
- `unknown_brand`: 3

### نمونه محصولات معاف (حداکثر ۵۰ در JSON کامل)

| id | handle | دلیل | شکاف | برند needle |
|---:|---|---|---|---|
| 1139 | `gamekey-7-days-to-die-global` | single_sku | single_sku | steam |
| 1140 | `gamekey-abyssus-global` | single_sku | single_sku | steam |
| 1141 | `gamekey-ace-attorney-investigations-collection-cis` | single_sku | single_sku | steam |
| 1142 | `gamekey-against-the-storm-cis` | unknown_mapping | unknown_amount | steam |
| 1143 | `gamekey-age-of-empires-2-definitive-edition-global` | unknown_mapping | unknown_amount | steam |
| 1144 | `gamekey-age-of-empires-4-global` | unknown_mapping | unknown_amount | steam |
| 1145 | `gamekey-age-of-mythology-retold-global` | unknown_mapping | unknown_amount | steam |
| 1146 | `gamekey-american-truck-simulator-global` | single_sku | single_sku | steam |
| 1147 | `gamekey-among-us-global` | single_sku | single_sku | steam |
| 1148 | `gamekey-apollo-justice-ace-attorney-trilogy-cis` | unknown_mapping | unknown_amount | steam |
| 1149 | `gamekey-arc-raiders-global` | single_sku | single_sku | steam |
| 1150 | `gamekey-arc-raiders-row` | single_sku | single_sku | steam |
| 1151 | `gamekey-arma-3-global` | unknown_mapping | unknown_amount | steam |
| 1152 | `gamekey-arma-reforger-global` | unknown_mapping | unknown_amount | steam |
| 1153 | `gamekey-assetto-corsa-cis` | unknown_mapping | unknown_amount | steam |
| 1154 | `gamekey-assetto-corsa-competizione-cis` | unknown_mapping | unknown_amount | steam |
| 1155 | `gamekey-assetto-corsa-rally-cis` | single_sku | single_sku | steam |
| 1156 | `gamekey-atomic-heart-row` | single_sku | single_sku | steam |
| 1157 | `gamekey-aven-colony-global` | single_sku | single_sku | steam |
| 1158 | `gamekey-batman-arkham-origins-global` | single_sku | single_sku | steam |
| 1159 | `gamekey-battlefield-4tm-global` | single_sku | single_sku | — |
| 1160 | `gamekey-battletech-cis` | unknown_mapping | unknown_amount | steam |
| 1161 | `gamekey-beyond-two-souls-global` | single_sku | single_sku | steam |
| 1162 | `gamekey-blasphemous-global` | single_sku | single_sku | steam |
| 1163 | `gamekey-blasphemous-2-global` | single_sku | single_sku | steam |

### پیشنهاد نگاشت (نیاز به تأیید شما)

- تا عنوان فارسی بازی در `product.title` نباشد، split غیرفعال بماند
- `unknown_brand` برای GTA/RDR/Sims: نام فارسی بازی در config یا کاتالوگ

## Game Keys — آیا split مناسب است؟

خیر.
SKUهای کلید بازی معمولاً نسخه/پلتفرم هستند نه مبلغ ارزی؛ الگوی gift_card (مبلغ+واحد) جواب نمی‌دهد.

الگوی پیشنهادی: `کلید بازی {نام فارسی بازی از عنوان کاتالوگ} ({کد ریجن})`

- کلید بازی (GLOBAL)
- کلید بازی (CIS)
- کلید بازی (EU)

## Service top-up — الگوی عنوان

`شارژ {برند فارسی} {N} {واحد}`

- شارژ پابجی موبایل 60 یوسی
- شارژ فری فایر 110 الماس
- شارژ ولورانت 475 ولورانت پوینت

