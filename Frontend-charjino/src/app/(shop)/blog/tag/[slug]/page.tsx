import type { Metadata } from 'next';
import { notFound, permanentRedirect } from 'next/navigation';
import { getBlogTag } from '@/actions/blog.actions';
import { BlogCard } from '@/components/blog/blog-card';
import { BlogHtml } from '@/components/blog/blog-html';
import { BlogPager } from '@/components/blog/blog-pager';
import { JsonLd } from '@/components/seo/json-ld';
import { Container } from '@/components/ui';
import { breadcrumbRecords, collectionPageJsonLd } from '@/lib/blog-schema';
import { prepareBlogHtml } from '@/lib/blog-toc';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const revalidate = 120;

type PageProps = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ page?: string }>;
};

export async function generateMetadata({ params, searchParams }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const result = await getBlogTag(slug, page);
  if (!result.ok) {
    return buildMetadata({
      title: 'تگ وبلاگ',
      description: PAGE_DESCRIPTIONS.blog,
      path: ROUTES.blogTag(slug),
      noindex: true,
      follow: true,
    });
  }
  const titleBase = result.data.seo?.title || `${result.data.tag.title} | وبلاگ شارژینو`;
  const title = page > 1 ? `${titleBase} | صفحه ${page}` : titleBase;
  const path = page > 1 ? `${ROUTES.blogTag(result.data.tag.slug)}?page=${page}` : ROUTES.blogTag(result.data.tag.slug);
  return buildMetadata({
    title,
    description: result.data.seo?.description || result.data.tag.description || PAGE_DESCRIPTIONS.blog,
    path,
    noindex: Boolean(result.data.seo?.noindex || result.data.tag.noindex),
    follow: true,
  });
}

export default async function BlogTagPage({ params, searchParams }: PageProps) {
  const { slug } = await params;
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const result = await getBlogTag(slug, page);
  if (!result.ok) {
    if ('redirectTo' in result && result.redirectTo) permanentRedirect(result.redirectTo);
    if ('notFound' in result && result.notFound) notFound();
    return (
      <div className="bg-surface">
        <Container className="py-12 text-sm text-text/70">{result.message}</Container>
      </div>
    );
  }
  const { tag, items, meta } = result.data;
  const body = tag.bodyHtml ? prepareBlogHtml(tag.bodyHtml).html : '';

  return (
    <div className="bg-surface">
      <JsonLd
        data={[
          breadcrumbRecords([
            { name: 'خانه', path: ROUTES.home },
            { name: 'وبلاگ', path: ROUTES.blog },
            { name: tag.title, path: ROUTES.blogTag(tag.slug) },
          ]),
          ...collectionPageJsonLd({
            name: tag.title,
            description: tag.description || PAGE_DESCRIPTIONS.blog,
            path: ROUTES.blogTag(tag.slug),
            items,
          }),
        ]}
      />
      <Container className="flex flex-col gap-6 py-8 sm:py-12">
        <header className="flex flex-col gap-4">
          <h1 className="text-2xl font-bold text-text">{tag.title}</h1>
          {tag.description ? <p className="text-sm text-text/70">{tag.description}</p> : null}
          {body ? <BlogHtml html={body} /> : null}
        </header>
        {items.length === 0 ? (
          <p className="text-sm text-text/70">برای این تگ مطلبی نیست.</p>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((post) => (
              <BlogCard key={post.id} post={post} />
            ))}
          </div>
        )}
        <BlogPager
          page={meta.page}
          totalPages={meta.totalPages}
          hrefFor={(next) => (next > 1 ? `${ROUTES.blogTag(tag.slug)}?page=${next}` : ROUTES.blogTag(tag.slug))}
        />
      </Container>
    </div>
  );
}
