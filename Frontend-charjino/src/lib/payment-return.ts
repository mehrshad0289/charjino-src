import { completeMockPayment } from '@/actions/checkout.actions';
import { ROUTES } from '@/lib/routes';
import type { PayOrderResult } from '@/types/catalog';

export function withShopFront(redirectUrl: string, origin = window.location.origin): string {
  try {
    const url = new URL(redirectUrl, origin);
    url.searchParams.set('front', origin);
    return url.toString();
  } catch {
    return redirectUrl;
  }
}

export async function continueShopPayment(
  pay: PayOrderResult,
): Promise<{ ok: true; href: string } | { ok: false; message: string }> {
  if (pay.provider === 'wallet') {
    const number = pay.order?.number;
    if (!number) {
      return { ok: false, message: 'پرداخت با کیف پول ثبت نشد.' };
    }
    return { ok: true, href: ROUTES.profileOrder(number) };
  }

  if (pay.provider === 'mock') {
    const authority = pay.authority?.trim() ?? '';
    if (!authority) {
      return { ok: false, message: 'پرداخت آزمایشی شروع نشد.' };
    }

    const done = await completeMockPayment(authority);
    if (!done.ok) {
      const number = pay.order?.number;
      return {
        ok: false,
        message: number
          ? `${done.message} شماره سفارش: ${number}. از حساب من، بخش سفارش‌ها ادامه بده.`
          : done.message,
      };
    }

    return { ok: true, href: ROUTES.profileOrder(done.data.number) };
  }

  return { ok: true, href: pay.redirectUrl };
}
