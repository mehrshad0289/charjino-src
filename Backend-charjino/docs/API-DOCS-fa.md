# مستند API بک‌اند شارژینو (Charjino) — نسخهٔ «همان‌طور که ساخته شده» (As-built)

> تاریخ بررسی: شنبه ۲۰۲۶-۰۹-۲۶ (به وقت تهران) — بر اساس کد فعلی `Backend-charjino`.
> مرجع ماشینی: `openapi.yaml` (OpenAPI 3.1، بدون خطا در validation) — نسخهٔ HTML: `api-docs.html`.
> هر چیزی که در این فایل آمده **در کد وجود دارد** و مسیر فایلش ذکر شده. هر چیزی که وجود ندارد فقط در `PROPOSED-standard.md` آمده.

---

## ۰) خلاصهٔ مدیریتی

| مورد | وضعیت |
|---|---|
| Framework بک‌اند | **Laravel 12** (PHP ^8.2) — `Backend-charjino/composer.json` |
| معماری | Modular monolith: `app/Modules/{Identity, Catalog, Pricing, Orders, Payments, Fulfillment, Supplier/Shop2}` |
| Prefix | `/api` (`bootstrap/app.php` → `apiPrefix: 'api'`) — **بدون versioning** (`/api/v1` وجود ندارد) |
| پورت محلی | `3000` (`composer dev/serve` → `php artisan serve --port=3000`، `.env.example` → `PORT=3000`) |
| Production | `https://api.charjino.ir/api` (nginx: `deploy/charjino/nginx/api.conf`) |
| Auth | JWT HS256 دست‌ساز (`app/Support/Jwt.php`) + refresh token مات در DB؛ کاربر فروشگاه با OTP موبایل، ادمین با ایمیل/رمز |
| سفارش / پرداخت | **پیاده شده:** `draft` → mock PSP → `paid` → UUID تأمین در DB → `fulfilling` (dry-run تا `ALLOW_LIVE_ORDER=yes`)؛ webhook HMAC + poll 3/60s |
| صفحه‌بندی | کاتالوگ عمومی + محصولات ادمین + سفارش‌ها `{items, meta}` |
| تأمین‌کننده | Shop2TopUp — validate/price زنده؛ `orders/create` فقط با `ALLOW_LIVE_ORDER=yes` و confirm؛ وگرنه dry-run |

### شمارش endpointها بر اساس tag

| Tag | تعداد | endpointها |
|---|---|---|
| System | 2 (+3 web) | `GET /health`، `GET /platform/policy` (+ `GET /`، `GET /storage/{path}`، `GET /up`) |
| Auth | 6 | request-otp، verify-otp، refresh، admin/login، logout، me |
| Catalog (public) | 5 | `GET /catalog/home`، `GET /catalog/categories`، `GET /catalog/categories/{slug}`، `GET /catalog/products`، `GET /catalog/products/{handle}` |
| Admin · Users | 1 | `GET /admin/users` |
| Admin · Categories | 5 | `GET/POST /admin/catalog/groups`، `GET/PUT/DELETE /admin/catalog/groups/{group}` |
| Admin · Products | 6 | `GET/GET{id}/PUT{id}/DELETE{id} /admin/catalog/products`، `POST .../refresh-requirements`، `PUT /admin/catalog/skus/{sku}` |
| Admin · Pricing | 3 | `GET/PUT /admin/settings`، `POST /admin/settings/fx` |
| Admin · Uploads | 1 | `POST /admin/uploads` |
| Admin · Supplier (Shop2TopUp) | 7 | `GET /admin/supplier`، `POST /admin/supplier/discover`، `GET /admin/supplier/sku-map`، `GET /admin/supplier/price`، `POST /admin/supplier/validate-player`، `GET+POST /admin/catalog/sync` |
| Checkout / Orders / Payments | 7 | `POST /checkout/validate-player`، `POST/GET /orders`، `GET /orders/{number}`، `POST /orders/{number}/pay`، `POST /payments/callback/{provider}`، `POST /webhooks/shop2topup` |
| Admin · Orders | 6 | `GET /admin/orders`، `GET /admin/orders/{order}`، `POST .../retry-fulfillment`، `POST .../mark-refunded`، `POST .../cancel`، `GET /admin/dashboard/summary` |

---

## ۱) محیط‌ها، پورت‌ها و متغیرهای محیطی

### پورت‌ها (مطابق کد فعلی)

| اپ | پوشه | پورت dev | منبع |
|---|---|---|---|
| Backend (Laravel) | `Backend-charjino` | `3000` → `http://127.0.0.1:3000/api` | `composer.json` scripts، `README.md` |
| Storefront (Next.js 16) | `Frontend-charjino` | `3001` | `package.json` → `next dev -p 3001` |
| Admin (Next.js 16) | `Admin-Front-charjino` | `3002` | `package.json` → `next dev -p 3002` |

Production (`deploy/charjino/README.md`، `nginx/*.conf`، `pm2/ecosystem.config.cjs`):
`charjino.ir`/`www` → PM2 `:3001` · `admin.charjino.ir` → PM2 `:3002` · `api.charjino.ir` → php-fpm (Laravel `public/`).

