# Frontend Charjino

فرانت شارژینو — Next.js 16، App Router، TypeScript، Tailwind v4، RTL فارسی.

دو خط قرمز:

1. ظاهر باید ۱۰۰٪ از طرح `ui ux مورد نظر` پیروی کند.
2. معماری کد باید ۱۰۰٪ از الگوی `از این استفاده کن معماری charjino کد` پیروی کند.

## پورت‌ها

| اپ | پوشه | پورت |
|---|---|---|
| بک‌اند لاراول | `Backend-charjino` | `3000` |
| فروشگاه | `Frontend-charjino` | `3001` |
| ادمین | `Admin-Front-charjino` | `3002` |

## شروع

```bash
npm install
npm run dev
```

`http://localhost:3001`

سایت `lang="fa"` و `dir="rtl"` است. بک‌اند لاراول از `BACKEND_URL` خوانده می‌شود (`http://127.0.0.1:3000/api`).

وضعیت اتصال API، لوگو و کارهای مانده: [`docs/FRONTEND-STATUS.md`](./docs/FRONTEND-STATUS.md).
