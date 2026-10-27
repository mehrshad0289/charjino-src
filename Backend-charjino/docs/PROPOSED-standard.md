# ⚠️ PROPOSED — پیشنهاد استانداردسازی API شارژینو

> **هیچ‌کدام از endpointها، فیلدها و جدول‌های این فایل هنوز در کد وجود ندارند.**
> این سند پیشنهاد است و فقط با تأیید کاربر (صغری نوری) باید پیاده شود. API فعلی (as-built) در `openapi.yaml` و `API-DOCS-fa.md` است.
> اصل طراحی: **هم‌راستا با چیزی که هست، نه بازنویسی.** هرچه الان کار می‌کند (Envelope، JWT، OTP، Server Actions، `serverFetch`) حفظ می‌شود.

---

## ۱) چرا پیشنهاد لازم است (خلاصهٔ کمبودها)

| کمبود | مرجع در کد فعلی |
|---|---|
| هیچ سفارش/پرداخت/تحویلی نیست | `routes/api.php` |
| دسته‌ها `id`/`slug` عمومی ندارند؛ صفحهٔ دسته ممکن نیست | `CatalogService::home` |
| CRUD ناقص دسته/محصول؛ تغییر دستهٔ محصول ممکن نیست | `AdminCatalogController` |
| صفحه‌بندی و فیلتر نیست | همهٔ controllerها |
| خطا کد ماشینی و map فیلد ندارد | `bootstrap/app.php` |
| `costUsd` به خریدار نشان داده می‌شود | `Sku::toApi()` |
| refresh token ادمین مصرف نمی‌شود | `Admin-Front-charjino/src/lib/*` |
| versioning نیست | `bootstrap/app.php` → `apiPrefix: 'api'` |

---

## ۲) قراردادهای عمومی (PROPOSED)

### ۲.۱ Versioning
- گروه route جدید با prefix `/api/v1` که **همان controllerهای فعلی** را mount می‌کند.
- `/api/*` فعلی تا مهاجرت کامل هر دو فرانت به‌عنوان alias باقی بماند (deprecation بعد از انتشار نسخهٔ جدید فرانت‌ها).
- `BACKEND_URL` فرانت‌ها بعد از مهاجرت: `http://127.0.0.1:3000/api/v1` و `https://api.charjino.ir/api/v1`.

### ۲.۲ Envelope — گسترش سازگار با عقب (نه جایگزینی)
شکل فعلی `{success, statusCode, data, message, timestamp}` حفظ می‌شود و فقط دو فیلد **اختیاری** اضافه می‌شود:

```jsonc
// موفق + لیست صفحه‌بندی‌شده
{
  "success": true, "statusCode": 200, "message": "موفق", "timestamp": "…",
  "data": [ /* items */ ],
  "meta": { "page": 1, "perPage": 20, "total": 134, "totalPages": 7 }
}
// خطا
{
  "success": false, "statusCode": 422, "message": "شماره همراه معتبر نیست.", "timestamp": "…",
  "error": { "code": "VALIDATION_FAILED", "fields": { "phoneNumber": ["شماره همراه معتبر نیست."] } }
}
```
- `message` فارسی برای نمایش؛ `error.code` (انگلیسی، پایدار) برای منطق فرانت.
- دلیل انتخاب به‌جای `{data, meta, error}` خالص: `serverFetch` هر دو فرانت و تست `ApiContractTest` به `success`/`message` وابسته‌اند.
- لیست‌ها: `data` **همیشه آرایه** + `meta` (به‌جای `data.items` فعلی ادمین). در دورهٔ گذار، endpointهای ادمین فعلی `items` را حفظ کنند و endpointهای `/api/v1` شکل جدید را.

کدهای خطای پیشنهادی: `UNAUTHENTICATED, FORBIDDEN, NOT_FOUND, VALIDATION_FAILED, RATE_LIMITED, OTP_INVALID, OTP_EXPIRED, SUPPLIER_UNAVAILABLE, SUPPLIER_IP_NOT_ALLOWED, SUPPLIER_BALANCE_LOW, PLAYER_NOT_FOUND, PLAYER_CHECK_UNAVAILABLE, REGION_MISMATCH, PRICE_CHANGED, OUT_OF_STOCK, ORDER_STATE_INVALID, IDEMPOTENCY_CONFLICT, PAYMENT_FAILED, INTERNAL`.

