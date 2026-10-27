'use server';

import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import type { BlogComment, BlogPageMeta } from '@/types/blog';

function toMessage(error: unknown) {
  if (error instanceof ApiError && error.message) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return 'درخواست انجام نشد';
}

export async function listProductComments(handle: string, page = 1) {
  try {
    const data = await serverFetch<{ items: BlogComment[]; meta: BlogPageMeta }>(
      apiPaths.catalogProductComments(handle, page),
      { auth: true },
    );
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      try {
        const data = await serverFetch<{ items: BlogComment[]; meta: BlogPageMeta }>(
          apiPaths.catalogProductComments(handle, page),
          { auth: false },
        );
        return { ok: true as const, data };
      } catch (inner) {
        return { ok: false as const, message: toMessage(inner) };
      }
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function addProductComment(handle: string, body: string, parentId?: number) {
  try {
    const data = await serverFetch<BlogComment>(apiPaths.catalogProductComments(handle), {
      method: 'POST',
      body: JSON.stringify({ body, parentId: parentId || undefined }),
    });
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function likeProductComment(id: number) {
  try {
    const data = await serverFetch<{ liked: boolean; likesCount: number }>(apiPaths.catalogCommentLike(id), {
      method: 'POST',
    });
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}
