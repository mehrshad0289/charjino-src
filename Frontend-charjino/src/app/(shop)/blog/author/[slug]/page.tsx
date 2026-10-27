import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getBlogAuthor } from '@/actions/blog.actions';
import { BlogCard } from '@/components/blog/blog-card';
import { BlogPager } from '@/components/blog/blog-pager';
import { JsonLd } from '@/components/seo/json-ld';
import { Container } from '@/components/ui';
import { breadcrumbRecords, collectionPageJsonLd } from '@/lib/blog-schema';
import { ROUTES } from '@/lib/routes';
import { AUTHOR_DOCUMENT_TITLE, PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const revalidate = 120;

type PageProps = {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ page?: string }>;
};

export async function generateMetadata({ params, searchParams }: PageProps): Promise<Metadata> {
  const { slug } = await params;
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const result = await getBlogAuthor(slug, page);
  if (!result.ok) {
    return buildMetadata({
      title: AUTHOR_DOCUMENT_TITLE,
      description: PAGE_DESCRIPTIONS.blog,
      path: ROUTES.blogAuthor(slug),
      noindex: true,
      follow: true,
    });
  }
  const titleBase = AUTHOR_DOCUMENT_TITLE;
  const title = page > 1 ? `${titleBase} | صفحه ${page}` : titleBase;
  const path = page > 1 ? `${ROUTES.blogAuthor(slug)}?page=${page}` : ROUTES.blogAuthor(slug);
  return buildMetadata({
    title,
    description: result.data.seo?.description || PAGE_DESCRIPTIONS.blog,
    path,
    noindex: page > 1 || Boolean(result.data.seo?.noindex),
    follow: true,
  });
}

export default async function BlogAuthorPage({ params, searchParams }: PageProps) {
  const { slug } = await params;
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const result = await getBlogAuthor(slug, page);
  if (!result.ok) {
    if ('notFound' in result && result.notFound) notFound();
    return (
      <div className="bg-surface">
        <Container className="py-12 text-sm text-text/70">{result.message}</Container>
      </div>
    );
  }
  const { author, items, meta } = result.data;

  return (
    <div className="bg-surface">
      <JsonLd
        data={[
          breadcrumbRecords([
            { name: 'خانه', path: ROUTES.home },
            { name: 'وبلاگ', path: ROUTES.blog },
            { name: author.name, path: ROUTES.blogAuthor(slug) },
          ]),
          ...collectionPageJsonLd({
            name: author.name,
            description: `مطالب ${author.name} در وبلاگ شارژینو.`,
            path: ROUTES.blogAuthor(slug),
            items,
          }),
        ]}
      />
      <Container className="flex flex-col gap-6 py-8 sm:py-12">
        <header className="flex flex-col gap-2">
          <h1 className="text-2xl font-bold text-text">{author.name}</h1>
          <p className="text-sm text-text/70">مطالب این نویسنده در وبلاگ شارژینو.</p>
        </header>
        {items.length === 0 ? (
          <p className="text-sm text-text/70">مطلبی از این نویسنده نیست.</p>
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
          hrefFor={(next) => (next > 1 ? `${ROUTES.blogAuthor(slug)}?page=${next}` : ROUTES.blogAuthor(slug))}
        />
      </Container>
    </div>
  );
}
