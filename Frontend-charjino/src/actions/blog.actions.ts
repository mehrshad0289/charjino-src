'use server';

import { revalidatePath } from 'next/cache';
import { ApiError, serverFetch } from '@/lib/server-fetcher';
import { apiPaths } from '@/lib/api-paths';
import { ROUTES } from '@/lib/routes';
import type {
  BlogAuthor,
  BlogCategory,
  BlogComment,
  BlogPageMeta,
  BlogPost,
  BlogPostCard,
  BlogRelatedProduct,
  BlogSeo,
  BlogTag,
} from '@/types/blog';

function toMessage(error: unknown) {
  if (error instanceof ApiError && error.message) return error.message;
  if (error instanceof Error && error.message) return error.message;
  return 'درخواست انجام نشد';
}

function redirectTo(error: unknown): string | null {
  if (!(error instanceof ApiError) || (error.statusCode !== 301 && error.statusCode !== 308)) {
    return null;
  }
  const data = error.data;
  if (data && typeof data === 'object' && 'to' in data && typeof data.to === 'string' && data.to.startsWith('/')) {
    return data.to;
  }
  return null;
}

export async function getBlogHub(page = 1) {
  try {
    const data = await serverFetch<{
      categories: BlogCategory[];
      latest: BlogPostCard[];
      popular: BlogPostCard[];
      items: BlogPostCard[];
      meta: BlogPageMeta;
      seo: BlogSeo;
    }>(apiPaths.blogHub(page), { auth: false, next: { revalidate: 120, tags: ['blog'] } });
    return {
      ok: true as const,
      data: {
        ...data,
        latest: data.items ?? data.latest,
      },
    };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 404) {
      return { ok: false as const, notFound: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function listBlogPosts(params: { q?: string; category?: string; page?: number } = {}) {
  try {
    const data = await serverFetch<{ items: BlogPostCard[]; meta: BlogPageMeta; q?: string }>(
      apiPaths.blogPosts(params),
      { auth: false, next: { revalidate: 60, tags: ['blog'] } },
    );
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 404) {
      return { ok: false as const, notFound: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function getBlogCategory(slug: string, page = 1) {
  try {
    const data = await serverFetch<{
      category: BlogCategory;
      items: BlogPostCard[];
      meta: BlogPageMeta;
      seo: BlogSeo;
    }>(apiPaths.blogCategory(slug, page), { auth: false, next: { revalidate: 60, tags: ['blog'] } });
    return { ok: true as const, data };
  } catch (error) {
    const to = redirectTo(error);
    if (to) return { ok: false as const, redirectTo: to, message: toMessage(error) };
    if (error instanceof ApiError && error.statusCode === 404) {
      return { ok: false as const, notFound: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function getBlogTag(slug: string, page = 1) {
  try {
    const data = await serverFetch<{
      tag: BlogTag;
      items: BlogPostCard[];
      meta: BlogPageMeta;
      seo: BlogSeo;
    }>(apiPaths.blogTag(slug, page), { auth: false, next: { revalidate: 60, tags: ['blog'] } });
    return { ok: true as const, data };
  } catch (error) {
    const to = redirectTo(error);
    if (to) return { ok: false as const, redirectTo: to, message: toMessage(error) };
    if (error instanceof ApiError && error.statusCode === 404) {
      return { ok: false as const, notFound: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function getBlogAuthor(slug: string, page = 1) {
  try {
    const data = await serverFetch<{
      author: BlogAuthor;
      items: BlogPostCard[];
      meta: BlogPageMeta;
      seo: BlogSeo;
    }>(apiPaths.blogAuthor(slug, page), { auth: false, next: { revalidate: 60, tags: ['blog'] } });
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 404) {
      return { ok: false as const, notFound: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function getBlogPreview(token: string) {
  try {
    const data = await serverFetch<{
      post: BlogPost;
      related: BlogPostCard[];
      products?: BlogRelatedProduct[];
      preview?: boolean;
    }>(apiPaths.blogPreview(token), { auth: false });
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 404) {
      return { ok: false as const, notFound: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function getBlogPost(slug: string) {
  try {
    const data = await serverFetch<{ post: BlogPost; related: BlogPostCard[]; products?: BlogRelatedProduct[] }>(
      apiPaths.blogPost(slug),
      { auth: true },
    );
    return { ok: true as const, data };
  } catch (error) {
    const to = redirectTo(error);
    if (to) return { ok: false as const, redirectTo: to, message: toMessage(error) };
    if (error instanceof ApiError && error.statusCode === 401) {
      try {
        const data = await serverFetch<{ post: BlogPost; related: BlogPostCard[]; products?: BlogRelatedProduct[] }>(
          apiPaths.blogPost(slug),
          { auth: false, next: { revalidate: 30, tags: ['blog'] } },
        );
        return { ok: true as const, data };
      } catch (inner) {
        const innerTo = redirectTo(inner);
        if (innerTo) return { ok: false as const, redirectTo: innerTo, message: toMessage(inner) };
        if (inner instanceof ApiError && inner.statusCode === 404) {
          return { ok: false as const, notFound: true as const, message: toMessage(inner) };
        }
        return { ok: false as const, message: toMessage(inner) };
      }
    }
    if (error instanceof ApiError && error.statusCode === 404) {
      return { ok: false as const, notFound: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function getBlogSitemap() {
  try {
    const data = await serverFetch<{
      posts: { slug: string; lastmod?: string | null }[];
      categories: { slug: string; lastmod?: string | null }[];
      tags: { slug: string; lastmod?: string | null }[];
    }>(apiPaths.blogSitemap, { auth: false, next: { revalidate: 3600, tags: ['blog', 'sitemap'] } });
    return { ok: true as const, data };
  } catch {
    return { ok: false as const, data: { posts: [], categories: [], tags: [] } };
  }
}

export async function listBlogComments(slug: string, page = 1) {
  try {
    const data = await serverFetch<{ items: BlogComment[]; meta: BlogPageMeta }>(apiPaths.blogPostComments(slug, page), {
      auth: true,
    });
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      try {
        const data = await serverFetch<{ items: BlogComment[]; meta: BlogPageMeta }>(
          apiPaths.blogPostComments(slug, page),
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

export async function addBlogComment(slug: string, body: string, parentId?: number) {
  try {
    const data = await serverFetch<BlogComment>(apiPaths.blogPostComments(slug), {
      method: 'POST',
      body: JSON.stringify({ body, parentId: parentId || undefined }),
    });
    revalidatePath(ROUTES.blogPost(slug));
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function likeBlogPost(slug: string) {
  try {
    const data = await serverFetch<{ liked: boolean; likesCount: number }>(apiPaths.blogPostLike(slug), {
      method: 'POST',
    });
    revalidatePath(ROUTES.blogPost(slug));
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}

export async function likeBlogComment(slug: string, id: number) {
  try {
    const data = await serverFetch<{ liked: boolean; likesCount: number }>(apiPaths.blogCommentLike(id), {
      method: 'POST',
    });
    revalidatePath(ROUTES.blogPost(slug));
    return { ok: true as const, data };
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 401) {
      return { ok: false as const, needAuth: true as const, message: toMessage(error) };
    }
    return { ok: false as const, message: toMessage(error) };
  }
}