> **دربارهٔ «port mismatch» که در brief آمده بود** (ادمین روی 3001 و بک‌اند روی 8000): در کد فعلی **دیگر وجود ندارد**. ادمین روی 3002 است و هر دو فرانت `BACKEND_URL` را به `http://127.0.0.1:3000/api` می‌دهند (فقط host/port در `.env.local` چک شد، مقدارها کپی نشدند). عدد `8000` فقط در `deploy/backend.md` آمده که سند پروژهٔ دیگری (Boomobana) است.

### متغیرهای env (فقط نام‌ها)

**Backend-charjino/.env** (نام‌ها): `APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL, APP_TIMEZONE, APP_LOCALE, APP_FALLBACK_LOCALE, APP_FAKER_LOCALE, APP_MAINTENANCE_DRIVER, BCRYPT_ROUNDS, LOG_*, DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, SESSION_*, BROADCAST_CONNECTION, FILESYSTEM_DISK, QUEUE_CONNECTION, CACHE_STORE, MEMCACHED_HOST, REDIS_*, MAIL_*, AWS_*, VITE_APP_NAME, PORT, JWT_ACCESS_SECRET, JWT_REFRESH_SECRET, JWT_ACCESS_TTL, JWT_REFRESH_TTL, CORS_ORIGINS, DEV_OTP_CODE, ADMIN_EMAIL, ADMIN_PASSWORD, ADMIN_NAME, S2T_BASE, S2T_KEY, S2T_WEBHOOK_SECRET, ALLOW_LIVE_ORDER`
(در `config/charjino.php` یک fallback به `SHOP2TOPUP_API_KEY` هم خوانده می‌شود.)

| متغیر | کجا خوانده می‌شود | نقش |
|---|---|---|
| `JWT_ACCESS_SECRET` / `JWT_REFRESH_SECRET` | `config/charjino.php` | امضای access token (refresh secret در کد استفاده نمی‌شود؛ refresh token مات است) |
| `JWT_ACCESS_TTL` / `JWT_REFRESH_TTL` | همان | پیش‌فرض 1800s / 432000s |
| `DEV_OTP_CODE` | `IdentityService::nextOtpCode` | فقط وقتی `APP_ENV != production` |
| `ADMIN_*` | `AdminSeeder` | ساخت ادمین |
| `S2T_BASE`, `S2T_KEY` | `Shop2Client` | آدرس و کلید Shop2TopUp |
| `S2T_WEBHOOK_SECRET` | `Shop2WebhookService` | HMAC خام بدنهٔ `POST /webhooks/shop2topup` |
| `ALLOW_LIVE_ORDER` | `Shop2Client::liveOrderEnabled` | قفل ساخت سفارش زنده (`yes` = باز) |
| `CORS_ORIGINS` | `config/cors.php` | فقط `api/*`، `supports_credentials=true` |

**Frontend-charjino** (`.env.local` نام‌ها): `NEXT_PUBLIC_API_URL, NEXT_PUBLIC_APP_NAME, PORT, BACKEND_URL` — `.env.production`: `BACKEND_URL, NEXT_PUBLIC_APP_NAME`.
در کد **فقط `BACKEND_URL`** خوانده می‌شود (`src/lib/server-fetcher.ts`، `src/lib/request-access-token.ts`). `NEXT_PUBLIC_API_URL` و `NEXT_PUBLIC_APP_NAME` هیچ‌جا استفاده نمی‌شوند.

**Admin-Front-charjino** (`.env.local` نام‌ها): `PORT, NODE_ENV, BACKEND_URL, NEXT_PUBLIC_BASE_URL, NEXT_PUBLIC_SITE_URL, NEXT_PUBLIC_APP_NAME, NEXT_PUBLIC_PRODUCTION`.
در کد **فقط `BACKEND_URL`** (و `NODE_ENV` برای cookie `secure`) خوانده می‌شود (`src/lib/server-fetcher.ts`).

> هر دو فرانت API را **فقط سمت سرور** (Server Actions + `serverFetch`) صدا می‌زنند؛ مرورگر مستقیماً به بک‌اند وصل نمی‌شود، پس CORS عملاً برای آن‌ها مهم نیست.

---

## ۲) قرارداد پاسخ (Envelope) و خطا

منبع: `app/Support/ApiResponse.php` و `bootstrap/app.php`.

**موفق:**
```json
{ "success": true, "statusCode": 200, "data": { }, "message": "موفق", "timestamp": "2026-09-25T16:00:00+03:30" }
```
اگر `data` آبجکتی با فیلد `message` باشد، همان به `message` بالایی هم کپی می‌شود.

**خطا:**
```json
{ "success": false, "statusCode": 422, "message": "شماره همراه معتبر نیست.", "timestamp": "..." }
```
- `data` در خطای validation خالی است (فقط اولین پیام). برای `CODM_REGION_MISMATCH` فیلد `data` payload عمومی ریجن است (`accountCountry`, `suggestedVariant`, `suggestedHandle`).
- `error.code` برای خطاهای تأمین‌کننده پایدار است (`IP_NOT_ALLOWED`, `INSUFFICIENT_BALANCE`, `REGION_MISMATCH`, `CODM_REGION_MISMATCH`, `PLAYER_ID_INVALID`, …). `POST /checkout/validate-player` برای SKUهای Activision کالاف ابتدا `CodmRegionCheck` را اجرا می‌کند (`validateMode=codm_store`).

