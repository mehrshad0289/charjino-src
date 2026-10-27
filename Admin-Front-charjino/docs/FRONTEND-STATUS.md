# وضعیت پنل ادمین شارژینو

> به‌روز شده: ۲۶ سپتامبر ۲۰۲۶  
> پروژه: `Admin-Front-charjino` (`:3002`) · API: `BACKEND_URL` → Laravel `/api`  
> هم‌خانواده: `Frontend-charjino/docs/FRONTEND-STATUS.md`
<!--  -->
---
## این مرحله چه شد

| صفحه | API | وضعیت |
|------|-----|--------|
| پیشخوان | `GET /admin/dashboard/summary` + groups/products/users/supplier/settings/orders | زنده — بدون `RECENT_ORDERS` |
| دسته‌ها | `GET/POST/PUT/DELETE /admin/catalog/groups` | تصویر، visible، sortOrder، ساخت دستی |
| محصولات | `GET/PUT/DELETE /admin/catalog/products` فیلتر `category/status/q/visible/page` | تصویر، visible، sortOrder از API |
| SKU | `PUT /admin/catalog/skus/{id}` | name / visible / status / قیمت فروش |
| نرخ دلار | `GET/PUT /admin/settings` ، `POST /admin/settings/fx` | `fxMode=auto` قفل نرخ دستی؛ refresh حالت را auto می‌کند |
| تأمین‌کننده | `GET /admin/supplier` ، `GET/POST /admin/catalog/sync` | کیف پول، آخرین sync، خطای IP/کلید |
| کاربران | `GET /admin/users` | فقط خواندنی |
| سفارش‌ها | `GET /admin/orders` + جزئیات + retry/refund/cancel | از ایجاد تا تحویل؛ empty/error/loading فارسی |

سایدبار نمونه TailAdmin (تقویم، تیکت، figma، جدول‌ها) از منوی اصلی مخفی است؛ فایل‌ها باقی مانده‌اند.

قوانین Cursor ادمین فقط مسیر شارژینو است (`Admin-Front-charjino/.cursor/rules/admin-frontend-red-lines.mdc`) — Boomobana نیست.

---

## لوکال

```powershell
cd C:\Users\Asus\Desktop\charjino\Admin-Front-charjino
# BACKEND_URL=http://127.0.0.1:3000/api
npm run api:types
npm run typecheck
npm run dev
```
