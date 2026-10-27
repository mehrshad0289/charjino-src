import type { BlogPosting, BreadcrumbList, CollectionPage, FAQPage, ItemList, WithContext } from 'schema-dts';
import { ROUTES } from '@/lib/routes';
import { BRAND_FA, absoluteUrl } from '@/lib/site';
import type { BlogFaqItem, BlogPost, BlogPostCard } from '@/types/blog';

function asRecord<T>(value: T): Record<string, unknown> {
  return value as Record<string, unknown>;
}

function coverUrl(src?: string | null) {
  if (!src?.trim()) return undefined;
  if (src.startsWith('/')) return absoluteUrl(src);
  return src;
}

export function blogPostingJsonLd(post: BlogPost): Record<string, unknown> {
  const url = absoluteUrl(ROUTES.blogPost(post.slug));
  const data = {
    '@context': 'https://schema.org',
    '@type': ['Article', 'BlogPosting'],
    headline: post.title,
    description: post.metaDescription || post.excerpt || undefined,
    image: coverUrl(post.cover),
    datePublished: post.publishedAt || undefined,
    dateModified: post.updatedAt || post.publishedAt || undefined,
    inLanguage: 'fa-IR',
    mainEntityOfPage: url,
    author: {
      '@type': 'Person',
      name: post.author?.name || BRAND_FA,
      url: absoluteUrl('/about'),
    },
    publisher: {
      '@type': 'Organization',
      name: BRAND_FA,
      logo: {
        '@type': 'ImageObject',
        url: absoluteUrl('/icon-512.png'),
      },
    },
  };
  return asRecord(data);
}

export function faqPageJsonLd(faq: BlogFaqItem[]): Record<string, unknown> | null {
  if (faq.length === 0) return null;
  const data: WithContext<FAQPage> = {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: faq.map((item) => ({
      '@type': 'Question',
      name: item.question,
      acceptedAnswer: {
        '@type': 'Answer',
        text: item.answer,
      },
    })),
  };
  return asRecord(data);
}

export function collectionPageJsonLd(input: {
  name: string;
  description: string;
  path: string;
  items: BlogPostCard[];
}): Record<string, unknown>[] {
  const url = absoluteUrl(input.path);
  const collection: WithContext<CollectionPage> = {
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    name: input.name,
    description: input.description,
    url,
    inLanguage: 'fa-IR',
  };
  const list: WithContext<ItemList> = {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    itemListElement: input.items.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      url: absoluteUrl(ROUTES.blogPost(item.slug)),
      name: item.title,
    })),
  };
  return [asRecord(collection), asRecord(list)];
}

export function breadcrumbRecords(items: { name: string; path?: string | null }[]): Record<string, unknown> {
  const data: WithContext<BreadcrumbList> = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: item.name,
      item: item.path ? absoluteUrl(item.path) : undefined,
    })),
  };
  return asRecord(data);
}