### جدول کدهای خطا

| HTTP | پیام نمونه | منشأ |
|---|---|---|
| 400 | `DEV_OTP_CODE نامعتبر است.` / خطاهای 400 تأمین‌کننده | `ApiException` |
| 401 | `احراز هویت نامعتبر است` | نبود/خرابی/انقضای Bearer (`AuthenticateJwt`، `Jwt::decode`) |
| 401 | `کد تأیید نامعتبر است.` / `کد تأیید منقضی شده است.` | verify-otp |
| 401 | `توکن بازیابی موجود نیست` | refresh |
| 401 | `ایمیل یا رمز عبور نادرست است.` | admin/login |
| 401/403/… | پیام فارسی نگاشت‌شده از Shop2 (`INVALID_API_KEY`، `IP_NOT_ALLOWED`، `INSUFFICIENT_BALANCE`) با همان status تأمین‌کننده | `Shop2Client::request` |
| 403 | `دسترسی مجاز نیست.` | JWT معتبر ولی `role != admin` (`EnsureAdmin`) |
| 404 | `مسیر پیدا نشد.` | route نامعتبر یا id ناموجود در route-model binding |
| 404 | `محصول پیدا نشد.` | `GET /catalog/products/{handle}` |
| 422 | اولین پیام validation | `ValidationException` |
| 429 | `تعداد درخواست بیش از حد مجاز است.` / `تعداد تلاش بیش از حد مجاز است.` | `throttle:*` یا محدودیت OTP |
| 503 | `کلید تأمین‌کننده تنظیم نشده است.` / `اتصال به تأمین‌کننده برقرار نشد…` / `گرفتن نرخ دلار انجام نشد…` | Supplier / FX |
| 500 | `درخواست انجام نشد` (متن exception فقط با `APP_DEBUG=true`) | catch-all |

---

## ۳) احراز هویت (Auth flow)

منابع: `routes/api.php`، `AuthController`، `IdentityService`، `Jwt`، `AuthenticateJwt`، `EnsureAdmin`؛ فرانت‌ها: `src/actions/auth.actions.ts`، `src/lib/auth-cookies.ts`.

### کاربر فروشگاه (OTP)
```
[Frontend /auth] ── POST /api/auth/request-otp {phoneNumber} ──► کد ۶ رقمی (dev: DEV_OTP_CODE) ؛ TTL=120s
                 ── POST /api/auth/verify-otp {phoneNumber, code} ──► {access_token, refresh_token, user}
   → Server Action کوکی‌های httpOnly `access_token` (30min) و `refresh_token` (5 روز) را ست می‌کند
   → src/proxy.ts قبل از هر درخواست صفحه، اگر access تا ۳۰ ثانیه دیگر منقضی شود، POST /api/auth/refresh می‌زند
```
- نرمال‌سازی شماره: ارقام فارسی/عربی، پیشوند `+98`/`0098`/`98` → `09XXXXXXXXX` (`app/Support/Phone.php`).
- محدودیت‌ها: route `throttle:10,10` (request-otp)، `throttle:20,10` (verify-otp)؛ حداکثر ۵ درخواست OTP برای هر شماره در ۶۰۰ ثانیه؛ حداکثر ۵ تلاش برای هر کد.
- اولین ورود = ساخت کاربر `role=user, status=active`.
- **ارسال SMS در کد وجود ندارد** (در non-production کد در لاگ نوشته می‌شود با شمارهٔ mask‌شده).

### ادمین
```
[Admin /signin] ── POST /api/auth/admin/login {email, password} ──► {access_token, refresh_token, user{id,email,role:'admin'}}
   → اگر «مرا وارد نگه دار» تیک خورده باشد هر دو کوکی، وگرنه فقط access_token
   → (admin)/layout.tsx با GET /api/auth/me نقش admin را چک می‌کند
```
- **پنل ادمین هیچ مکانیزم refresh ندارد** (نه `proxy.ts`، نه route `/api/auth/refresh`؛ ثابت `AUTH_REFRESH_PATH` بلااستفاده است) → بعد از ۳۰ دقیقه نشست ادمین قطع می‌شود.

### توکن‌ها
| | Access | Refresh |
|---|---|---|
| نوع | JWT HS256 | رشتهٔ مات ۹۶ کاراکتری hex (hash sha256 در جدول `refresh_tokens`) |
| TTL | `JWT_ACCESS_TTL` = 1800s | `JWT_REFRESH_TTL` = 432000s |
| Claims | `sub, role, phoneNumber, email, iat, exp` | — |
| چرخش | — | **ندارد** (refresh فقط access جدید می‌دهد) |
| ابطال | تا exp معتبر است | `POST /auth/logout` همهٔ refresh tokenهای کاربر (همهٔ دستگاه‌ها) را revoke می‌کند |

`GET /api/auth/me`: برای ادمین `{id, email, role:"admin"}`، برای کاربر `{id, phoneNumber, avatarUrl, role:"user"}`.