### ۲.۳ صفحه‌بندی و فیلتر
- Query: `page` (پیش‌فرض 1)، `perPage` (پیش‌فرض 20، حداکثر 100)، `sort` (مثلاً `sortOrder`, `-createdAt`)، `q` (جستجوی متنی).
- فیلترها با نام صریح: `category={slug}`، `popular=1`، `status=`.

### ۲.۴ شناسه‌ها و slug
- عمومی: دسته با `slug`، محصول با `handle` (موجود).
- ادمین: همه با `id` عددی (موجود).
- migration پیشنهادی: `catalog_groups.slug` (unique، پر شده از `Str::slug(name)-{shop2_big_category_id}`؛ قابل ویرایش در ادمین).

### ۲.۵ جدایی Public / Admin
- Public: `/api/v1/catalog/*`، `/api/v1/checkout/*`، `/api/v1/orders/*` (کاربر لاگین).
- Admin: `/api/v1/admin/*` با `AuthenticateJwt` + `EnsureAdmin` (موجود).
- Resource جدا برای هر سطح: `toApi()` (public، **بدون** `costUsd`/`liveCostUsd`/`shop2*`) و `toAdmin()`.

### ۲.۶ Auth / نقش‌ها
- حفظ JWT فعلی. افزودن **refresh در پنل ادمین** (مشابه `Frontend-charjino/src/proxy.ts` + `src/app/api/auth/refresh/route.ts`).
- پیشنهاد اختیاری: چرخش refresh token در `POST /auth/refresh` (برگرداندن `refresh_token` جدید و revoke قبلی).
- نقش‌ها فعلاً `admin | user`؛ در صورت نیاز بعداً `support` (فقط خواندن سفارش‌ها).

---

## ۳) Catalog عمومی (PROPOSED)

| Method | Path | توضیح |
|---|---|---|
| GET | `/api/v1/catalog/categories` | **پیاده شد روی** `GET /api/catalog/categories` — `[{id, slug, title, description, imageUrl, productsCount}]` فقط visible |
| GET | `/api/v1/catalog/categories/{slug}` | **پیاده شد روی** `GET /api/catalog/categories/{slug}` — `{category, products[], meta}` |
| GET | `/api/v1/catalog/products` | **پیاده شد روی** `/api/catalog/products` با `?category=&q=&popular=&page=&perPage=`؛ بدون page/category هنوز آرایهٔ خام (سازگار با جستجوی فرانت) |
| GET | `/api/v1/catalog/products/{handle}` | **جزئیات فعلی +** `category{id,slug,title}` روی `/api/catalog/products/{handle}` |
| GET | `/api/v1/catalog/home` | **پیاده شد:** `groups[].id` و `groups[].slug` روی `GET /api/catalog/home` |

`inputFields` (از `GET /catalog/category/:categoryId/requirements` تأمین‌کننده، کش‌شده در `products.requirements` هنگام sync):
```json
[{ "name": "player_id", "label": "Player ID", "type": "text", "required": true, "options": null }]
```
> قانون قرمز: هیچ فیلد `password`/`otp` در `inputFields` مجاز نیست؛ بک‌اند هر فیلد با نام شامل `pass|otp|pin|login` را رد کند.

---

## ۴) Admin کاتالوگ (PROPOSED — تکمیل CRUD)

| Method | Path | توضیح |
|---|---|---|
| GET | `/api/v1/admin/catalog/groups/{id}` | دریافت تک دسته |
| POST | `/api/v1/admin/catalog/groups` | ساخت دستهٔ دستی (`shop2BigCategoryId = null`) `{title, slug?, description?, imageUrl?, visible, sortOrder}` |
| PUT | `/api/v1/admin/catalog/groups/{id}` | موجود + `slug` |
| DELETE | `/api/v1/admin/catalog/groups/{id}` | فقط اگر محصولی ندارد (وگرنه 409)؛ دسته‌های sync‌شده بهتر است `visible=false` شوند |
| GET | `/api/v1/admin/catalog/products` | موجود + `?category=&status=&q=&page=` |
| PUT | `/api/v1/admin/catalog/products/{id}` | موجود + `catalogGroupId`, `handle`, `inputs` |
| POST | `/api/v1/admin/catalog/products` | (اختیاری) محصول دستی بدون تأمین‌کننده — فقط اگر مدل کسب‌وکار لازم دارد |
| DELETE | `/api/v1/admin/catalog/products/{id}` | پیشنهاد: به‌جای حذف فیزیکی `status=hidden` (سفارش‌های قدیمی به محصول ارجاع دارند) |
| POST | `/api/v1/admin/catalog/products/{id}/refresh-requirements` | خواندن requirements از Shop2 و ذخیره |
| GET/POST/PUT/DELETE | `/api/v1/admin/banners`, `/api/v1/admin/flash-sale` | مدیریت محتوای home؛ قیمت flash sale از SKU مشتق شود نه عدد ثابت |
| PATCH | `/api/v1/admin/users/{id}` | `{status: active|blocked}` |

