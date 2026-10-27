'use server';

import { revalidatePath } from 'next/cache';
import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import type { AdminCoupon, AdminPageMeta } from '@/types/coupons';

function toActionMessage(error: unknown) {
  if (error instanceof ApiError && error.message) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return 'درخواست انجام نشد';
}

function withQuery(path: string, params: Record<string, string | number | undefined | null>) {
  const qs = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null || value === '' || value === 'all') continue;
    qs.set(key, String(value));
  }
  const suffix = qs.toString();
  return suffix ? `${path}?${suffix}` : path;
}

function revalidateCoupons() {
  revalidatePath('/coupons');
}

export async function listCoupons(params: { q?: string; status?: string; page?: number; perPage?: number }) {
  try {
    const data = await serverFetch<{ items: AdminCoupon[]; meta: AdminPageMeta }>(
      withQuery(apiPaths.adminCoupons, params)
    );
    return { ok: true as const, items: data.items, meta: data.meta };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getCoupon(id: number) {
  try {
    const data = await serverFetch<AdminCoupon>(apiPaths.adminCoupon(id));
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function createCoupon(payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminCoupon>(apiPaths.adminCoupons, {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    revalidateCoupons();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function updateCoupon(id: number, payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminCoupon>(apiPaths.adminCoupon(id), {
      method: 'PUT',
      body: JSON.stringify(payload)
    });
    revalidateCoupons();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function deleteCoupon(id: number) {
  try {
    await serverFetch(apiPaths.adminCoupon(id), { method: 'DELETE' });
    revalidateCoupons();
    return { ok: true as const };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}