---

## ۴) سلسله‌مراتب دسته → محصول → بسته (Category / Product / SKU)

منابع: migrationهای `2026_09_24_000002_create_catalog_tables.php` و `2026_09_25_000001_add_shop2_catalog_and_settings.php`، مدل‌های `CatalogGroup`، `Product`، `Sku`، سرویس `CatalogSyncService`.

```
catalog_groups (دسته)          ← Shop2TopUp big-category     (shop2_big_category_id)
   └─ products (محصول/بازی)    ← Shop2TopUp category         (shop2_category_id)   handle = slug
        └─ skus (بسته/Package)  ← Shop2TopUp subcategory item (shop2_item_id)       price = تومان
```

| جدول | فیلدهای کلیدی | نکته |
|---|---|---|
| `catalog_groups` | `id, shop2_big_category_id (unique), name, slug (unique), title, description, image_url, visible, sort_order` | seed و sync می‌سازند؛ slug از نام |
| `products` | `id, catalog_group_id (FK nullOnDelete), shop2_category_id, shop2_big_category_id, handle (unique), code (unique), title, name_en, publisher, category_name, subtitle, description, thumbnail, image_url, is_popular, visible, fulfillment_type, inputs (json), requirements (json), sort_order, status` | `status ∈ {active, draft, hidden}` |
| `skus` | `id, product_id, shop2_item_id (unique), code, name, fulfillment_type, price (تومان), cost_usd, live_cost_usd, region, status, visible, sort_order` | `unique(product_id, code)` |
| `banners`, `flash_sale_items` | داده‌های ثابت صفحهٔ اصلی | فقط seed (`database/data/catalog.php`)؛ endpoint مدیریتی ندارد |
| `settings` | key/value: `margin_percent, usd_toman_rate, fx_mode, usd_toman_source, usd_toman_updated_at` | با cache ۶۰ ثانیه |
| `users` | `id (uuid), phone, email, name, password, role (admin/user), avatar_url, status` | |

**قاعدهٔ نمایش عمومی:** محصول فقط وقتی `status=active` و `visible=true` باشد و دسته‌اش (اگر دارد) `visible=true` باشد. بسته‌ها هم همین‌طور. دستهٔ مخفی از `/catalog/categories` و هوم حذف می‌شود و `GET /catalog/categories/{slug}` برابر ۴۰۴ است.

**Sync حافظهٔ ویرایش ادمین را نگه می‌دارد:** عنوان فارسی، توضیح، عکس، visible و sort_order موجود بازنویسی نمی‌شوند.

**قیمت‌گذاری** (`PricingService`): `price = round(costUsd × usdTomanRate × (1 + margin/100) / 100) × 100` تومان. هر تغییر در نرخ/سود همهٔ SKUها را re-price می‌کند. نرخ خودکار از Nobitex (`USDTIRT`) و سپس Tetherland؛ زمان‌بندی روزانه ۰۹:۱۰ (`routes/console.php`). قیمت SKU از پنل **قابل ویرایش مستقیم نیست**.

---

## ۵) جدول کامل endpointها

> Base: `http://127.0.0.1:3000/api` (dev) / `https://api.charjino.ir/api` (prod). 🔓 = عمومی، 🔑 = Bearer، 🛡 = Bearer + role admin.

### System
| Method | Path | Auth | Handler | پاسخ `data` |
|---|---|---|---|---|
| GET | `/health` | 🔓 | `Api/HealthController` | `{app, status, language, stack, supplierConfigured, liveOrderEnabled, database}` |
| GET | `/platform/policy` | 🔓 | `Api/PlatformController@policy` | فلگ‌های سیاست (`neverAskGamePassword`، `noCrypto`، …) |
| GET | `/` (بدون /api) | 🔓 | `routes/web.php` | `{app, stack, health}` |
| GET | `/storage/{path}` (بدون /api) | 🔓 | `routes/web.php` | فایل آپلودشده |
| GET | `/up` (بدون /api) | 🔓 | Laravel health | HTML |

### Auth
| Method | Path | Auth | Body | پاسخ `data` |
|---|---|---|---|---|
| POST | `/auth/request-otp` | 🔓 | `{phoneNumber}` | `{message}` |
| POST | `/auth/verify-otp` | 🔓 | `{phoneNumber, code}` | `{access_token, refresh_token, user}` |
| POST | `/auth/refresh` | 🔓 | `{refresh_token}` | `{access_token}` |
| POST | `/auth/admin/login` | 🔓 | `{email, password(min 6)}` | `{access_token, refresh_token, user}` |
| POST | `/auth/logout` | 🔑 | — | `{message}` |
| GET | `/auth/me` | 🔑 | — | AuthUser |