---

## ۵) وضعیت‌ها (Status enums) — PROPOSED

ساده‌شدهٔ ماشین حالت سند `ایده گیم.md` §۱۶.۳، هم‌تراز با وضعیت‌های Shop2TopUp:

| Order.status | معنی | ورود از |
|---|---|---|
| `pending_payment` | سفارش ساخته شد، منتظر پرداخت | ایجاد |
| `paid` | پرداخت PSP verify شد | callback PSP |
| `fulfilling` | `POST /orders/create` تأمین‌کننده پذیرفت (`pending`) | worker |
| `delivered` | تأمین‌کننده `completed` | webhook/poll |
| `partial` | تأمین‌کننده `partial` | webhook/poll |
| `failed` | خطای نهایی (مثلاً `REGION_MISMATCH`, `PLAYER_NOT_FOUND`) | worker |
| `refund_pending` → `refunded` | برگشت پول مشتری | ادمین/سیاست |
| `cancelled` / `expired` | لغو قبل از پرداخت / انقضای مهلت پرداخت | کاربر/cron |

`Payment.status`: `created, redirected, verify_pending, verified, failed, expired, review`
`Fulfillment.status`: `not_started, submitted, pending, completed, partial, refunded, failed, unknown`
(موجود و بدون تغییر: `products.status`/`skus.status` ∈ `active, draft, hidden`)

---

## ۶) جریان سفارش با Player ID (PROPOSED)

```
[صفحهٔ محصول] کاربر SKU را انتخاب و inputFields (فقط Player ID و فیلدهای مجاز) را پر می‌کند
  → POST /api/v1/checkout/validate-player {skuId, requirements}
        بک‌اند → Shop2 POST /player/validate  ⇒ {playerName, region}   (سهمیهٔ روزانه؛ throttle سمت ما)
  → نمایش «نام بازیکن: X» برای تأیید خریدار
  → POST /api/v1/orders   (Bearer کاربر، هدر Idempotency-Key: <uuid فرانت>)
        {skuId, quantity: 1, requirements}
        ⇒ قیمت تومانی در لحظه قفل می‌شود (priceToman, costUsd snapshot, fxRate snapshot)
        ⇒ {number: "CH…", status: "pending_payment", amountToman, expiresAt}
  → POST /api/v1/orders/{number}/pay  ⇒ {redirectUrl}   (PSP — نامعلوم؛ باید انتخاب شود)
  → PSP callback: POST /api/v1/payments/callback/{provider} → verify → Order=paid (در یک transaction + outbox)
  → Worker: supplier_order_id = UUID (قبل از HTTP ذخیره شود)
        GET price → expected_unit_price ؛ POST /orders/create
        روی PRICE_INCREASED: اگر اختلاف ≤ سقف مجاز، با همان UUID دوباره؛ وگرنه review
        روی DUPLICATE_ORDER / timeout: GET /orders/{uuid}
  → Webhook: POST /api/v1/webhooks/shop2topup  (verify HMAC روی raw body، پاسخ 200 < 10s، enqueue)
  → Poll پشتیبان برای pending قدیمی‌تر از N دقیقه (حداکثر 3 بار/دقیقه/سفارش؛ ترجیحاً POST /orders/batch)
  → Order=delivered / partial / failed → اطلاع به کاربر
```

