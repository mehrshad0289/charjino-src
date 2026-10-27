import type { MetadataRoute } from 'next';
import { getBlogSitemap } from '@/actions/blog.actions';
import { ROUTES } from '@/lib/routes';
import { absoluteUrl } from '@/lib/site';

export const revalidate = 3600;

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const result = await getBlogSitemap();
  const data = result.data;
  const entries: MetadataRoute.Sitemap = [
    { url: absoluteUrl(ROUTES.blog), changeFrequency: 'daily', priority: 0.7 },
  ];
  for (const post of data.posts) {
    entries.push({
      url: absoluteUrl(ROUTES.blogPost(post.slug)),
      lastModified: post.lastmod ? new Date(post.lastmod) : undefined,
      changeFrequency: 'weekly',
      priority: 0.6,
    });
  }
  for (const category of data.categories) {
    entries.push({
      url: absoluteUrl(ROUTES.blogCategory(category.slug)),
      lastModified: category.lastmod ? new Date(category.lastmod) : undefined,
      changeFrequency: 'weekly',
      priority: 0.5,
    });
  }
  for (const tag of data.tags) {
    entries.push({
      url: absoluteUrl(ROUTES.blogTag(tag.slug)),
      lastModified: tag.lastmod ? new Date(tag.lastmod) : undefined,
      changeFrequency: 'weekly',
      priority: 0.4,
    });
  }
  return entries;
}