### Catalog (public)
| Method | Path | Auth | پاسخ `data` |
|---|---|---|---|
| GET | `/catalog/home` | 🔓 | `{banners[], flashSale[], groups[{id, slug, name, description?, imageUrl?, products[]}], popular[≤8]}` |
| GET | `/catalog/categories` | 🔓 | `PublicCategory[]` فقط visible |
| GET | `/catalog/categories/{slug}` | 🔓 | `{category, products[], meta{page, perPage, total, totalPages}}` |
| GET | `/catalog/products` | 🔓 | بدون `page`/`perPage`/`category`: `PublicProduct[]`؛ با هر کدام: `{items[], meta}` (`?q=` `?popular=` `?category=slug`) |
| GET | `/catalog/products/{handle}` | 🔓 | `PublicProduct + {skus[], requirements[], category{id,slug,title}?}` |

`PublicProduct` = `{id, categoryName, code, handle, inputs[], isPopular, publisher, subtitle, description, thumbnail, title, minPrice}`
`PublicSku` = `{id, code, name, price, costUsd, fulfillmentType}` ← ⚠️ `costUsd` (بهای خرید دلاری از تأمین‌کننده) عمومی است.

### Admin (همه 🛡 — handler در `app/Http/Controllers/Api/Admin/`)
| Method | Path | Handler | Body | پاسخ `data` |
|---|---|---|---|---|
| GET | `/admin/users` | `AdminUserController@index` | — | `{items: [{id, name, email, phoneNumber, role, status, createdAt}]}` |
| GET | `/admin/catalog/groups` | `AdminCatalogController@groups` | — | `{items: AdminCategory[]}` |
| POST | `/admin/catalog/groups` | `@storeGroup` | `{title, slug?, description?, imageUrl?, visible?, sortOrder?}` | `AdminCategory` (`synced=false`) |
| GET | `/admin/catalog/groups/{group}` | `@group` | — | `AdminCategory` |
| PUT | `/admin/catalog/groups/{group}` | `@updateGroup` | `{title?, slug?, description?, imageUrl?, visible?, sortOrder?}` | `AdminCategory` |
| DELETE | `/admin/catalog/groups/{group}` | `@destroyGroup` | — | همگام: `visible=false`؛ دستی خالی: `{id}`؛ با محصول: 409 `CATEGORY_HAS_PRODUCTS` |
| GET | `/admin/catalog/products` | `@products` | `?category=&status=&q=&visible=&page=&perPage=` | `{items: AdminProduct[], meta}` (`skus: []`) |
| GET | `/admin/catalog/products/{product}` | `@product` | — | `AdminProduct` با `skus` |
| PUT | `/admin/catalog/products/{product}` | `@updateProduct` | `{title?, catalogGroupId?, handle?, inputs?, ...}` | `AdminProduct` |
| DELETE | `/admin/catalog/products/{product}` | `@hideProduct` | — | `status=hidden`، `visible=false` |
| POST | `/admin/catalog/products/{product}/refresh-requirements` | `@refreshRequirements` | — | `AdminProduct` (بدون password/otp) |
| PUT | `/admin/catalog/skus/{sku}` | `@updateSku` | `{name?, visible?, status?, price?}` | `AdminSku` (قیمت فروش رند ۱۰۰ تومان) |
| GET | `/admin/settings` | `AdminSettingsController@show` | — | `PricingSnapshot` |
| PUT | `/admin/settings` | `@update` | `{usdTomanRate?, marginPercent?(0–100), fxMode?(auto/manual)}` | `PricingSnapshot` |
| POST | `/admin/settings/fx` | `@refreshFx` | — | `PricingSnapshot + {refreshed, repricedSkus?, reason?}` |
| POST | `/admin/uploads` | `@upload` | multipart `file` (image ≤ 4MB) | `{url, path}` |
| GET | `/admin/supplier` | `AdminSupplierController@show` | — | `{configured, liveOrderEnabled, host, seeds[], skuCount, account?{email, wallet, currency, active}, accountError?, accountErrorCode?}` |
| POST | `/admin/supplier/discover` | `@discover` | — | sku-map PUBG/COD (بدون secret) |
| GET | `/admin/supplier/sku-map` | `@skuMap` | — | همان نقشه |
| GET | `/admin/supplier/price` | `@price` | `itemId` یا `internalCode` | `{itemId, unitPrice, currency}` |
| POST | `/admin/supplier/validate-player` | `@validatePlayer` | `{playerId, itemId?\|internalCode?, server?, zoneId?}` | `{playerName, region?, zoneId?, itemId}` |
| POST | `/admin/catalog/sync` | `AdminCatalogController@sync` | — | `{status, startedAt, finishedAt, durationMs, account{email, wallet, currency, active}, groups, products, skus, pricing}` |
| GET | `/admin/catalog/sync` | `@syncStatus` | — | `{status: idle\|running\|completed\|failed, ...}` بدون `raw` |
| GET | `/admin/orders` | `AdminOrderController@index` | `?status=&q=&from=&to=&page=` | `{items, meta}` |
| GET | `/admin/orders/{order}` | `@show` | id یا number | سفارش + پرداخت + تلاش تحویل (بدون کلید/رمز) |
| POST | `/admin/orders/{order}/retry-fulfillment` | `@retryFulfillment` | — | همان UUID تأمین |
| POST | `/admin/orders/{order}/mark-refunded` | `@markRefunded` | `{reason?}` | وضعیت `refunded` — **سیاست تومان، نه کش‌اوت کوین** |
| POST | `/admin/orders/{order}/cancel` | `@cancel` | — | فقط `draft`/`pending_payment` |
| GET | `/admin/dashboard/summary` | `@summary` | — | `{ordersToday, revenueTodayToman, pendingFulfillment, failedToday, supplierWallet, accountError?}` |

