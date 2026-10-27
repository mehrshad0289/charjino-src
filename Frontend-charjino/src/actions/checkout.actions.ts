'use server';

import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import { customerErrorMessage } from '@/lib/customer-errors';
import type {
  CustomerOrder,
  CustomerOrderPage,
  Health,
  PayOrderResult,
  ValidatePlayerResult,
} from '@/types/catalog';

function toActionError(error: unknown) {
  if (error instanceof ApiError) {
    return {
      message: customerErrorMessage(error.errorCode, error.message),
      errorCode: error.errorCode,
      statusCode: error.statusCode,
      data: error.data,
    };
  }
  if (error instanceof Error && error.message) {
    return { message: error.message };
  }
  return { message: 'درخواست انجام نشد' };
}

export async function getShopHealth() {
  try {
    const data = await serverFetch<Health>(apiPaths.health, { auth: false });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionError(error).message };
  }
}

export async function validateCheckoutPlayer(
  skuId: number,
  requirements: Record<string, string>,
) {
  if (!Number.isInteger(skuId) || skuId < 1) {
    return { ok: false as const, message: 'بسته انتخاب نشده است' };
  }

  try {
    const data = await serverFetch<ValidatePlayerResult>(apiPaths.checkoutValidatePlayer, {
      method: 'POST',
      body: JSON.stringify({ skuId, requirements }),
    });
    return { ok: true as const, data };
  } catch (error) {
    const mapped = toActionError(error);
    if (mapped.statusCode === 401) {
      return {
        ok: false as const,
        needAuth: true as const,
        message: 'برای بررسی شناسه وارد شوید',
        errorCode: mapped.errorCode,
      };
    }
    return { ok: false as const, message: mapped.message, errorCode: mapped.errorCode, data: mapped.data };
  }
}

export async function previewCheckoutCoupon(
  code: string,
  skuId: number,
  quantity = 1,
  extra?: { amount?: string; currency?: string },
) {
  const trimmed = code.trim();
  if (!trimmed) {
    return { ok: false as const, message: 'کد تخفیف را بنویس' };
  }
  if (!Number.isInteger(skuId) || skuId < 1) {
    return { ok: false as const, message: 'بسته انتخاب نشده است' };
  }
  try {
    const data = await serverFetch<{
      code: string;
      type: string;
      subtotalToman: number;
      discountToman: number;
      payableToman: number;
    }>(apiPaths.checkoutCoupon, {
      method: 'POST',
      body: JSON.stringify({
        code: trimmed,
        skuId,
        quantity,
        ...(extra?.amount ? { amount: extra.amount } : {}),
        ...(extra?.currency ? { currency: extra.currency } : {}),
      }),
    });
    return { ok: true as const, data };
  } catch (error) {
    const mapped = toActionError(error);
    if (mapped.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: 'برای کد تخفیف وارد حساب شو' };
    }
    return { ok: false as const, message: mapped.message, errorCode: mapped.errorCode };
  }
}

export async function createCheckoutOrder(
  skuId: number,
  requirements: Record<string, string>,
  idempotencyKey: string,
  quantity = 1,
  extra?: { amount?: string; currency?: string; couponCode?: string },
) {
  if (!Number.isInteger(skuId) || skuId < 1) {
    return { ok: false as const, message: 'بسته انتخاب نشده است' };
  }
  const key = idempotencyKey.trim().slice(0, 80);
  if (!key) {
    return { ok: false as const, message: 'کلید سفارش نامعتبر است' };
  }
  const qty = Math.max(1, Math.min(100, Math.trunc(quantity) || 1));

  try {
    const data = await serverFetch<CustomerOrder>(apiPaths.orders, {
      method: 'POST',
      headers: { 'Idempotency-Key': key },
      body: JSON.stringify({
        skuId,
        quantity: qty,
        requirements,
        ...(extra?.amount ? { amount: extra.amount } : {}),
        ...(extra?.currency ? { currency: extra.currency } : {}),
        ...(extra?.couponCode ? { couponCode: extra.couponCode } : {}),
      }),
    });
    return { ok: true as const, data };
  } catch (error) {
    const mapped = toActionError(error);
    if (mapped.statusCode === 401) {
      return {
        ok: false as const,
        needAuth: true as const,
        message: 'برای ثبت سفارش وارد شوید',
        errorCode: mapped.errorCode,
      };
    }
    return { ok: false as const, message: mapped.message, errorCode: mapped.errorCode };
  }
}

