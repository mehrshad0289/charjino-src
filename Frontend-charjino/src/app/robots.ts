import type { MetadataRoute } from 'next';
import { headers } from 'next/headers';
import { isIndexableSeoHost, seoHostFromRequest } from '@/lib/seo-host';
import { SITE_URL } from '@/lib/site';

export default async function robots(): Promise<MetadataRoute.Robots> {
  const headerStore = await headers();
  const host = seoHostFromRequest({ headers: headerStore });
  if (!isIndexableSeoHost(host)) {
    return {
      rules: {
        userAgent: '*',
        disallow: '/',
      },
    };
  }

  return {
    rules: {
      userAgent: '*',
      allow: ['/_next/image', '/_next/static', '/'],
      disallow: [
        '/auth',
        '/profile',
        '/invoices',
        '/payments',
        '/figma',
        '/sitemaps',
        '/api/',
        '/*?*q=',
        '/*?*min=',
        '/*?*max=',
        '/*?*region=',
        '/*?*edition=',
        '/*?*pack=',
        '/*?*duration=',
      ],
    },
    sitemap: [`${SITE_URL}/sitemap.xml`, `${SITE_URL}/blog/sitemap.xml`],
  };
}