**فیلدهای `AdminCategory`:** `id, shop2BigCategoryId, name, slug, title, description, imageUrl, visible, synced, sortOrder, productsCount`
**فیلدهای `AdminProduct`:** `id, catalogGroupId, shop2CategoryId, shop2BigCategoryId, handle, code, title, nameEn, publisher, categoryName, subtitle, description, thumbnail, imageUrl, displayImage, isPopular, visible, fulfillmentType, status, sortOrder, skusCount, skus[]`
**فیلدهای `AdminSku`:** `id, productId, shop2ItemId, code, name, fulfillmentType, price, costUsd, liveCostUsd, status, visible, sortOrder`

> **مانده نسبت به PROPOSED:** ساخت محصول دستی بدون تأمین‌کننده (اختیاری)، PATCH کاربر، audit log تغییرات قیمت. بنر و فروش ویژه از قبل در `/admin/home` و `/admin/banners` و `/admin/flash-sale` هستند.

---

## ۶) جریان سفارش (Player ID only) — وضعیت فعلی

سفارش فروشگاه پیاده شده: `POST /orders` → mock PSP → `paid` → UUID تأمین در DB → `fulfilling` (dry-run تا `ALLOW_LIVE_ORDER=yes`). ادمین لیست/جزئیات/لغو پرداخت‌نشده/refund سیاست‌محور تومان را از `/admin/orders` و داشبورد را از `/admin/dashboard/summary` می‌خواند — **بدون کش‌اوت کوین**.

1. `inputs`/`requirements` فقط فیلدهای مجاز بازیکن؛ password/otp/pin/login رد می‌شوند (`BuyerInputFields`).
2. `Shop2Client::createOrder()` سه قفل دارد: `ALLOW_LIVE_ORDER=yes`، `confirm`، موجودی مثبت. بدون این‌ها fulfillment خشک است.
3. قانون محصول: **فقط Player ID (و فیلدهای requirements تأمین‌کننده)؛ هرگز رمز/OTP بازی.**

---

## ۷) صفحه‌بندی، فیلتر، مرتب‌سازی

- عمومی: `GET /catalog/categories/{slug}` همیشه `{products, meta}` دارد (`page` پیش‌فرض ۱، `perPage` پیش‌فرض ۲۰، حداکثر ۱۰۰).
- `GET /catalog/products?q=` و `?popular=1` بدون `page`/`category` هنوز آرایهٔ خام است (جستجوی هدر). با `category` یا `page`/`perPage` پاسخ `{items, meta}` است.
- ترتیب ثابت `sort_order ASC, id ASC` (کاربران: `created_at DESC`).
- لیست ادمین محصولات و سفارش‌ها `{items, meta}` است؛ دسته‌ها همچنان `{items}` بدون صفحه.

## ۸) آپلود فایل

`POST /api/admin/uploads` — `multipart/form-data` با فیلد `file` (قاعدهٔ Laravel `image`، حداکثر 4096KB) → ذخیره در `storage/app/public/catalog/` → پاسخ `{url: APP_URL/storage/catalog/xxx, path}`. سرو از `GET /storage/{path}` (`routes/web.php`، با محافظت path traversal). ادمین آن را در `components/admin/catalog-image-field.tsx` صدا می‌زند و URL را در `imageUrl` دسته/محصول ذخیره می‌کند. `next.config` هر دو فرانت `http://127.0.0.1:3000` و `https://**` را برای `next/image` مجاز کرده‌اند.

---

## ۹) Shop2TopUp (تأمین‌کننده)

### منابع رسمی (پیدا شد و خوانده شد)
- معرفی و webhook: https://shop2topup.com/en/reseller-api
- مرجع: https://shop2topup.com/fa/reseller-api/docs
- سند داخلی: `C:\Users\Asus\Desktop\charjino\Shop2TopUp-Cursor-Integration.md` (برای برند GamerSho نوشته شده، ولی همان قرارداد)
- آزمایشگاه: پوشهٔ `api مورد استفاده که در بکند باید استفاده شود از shop2` (Next.js lab؛ شامل webhook receiver نمونه)

### قرارداد پایه (از مستند رسمی)
- Base: `https://shop2topup.com/api/endpoints/v1` — sandbox جدا ندارد؛ همه‌چیز production است.
- Auth: `Authorization: Bearer <keyId>.<secret>` — فقط سرور به سرور.
- **IP allowlist**: اگر لیست IP کلید خالی نباشد، هر درخواست از IP دیگر با `403 IP_NOT_ALLOWED` قبل از هر منطقی رد می‌شود ⇒ فقط سرور production (IP خروجی VPS) باید صدا بزند؛ لپ‌تاپ توسعه‌دهنده باید جدا allowlist شود.
- پاسخ همیشه `success` بولی دارد؛ خطا `{success:false, error:{code, message, action, retryable, retry_after, details}}` — روی `code` شرط بگذار، نه `message`.
- مبالغ: رشتهٔ اعشاری USD مثل `"0.950000"`.
- لیست‌ها `pagination{page, limit, total, total_pages}` دارند.
- `order_id` = UUID خود ما = کلید idempotency.