### endpointهای پیشنهادی
| Method | Path | Auth | توضیح |
|---|---|---|---|
| POST | `/api/v1/checkout/validate-player` | 🔑 user | `{skuId, requirements}` → `{playerName, region?, zoneId?}` |
| POST | `/api/v1/orders` | 🔑 user + `Idempotency-Key` | ساخت سفارش؛ تکرار با همان key همان سفارش را برمی‌گرداند؛ key با body متفاوت → 409 `IDEMPOTENCY_CONFLICT` |
| GET | `/api/v1/orders` | 🔑 user | سفارش‌های خودم (صفحه‌بندی) |
| GET | `/api/v1/orders/{number}` | 🔑 user (مالک) | جزئیات + timeline (برای `/invoices/[invoice]`) |
| POST | `/api/v1/orders/{number}/pay` | 🔑 user | شروع پرداخت |
| POST | `/api/v1/payments/callback/{provider}` | امضای PSP | verify |
| POST | `/api/v1/webhooks/shop2topup` | HMAC `S2T_WEBHOOK_SECRET` | رویدادهای `order.completed/refunded/webhook.test` |
| GET | `/api/v1/admin/orders` | 🛡 | `?status=&q=&from=&to=&page=` |
| GET | `/api/v1/admin/orders/{id}` | 🛡 | سفارش + پرداخت‌ها + تلاش‌های تحویل + پاسخ خام تأمین‌کننده |
| POST | `/api/v1/admin/orders/{id}/retry-fulfillment` | 🛡 | فقط در `failed/unknown` و با همان UUID |
| POST | `/api/v1/admin/orders/{id}/mark-refunded` | 🛡 | ثبت دستی refund |
| GET | `/api/v1/admin/dashboard/summary` | 🛡 | `{ordersToday, revenueTodayToman, pendingFulfillment, failedToday, supplierWallet}` |

> صفحهٔ عمومی `/invoices` فعلاً با «شماره فاکتور» بدون لاگین جستجو می‌کند؛ پیشنهاد: یا لاگین اجباری، یا `number` + ۴ رقم آخر موبایل برای جلوگیری از enumeration.

### جدول‌های پیشنهادی (migration)
- `orders(id uuid, number unique, user_id, sku_id, product_id, quantity, requirements json (فقط فیلدهای مجاز، بدون رمز), player_name, amount_toman, cost_usd_snapshot, fx_rate_snapshot, status, idempotency_key unique, expires_at, timestamps)`
- `payments(id, order_id, provider, amount_toman, status, authority/ref, raw json, timestamps)`
- `fulfillment_attempts(id, order_id, supplier, supplier_order_id uuid unique, expected_unit_price, status, error_code, raw json, timestamps)`
- `supplier_webhook_events(id, event, supplier_order_id, signature_valid, payload json, processed_at)` — idempotent روی `(event, supplier_order_id)`
- `catalog_groups.slug`

---

## ۷) امنیت و عملیات (PROPOSED)

- حذف `costUsd` از `Sku::toApi()` (public).
- `GET /admin/supplier` فیلد `account.raw` را فیلتر کند (فقط email/wallet/active).
- `ALLOW_LIVE_ORDER` و سه قفل فعلی `createOrder` حفظ شوند؛ worker فقط وقتی `ALLOW_LIVE_ORDER=yes`.
- Shop2 فقط از IP سرور production صدا زده شود (allowlist)؛ در dev، sync را فقط اگر IP توسعه‌دهنده هم allowlist شده اجرا کن.
- لاگ: هرگز کلید، refresh token یا OTP در production.
- Audit log برای تغییرات ادمین روی قیمت/سود/وضعیت سفارش.
- یکسان‌سازی دیتابیس (پیشنهاد: MariaDB/MySQL مطابق deploy) و اصلاح README/rules.

---

## ۸) ترتیب پیشنهادی اجرا (فقط با تأیید کاربر)

1. بدون تغییر رفتار: `/api/v1` alias، حذف `costUsd` عمومی، افزودن `error.code`/`error.fields` (فیلدهای اضافه، سازگار).
2. Catalog: `slug` دسته + endpointهای عمومی دسته + فیلتر `category` + CRUD تکمیلی ادمین + `catalogGroupId` در update محصول.
3. requirements/inputFields از Shop2 در sync.
4. Orders بدون پرداخت واقعی (ALLOW_LIVE_ORDER=no) + UI.
5. PSP + webhook + worker + poll.
6. داشبورد ادمین از `dashboard/summary`.
