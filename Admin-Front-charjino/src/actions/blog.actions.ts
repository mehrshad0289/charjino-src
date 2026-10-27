'use server';

import { revalidatePath } from 'next/cache';
import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import type {
  AdminBlogCategory,
  AdminBlogComment,
  AdminBlogPost,
  AdminBlogTag,
  AdminPageMeta,
  BlogLinkHit
} from '@/types/blog';

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

function revalidateBlog() {
  revalidatePath('/content/blog');
  revalidatePath('/content/blog/categories');
  revalidatePath('/content/blog/tags');
  revalidatePath('/content/blog/comments');
}

export async function listBlogCategories() {
  try {
    const data = await serverFetch<{ items: AdminBlogCategory[] }>(apiPaths.adminBlogCategories);
    return { ok: true as const, items: data.items };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function createBlogCategory(payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminBlogCategory>(apiPaths.adminBlogCategories, {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    revalidateBlog();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function updateBlogCategory(id: number, payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminBlogCategory>(apiPaths.adminBlogCategory(id), {
      method: 'PUT',
      body: JSON.stringify(payload)
    });
    revalidateBlog();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function deleteBlogCategory(id: number) {
  try {
    await serverFetch(apiPaths.adminBlogCategory(id), { method: 'DELETE' });
    revalidateBlog();
    return { ok: true as const };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listBlogPosts(params: {
  q?: string;
  status?: string;
  categoryId?: string;
  page?: number;
  perPage?: number;
}) {
  try {
    const data = await serverFetch<{ items: AdminBlogPost[]; meta: AdminPageMeta }>(
      withQuery(apiPaths.adminBlogPosts, {
        q: params.q,
        status: params.status,
        categoryId: params.categoryId,
        page: params.page,
        perPage: params.perPage
      })
    );
    return { ok: true as const, items: data.items, meta: data.meta };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function getBlogPost(id: number) {
  try {
    const data = await serverFetch<AdminBlogPost>(apiPaths.adminBlogPost(id));
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function createBlogPost(payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminBlogPost>(apiPaths.adminBlogPosts, {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    revalidateBlog();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function updateBlogPost(id: number, payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminBlogPost>(apiPaths.adminBlogPost(id), {
      method: 'PUT',
      body: JSON.stringify(payload)
    });
    revalidateBlog();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function deleteBlogPost(id: number) {
  try {
    await serverFetch(apiPaths.adminBlogPost(id), { method: 'DELETE' });
    revalidateBlog();
    return { ok: true as const };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function publishBlogPost(id: number) {
  try {
    const data = await serverFetch<AdminBlogPost>(apiPaths.adminBlogPostPublish(id), { method: 'POST' });
    revalidateBlog();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function unpublishBlogPost(id: number) {
  try {
    const data = await serverFetch<AdminBlogPost>(apiPaths.adminBlogPostUnpublish(id), { method: 'POST' });
    revalidateBlog();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listBlogComments(params: { status?: string; page?: number; perPage?: number }) {
  try {
    const data = await serverFetch<{ items: AdminBlogComment[]; meta: AdminPageMeta }>(
      withQuery(apiPaths.adminBlogComments, params)
    );
    return { ok: true as const, items: data.items, meta: data.meta };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function setBlogCommentStatus(id: number, status: 'approved' | 'hidden') {
  try {
    const data = await serverFetch<AdminBlogComment>(apiPaths.adminBlogComment(id), {
      method: 'PATCH',
      body: JSON.stringify({ status })
    });
    revalidateBlog();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function listBlogTags() {
  try {
    const data = await serverFetch<{ items: AdminBlogTag[] }>(apiPaths.adminBlogTags);
    return { ok: true as const, items: data.items };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function createBlogTag(payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminBlogTag>(apiPaths.adminBlogTags, {
      method: 'POST',
      body: JSON.stringify(payload)
    });
    revalidateBlog();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function updateBlogTag(id: number, payload: Record<string, unknown>) {
  try {
    const data = await serverFetch<AdminBlogTag>(apiPaths.adminBlogTag(id), {
      method: 'PUT',
      body: JSON.stringify(payload)
    });
    revalidateBlog();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function deleteBlogTag(id: number) {
  try {
    await serverFetch(apiPaths.adminBlogTag(id), { method: 'DELETE' });
    revalidateBlog();
    return { ok: true as const };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function restoreBlogRevision(postId: number, revisionId: number) {
  try {
    const data = await serverFetch<AdminBlogPost>(apiPaths.adminBlogRestoreRevision(postId, revisionId), {
      method: 'POST'
    });
    revalidateBlog();
    return { ok: true as const, item: data };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function checkBlogSlug(slug: string, ignoreId?: number) {
  try {
    const qs = new URLSearchParams({ slug });
    if (ignoreId) qs.set('ignoreId', String(ignoreId));
    const data = await serverFetch<{ slug: string; available: boolean }>(`${apiPaths.adminBlogSlugAvailable}?${qs}`);
    return { ok: true as const, available: data.available };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}

export async function searchBlogLinks(q: string) {
  try {
    const data = await serverFetch<{ posts: BlogLinkHit[]; products: BlogLinkHit[] }>(
      `${apiPaths.adminBlogLinkSearch}?q=${encodeURIComponent(q)}`
    );
    return { ok: true as const, posts: data.posts, products: data.products };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error), posts: [] as BlogLinkHit[], products: [] as BlogLinkHit[] };
  }
}

export async function uploadBlogImage(formData: FormData) {
  try {
    const data = await serverFetch<{
      url: string;
      path: string;
      width?: number;
      height?: number;
      alt?: string;
      variants?: Record<number, string>;
      mediaType?: string;
    }>(apiPaths.adminBlogUploads, {
      method: 'POST',
      body: formData
    });
    return {
      ok: true as const,
      url: data.url,
      width: data.width,
      height: data.height,
      alt: data.alt,
      mediaType: data.mediaType
    };
  } catch (error) {
    return { ok: false as const, message: toActionMessage(error) };
  }
}