export async function startOrderPayment(number: string, returnOrigin?: string, method?: 'wallet' | 'zibal' | 'zarinpal' | 'mock') {
  const normalized = number.trim();
  if (!normalized) {
    return { ok: false as const, message: 'شماره سفارش نامعتبر است' };
  }

  try {
    const data = await serverFetch<PayOrderResult>(apiPaths.payOrder(normalized), {
      method: 'POST',
      body: JSON.stringify({
        ...(returnOrigin ? { returnOrigin } : {}),
        ...(method ? { method } : {}),
      }),
    });
    return { ok: true as const, data };
  } catch (error) {
    const mapped = toActionError(error);
    if (mapped.statusCode === 401) {
      return {
        ok: false as const,
        needAuth: true as const,
        message: 'برای پرداخت وارد شوید',
        errorCode: mapped.errorCode,
      };
    }
    return { ok: false as const, message: mapped.message, errorCode: mapped.errorCode };
  }
}

export async function completeMockPayment(authority: string) {
  const token = authority.trim();
  if (!token) {
    return { ok: false as const, message: 'پرداخت پیدا نشد.' };
  }

  try {
    const data = await serverFetch<CustomerOrder>(apiPaths.paymentCallbackMock, {
      method: 'POST',
      auth: false,
      body: JSON.stringify({ authority: token, status: 'OK' }),
    });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionError(error).message };
  }
}

export async function verifyZarinpalPayment(input: {
  authority: string;
  status?: string;
}) {
  const authority = input.authority.trim();
  if (!authority) {
    return { ok: false as const, message: 'شناسه تراکنش نامعتبر است.' };
  }

  try {
    const data = await serverFetch<CustomerOrder>(apiPaths.paymentVerifyZarinpal, {
      method: 'POST',
      auth: false,
      body: JSON.stringify({
        authority,
        status: input.status ?? '',
      }),
    });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionError(error).message };
  }
}

export async function verifyZibalPayment(input: {
  trackId: string;
  success?: string;
  status?: string;
  orderId?: string;
}) {
  const trackId = input.trackId.trim();
  if (!trackId) {
    return { ok: false as const, message: 'شناسه تراکنش نامعتبر است.' };
  }

  try {
    const data = await serverFetch<CustomerOrder>(apiPaths.paymentVerifyZibal, {
      method: 'POST',
      auth: false,
      body: JSON.stringify({
        trackId,
        success: input.success ?? '',
        status: input.status ?? '',
        orderId: input.orderId ?? '',
      }),
    });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionError(error).message };
  }
}

export async function listMyOrders(page = 1) {
  try {
    const data = await serverFetch<CustomerOrderPage>(apiPaths.ordersQuery(page));
    return { ok: true as const, data };
  } catch (error) {
    const mapped = toActionError(error);
    if (mapped.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: 'برای دیدن سفارش‌ها وارد شوید' };
    }
    return { ok: false as const, message: mapped.message };
  }
}

export async function getMyOrder(number: string) {
  const normalized = number.trim();
  if (!normalized) {
    return { ok: false as const, message: 'شماره سفارش نامعتبر است', notFound: true as const };
  }

  try {
    const data = await serverFetch<CustomerOrder>(apiPaths.order(normalized));
    return { ok: true as const, data };
  } catch (error) {
    const mapped = toActionError(error);
    if (mapped.statusCode === 401) {
      return {
        ok: false as const,
        needAuth: true as const,
        message: 'برای دیدن این سفارش وارد شوید',
      };
    }
    return {
      ok: false as const,
      message: mapped.message,
      notFound: mapped.statusCode === 404,
    };
  }
}

export async function quoteSteamTopup(currency: string, amount: string) {
  try {
    const data = await serverFetch<{ currency: string; amount: string; usd: string; toman: number }>(
      apiPaths.steamTopupQuote(currency, amount),
      { auth: false },
    );
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionError(error).message, errorCode: toActionError(error).errorCode };
  }
}

export async function revealOrderCodes(number: string) {
  try {
    const data = await serverFetch<{ kind: string; items: string[] }>(apiPaths.revealOrder(number.trim()), {
      method: 'POST',
    });
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionError(error).message };
  }
}

export async function refreshOrderStatus(number: string) {
  try {
    await serverFetch<{ queued: boolean }>(apiPaths.refreshOrder(number.trim()), { method: 'POST' });
    return { ok: true as const };
  } catch (error) {
    return { ok: false as const, message: toActionError(error).message };
  }
}

export async function getMyWallet() {
  try {
    const data = await serverFetch<{
      balanceToman: number;
      transactions: Array<{
        type: string;
        amountToman: number;
        balanceAfter: number;
        reason: string;
        orderId?: string | null;
        createdAt?: string | null;
      }>;
      note: string;
    }>(apiPaths.meWallet);
    return { ok: true as const, data };
  } catch (error) {
    const mapped = toActionError(error);
    if (mapped.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: 'برای دیدن کیف پول وارد شوید' };
    }
    return { ok: false as const, message: mapped.message };
  }
}
