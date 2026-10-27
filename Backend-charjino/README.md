# Backend Charjino

API ماژولار فروشگاه چارژینو — **Laravel 12**، پیشوند `/api`، پاسخ `{ success, data, message }`.

سایت فارسی است (`fa` / `rtl`). فروشگاه روی `:3001` و ادمین روی `:3002` به این سرویس وصل می‌شوند.

## API Docs

منبع واحد قرارداد as-built در [`docs/openapi.yaml`](docs/openapi.yaml) است.

| فایل | محتوا |
|---|---|
| [`docs/openapi.yaml`](docs/openapi.yaml) | OpenAPI 3.1 — فقط چیزهایی که در کد هست |
| [`docs/API-DOCS-fa.md`](docs/API-DOCS-fa.md) | توضیح فارسی + جدول mismatch |
| [`docs/PROPOSED-standard.md`](docs/PROPOSED-standard.md) | endpointهای پیشنهادی (هنوز ساخته نشده) |
| [`docs/api-docs.html`](docs/api-docs.html) | نسخهٔ خواندنی |
| [`docs/redocly.yaml`](docs/redocly.yaml) | تنظیم lint |

```bash
npx @redocly/cli lint docs/openapi.yaml
```

```text
Frontend-charjino     →  :3001
Admin-Front-charjino  →  :3002
Backend-charjino      →  :3000/api
```

آداپتور تأمین‌کننده از الگوی Shop2 است. ساخت سفارش زنده تا `ALLOW_LIVE_ORDER=yes` قفل است.

## شروع

```bash
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=3000
```

`http://127.0.0.1:3000/api/health`

ورود فروشگاه در محیط توسعه: OTP برابر `123456` (متغیر `DEV_OTP_CODE`).

ورود پنل ادمین: `ADMIN_EMAIL` / `ADMIN_PASSWORD` (پیش‌فرض `admin@charjino.local` / `charjino-admin`).

دیتابیس پیش‌فرض SQLite است تا بدون Docker بالا بیاید. PostgreSQL در `docker-compose.yml` برای مرحله بعد آماده است.

## ماژول‌ها

Identity، Catalog، Pricing (اسکلت SKU)، Supplier/Shop2، Platform/Policy.

ذخیره OTP و نشست در دیتابیس است. کوین/چیپ نتیجه‌محور، VPN و رمز بازی در سیاست محصول خاموش‌اند.
