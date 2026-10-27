'use server';

import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import type { AdminBuyGuide, BuyGuideCoverageNode } from '@/types/buy-guide';

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

export async function listBuyGuideCoverage() {
  try {
    const data = await serverFetch<{ items: BuyGuideCoverageNode[] }>(apiPaths.buyGuideCoverage);
    return { ok: true as const, items: data.items };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getBuyGuide(id: number) {
  try {
    const data = await serverFetch<AdminBuyGuide>(apiPaths.buyGuide(id));
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function createBuyGuide(payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminBuyGuide>(apiPaths.buyGuides, {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function updateBuyGuide(id: number, payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminBuyGuide>(apiPaths.buyGuide(id), {
      method: 'PUT',
      body: JSON.stringify(payload)
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function deleteBuyGuide(id: number) {
  try {
    await serverFetch(apiPaths.buyGuide(id), { method: 'DELETE' });
    return { ok: true as const };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function publishBuyGuide(id: number) {
  try {
    const data = await serverFetch<AdminBuyGuide>(apiPaths.buyGuidePublish(id), { method: 'POST' });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function unpublishBuyGuide(id: number) {
  try {
    const data = await serverFetch<AdminBuyGuide>(apiPaths.buyGuideUnpublish(id), { method: 'POST' });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function duplicateBuyGuide(id: number, payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminBuyGuide>(apiPaths.buyGuideDuplicate(id), {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function resolveBuyGuidePreview(productId: number) {
  try {
    const data = await serverFetch<Record<string, unknown>>(
      withQuery(apiPaths.buyGuidePreview, { product_id: productId })
    );
    return { ok: true as const, data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listBuyGuideRegions(category: string, subcategory?: string) {
  try {
    const data = await serverFetch<{
      items: { code: string; label: string; compat: string; product_handle?: string; product_id?: number }[];
    }>(withQuery(apiPaths.buyGuideRegions, { category, subcategory }));
    return { ok: true as const, items: data.items };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function uploadBuyGuideMedia(formData: FormData) {
  try {
    const data = await serverFetch<{
      id: number;
      src: string;
      srcset?: string;
      width?: number;
      height?: number;
      bytes?: number;
      alt: string;
      kind: 'image' | 'video';
    }>(apiPaths.buyGuideMedia, { method: 'POST', body: formData });
    return { ok: true as const, media: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listBuyGuides(filters?: { category?: string; subcategory?: string; productId?: number }) {
  try {
    const data = await serverFetch<{ items: AdminBuyGuide[] }>(
      withQuery(apiPaths.buyGuides, {
        category: filters?.category,
        subcategory: filters?.subcategory,
        product_id: filters?.productId
      })
    );
    return { ok: true as const, items: data.items };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}