### ۱۱ endpoint رسمی و وضعیت استفاده در بک‌اند شارژینو

| Method | Path | در `Shop2Client` | استفادهٔ فعلی |
|---|---|---|---|
| GET | `/account` | `account()` | `GET /api/admin/supplier`، sync |
| GET | `/catalog/big-categories` | `bigCategories()` | sync → `catalog_groups` |
| GET | `/catalog/categories?bigCategoryId=` | `categories()` | sync → `products` |
| GET | `/catalog/subcategories?categoryId=` | `subcategories()` | sync → `skus` |
| GET | `/catalog/subcategory/:itemId/price` | `price()` | **استفاده نمی‌شود** |
| GET | `/catalog/category/:categoryId/requirements` | `requirements()` | **استفاده نمی‌شود** |
| POST | `/player/validate` | `validatePlayer()` | checkout + ادمین |
| POST | `/orders/create` | `createOrder()` (قفل‌دار) | worker فقط اگر `ALLOW_LIVE_ORDER=yes`؛ وگرنه dry-run |
| GET | `/orders/:orderId` | `getOrder()` | webhook + poll (حداکثر ۳/۶۰ثانیه) |
| POST | `/orders/batch` | — | پیاده نشده |
| GET | `/orders` | — | پیاده نشده |
| Webhook | `order.completed`, `order.refunded`, `webhook.test` | `Shop2WebhookController` | HMAC روی raw body + idempotent |

### رفتارهای پیاده‌شده در `Shop2Client::request`
- IPv4 اجباری + resolve دستی DNS (`Shop2HostResolver`: DNS سیستم → DoH کلادفلر/گوگل → IP ثابت fallback `104.26.8.143` برای `shop2topup.com`).
- timeout اتصال ۱۵s، کل ۳۰s.
- روی 429 تا ۳ بار retry با `Retry-After`.
- نگاشت خطا به فارسی: `INVALID_API_KEY`، `IP_NOT_ALLOWED`، `INSUFFICIENT_BALANCE`؛ بقیه `error.message` خام.
- sync برای هر محصول ۲۰۰ms و هر دسته ۱۵۰ms صبر می‌کند (زیر rate limit کاتالوگ ۵۰۰۰/۶۰s).

### محدودیت‌های نرخ رسمی (مهم برای طراحی سفارش)
`GET /catalog/*` 5000/60s · `POST /orders/create` 5000/60s · `GET /orders/:orderId` **3/60s به‌ازای هر سفارش** · `POST /orders/batch` 2/60s · `GET /orders` 2/60s · `GET /account` 60/60s · `POST /player/validate` سهمیهٔ روزانه.

### Webhook (از مستند رسمی)
هدرها: `X-Shop2Topup-Signature: sha256=<hex HMAC-SHA256 روی raw body>`، `X-Shop2Topup-Event`. باید ظرف ۱۰ ثانیه 200 بدهد؛ **retry خودکار ندارد** ⇒ poll پشتیبان لازم است. وضعیت‌ها: `pending` (غیرنهایی)، `completed`، `partial`، `refunded` (نهایی).

---

## ۱۰) جدول عدم‌تطابق Frontend ↔ Backend (Mismatch)

