'use server';

import { revalidatePath } from 'next/cache';
import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import type { AdminPageMeta, AdminProductComment } from '@/types/product-comments';

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

export async function listProductComments(params: { status?: string; page?: number; perPage?: number }) {
  try {
    const data = await serverFetch<{ items: AdminProductComment[]; meta: AdminPageMeta }>(
      withQuery(apiPaths.adminCatalogComments, params)
    );
    return { ok: true as const, items: data.items, meta: data.meta };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function setProductCommentStatus(id: number, status: 'approved' | 'hidden') {
  try {
    const data = await serverFetch<AdminProductComment>(apiPaths.adminCatalogComment(id), {
      method: 'PATCH',
      body: JSON.stringify({ status })
    });
    revalidatePath('/catalog/comments');
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}
