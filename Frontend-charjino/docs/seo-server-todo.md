# کارهای سرور و بک‌اند برای سئوی شارژینو

این فایل فقط مستند است؛ روی nginx یا Laravel اعمال نشده.

## سایت‌مپ (مرحله ۲)

فرانت سایت‌مپ را از فهرست سایت‌مپ `/sitemap.xml` می‌سازد:

- `/sitemap/categories.xml` — صفحه‌های ثابت و دسته‌ها
- `/sitemap/products.xml` — محصول‌های اصلی (`excludeCategory=steam-gifts`)
- `/sitemap/steam-gifts.xml` — گیفت استیم (`category=steam-gifts`)؛ اگر بیش از ۵۰۰۰ URL باشد `steam-gifts-2` و بعد

وضعیت API برای ساخت این فایل‌ها:
- صفحه‌بندی با `page` و `perPage` (حداکثر ۱۰۰) و `meta.total`
- `sort=id` برای ترتیب پایدار تکه‌ها
- `excludeCategory` برای جدا کردن گیفت استیم از محصول‌های اصلی
- محصول و دستهٔ عمومی `updatedAt` دارند؛ سایت‌مپ `lastmod` می‌گذارد
- محصول‌های `isStorefrontExcluded()` (از جمله `manual-call-of-duty-mobile`) در فهرست و جزئیات نیستند

به‌روزرسانی فوری: بعد از ایجاد، ویرایش یا حذف محصول، بک‌اند این را صدا بزند (secret از env سرور فرانت، نه در گیت):

```
POST https://charjino.ir/api/revalidate
Header: x-revalidate-secret: $REVALIDATE_SECRET
Body: {"handle":"product-handle","action":"create|update|delete"}
```

این endpoint تگ‌های `catalog-home`، `catalog-list`، `sitemap` و در صورت وجود handle تگ `product:{handle}` را فوری باطل می‌کند (`revalidateTag(..., { expire: 0 })`) و سایت‌مپ فایل‌کش را هم صف می‌کند.

مسیر قدیمی فقط سایت‌مپ (هنوز کار می‌کند):

```
POST https://charjino.ir/api/revalidate-sitemap
Header: x-revalidate-secret: $REVALIDATE_SECRET
Body: {"handle":"product-handle","action":"create|update|delete"}
```

اگر webhook اجرا نشود، کش فرانت حداکثر تا یک ساعت بعد خودش تازه می‌شود.

پشتیبان ساعتی روی سرور:

```
curl -fsS -X POST -H "x-revalidate-secret: $REVALIDATE_SECRET" https://charjino.ir/api/revalidate-sitemap
```

پوشهٔ کش سایت‌مپ باید قابل‌نوشتن باشد (`SITEMAP_CACHE_DIR` یا `.sitemap-cache` در cwd فرایند PM2).

نکتهٔ Next: `src/app/sitemap.ts` با `src/app/sitemap.xml/route.ts` روی `/sitemap.xml` تداخل دارد؛ فهرست سایت‌مپ از route سفارشی می‌آید و تکه‌ها از `/sitemap/categories.xml`، `/sitemap/products.xml` و `/sitemap/steam-gifts.xml`.

## nginx (www و HTTPS)

قالب فروشگاه: `docs-charjino/deploy/charjino/nginx/shop.conf`.
با `install-nginx.sh` روی VPS نصب می‌شود (اگر گواهی Let's Encrypt باشد).

- `http://charjino.ir` → `https://charjino.ir` (301)
- `http://www.charjino.ir` → `https://charjino.ir` (301)
- `https://www.charjino.ir` → `https://charjino.ir` (301)

canonical فقط `https://charjino.ir` است. `api.charjino.ir` را به این سرور بلاک قاطی نکن.

اگر ALPN هنوز `http/1.1` است، `http2` روی `listen 443 ssl` فعال نیست یا گواهی/listener جدا برای www مانده.

## ISR کاتالوگ (مرحله ۶ — اعمال نشده در بک‌اند)

فرانت خانه را ۳۰۰ ثانیه و صفحهٔ محصول را ۶۰۰ ثانیه با تگ `catalog-home` / `catalog-list` / `product:{handle}` کش می‌کند. قیمت و وضعیت سفارش در مسیر checkout همچنان `cache: 'no-store'` است.

بعد از هر تغییر محصول/دسته/خانه در Laravel، `POST /api/revalidate` بالا را با `REVALIDATE_SECRET` صدا بزن تا HTML و سایت‌مپ کهنه نمانند. بدون این webhook، حداکثر تأخیر خانه ۵ دقیقه و محصول ۱۰ دقیقه است.

## املای برند و API عمومی (مرحله ۷ — اعمال نشده در بک‌اند)

- پاسخ ریشهٔ `GET https://api.charjino.ir/` الان «چارژینو API» است؛ باید «شارژینو API» شود (با ش).
- روی `api.charjino.ir` هدر `X-Robots-Tag: noindex, nofollow` بگذار و `robots.txt` را `Disallow: /` کن تا ایندکس نشود.
- `GET /api/health` نباید عمومی باشد (احراز هویت، allow-list IP، یا پاسخ حداقلی بدون افشای کانفیگ).