| # | موضوع | Frontend / Admin | Backend | اثر |
|---|---|---|---|---|
| M1 | **سفارش / پرداخت** | `/invoices` هنوز به API وصل نیست | `POST /orders` + mock PSP + dry-run fulfill + webhook | فروش واقعی زنده هنوز قفل است (`ALLOW_LIVE_ORDER=no`) |
| M15 | Webhook تأمین‌کننده | — | `POST /webhooks/shop2topup` + `charjino:poll-fulfillment` | URL را باید در پنل Shop2 ثبت کرد |
| M2 | **صفحهٔ دسته‌بندی عمومی** | route دسته ندارد؛ `groups` فقط در home | **رفع شد در API:** `GET /catalog/categories`، `GET /catalog/categories/{slug}`، `groups[].id`/`slug`، `?category=` | فرانت هنوز صفحهٔ `/catalog/category/[slug]` ندارد |
| M3 | **CRUD دسته** | `groups/[id]/page.tsx` کل لیست را می‌گیرد و find می‌کند | فقط list + update؛ create/delete/show نیست | |
| M4 | **CRUD محصول با انتخاب دسته** | `product-edit-screen.tsx` فیلد دسته ندارد | `updateProduct` فیلد `catalogGroupId` را نمی‌پذیرد؛ create/delete نیست | «products CRUD with category select» فعلاً ممکن نیست |
| M5 | Type drift ادمین | `src/types/admin.ts`: `AdminProduct` فاقد `code`، `shop2BigCategoryId`؛ `SupplierStatus` فاقد `seeds`، `account.currency/raw`؛ `SyncResult` فاقد `pricing` | این فیلدها برمی‌گردند | کم‌خطر؛ با type تولیدی از OpenAPI رفع شود |
| M6 | Type drift فروشگاه | `src/types/catalog.ts`: `CatalogProduct` فاقد `id`؛ `requirements?: unknown` | `id` هست؛ `requirements` آرایه | کم‌خطر |
| M7 | env بلااستفاده | فروشگاه: `NEXT_PUBLIC_API_URL`، `NEXT_PUBLIC_APP_NAME`؛ ادمین: `NEXT_PUBLIC_BASE_URL`، `NEXT_PUBLIC_SITE_URL`، `NEXT_PUBLIC_APP_NAME`، `NEXT_PUBLIC_PRODUCTION` — در کد خوانده نمی‌شوند؛ فقط `BACKEND_URL` | — | سردرگمی؛ خطر لو رفتن آدرس داخلی در bundle اگر کسی بعداً استفاده کند |
| M8 | پورت (brief) | ادمین 3002، فروشگاه 3001، هر دو → `127.0.0.1:3000/api` | 3000 | **تطابق دارد**؛ فقط `deploy/backend.md` (سند Boomobana) عدد 8000 دارد |
| M9 | Refresh ادمین | refresh token ذخیره می‌شود ولی هیچ‌جا مصرف نمی‌شود | `POST /auth/refresh` موجود | خروج اجباری ادمین بعد از ۳۰ دقیقه |
| M10 | نشت بهای خرید | `CatalogSku.costUsd?` در type فروشگاه | `Sku::toApi()` → `costUsd` عمومی | افشای حاشیهٔ سود |
| M11 | فیلدهای ورودی خریدار | فرم ندارد | `inputs`/`requirements` برای محصولات sync‌شده خالی؛ `requirements()` صدا زده نمی‌شود | فرم Player ID داینامیک ممکن نیست |
| M12 | قیمت‌های hard-code | `src/data/home.ts` (fallback: `CATALOG_GROUPS`, `FLASH_SALE_ITEMS`, `POPULAR_PRODUCTS` با قیمت) و استفادهٔ هدر از آن | — | قیمت غیرواقعی در UI هنگام قطعی API |
| M13 | فروش ویژه/بنر | نمایش از API | فقط seed؛ قیمت flash sale ثابت و جدا از pricing | قیمت flash sale با نرخ دلار به‌روز نمی‌شود |
| M14 | Leaderboard | `src/data/leaderboard.ts` استاتیک | endpoint ندارد | |
| M16 | کد مرده | — | `PlatformController::supplier` بدون route؛ کلاس `Allowlist` استفاده نمی‌شود؛ `Seeds` استفاده نمی‌شود | |
| M17 | دیتابیس | — | README: SQLite پیش‌فرض؛ `.env(.example)`: mysql؛ rule Cursor: PostgreSQL؛ docker-compose: Postgres؛ deploy: MariaDB؛ تست: sqlite memory | ابهام |
| M18 | قوانین Cursor | `Admin-Front-charjino/.cursor/rules/admin-frontend-red-lines.mdc` به مسیرهای پروژهٔ **Boomobana** اشاره می‌کند | `.cursor/rules/charjino-backend.mdc` با glob `Backend-charjino/**/*.ts` هرگز روی PHP اعمال نمی‌شود | Cursor ممکن است منبع اشتباه را دنبال کند |
| M19 | صفحات قالب ادمین | calendar، support، profile، figma، basic-tables، form-elements با دادهٔ mock | endpoint ندارند | |
| M20 | FX mode | صفحهٔ pricing دکمهٔ refresh | `POST /admin/settings/fx` اول `fx_mode=auto` می‌کند ⇒ شاخهٔ «نرخ دستی فعال است» از این مسیر دست‌نیافتنی | رفتار غیرمنتظره |
| M21 | visibility دسته | — | **رفع شد:** لیست/جزئیات محصول و صفحهٔ دسته، گروه مخفی را حذف/۴۰۴ می‌کنند | |
| M22 | کارایی | — | `/catalog/products` برای `minPrice` SKUها را lazy-load می‌کند (N+1)؛ لیست‌های ادمین بدون صفحه‌بندی | با کاتالوگ کامل Shop2 کند می‌شود |

---

## ۱۱) موارد نامعلوم / محدودیت‌های این بررسی

- کد روی سرور production بررسی نشد (طبق دستور)؛ فقط snapshot محلی. `bootstrap/app.php` و فایل‌های فرانت امروز ساعت ۱۶:۰۱ توسط Cursor تغییر کرده بودند؛ ممکن است بعد از این snapshot تغییر کنند.
- شکل دقیق پاسخ `GET /account` و آیتم‌های کاتالوگ Shop2 در کد با چند کلید جایگزین خوانده می‌شود (`Payload.php`)؛ یعنی نویسندهٔ کد هم از شکل قطعی مطمئن نبوده. سند رسمی نمونهٔ `categories` را نشان می‌دهد (`id, name, description, big_category_id, big_category_name`).
- مرجع کامل ۴۲ کد خطای Shop2 فقط لینک شد (صفحهٔ جزئیات خوانده نشد).
- `database/database.sqlite` و `storage/logs/laravel.log` باز نشدند (احتمال دادهٔ حساس).
