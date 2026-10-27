import type { Metadata } from 'next';
import { notFound, permanentRedirect } from 'next/navigation';
import { getBlogCategory } from '@/actions/blog.actions';
import { BlogCard } from '@/components/blog/blog-card';
import { BlogHtml } from '@/components/blog/blog-html';
import { BlogPager } from '@/components/blog/blog-pager';
import { BlogSearchForm } from '@/components/blog/blog-search-form';
import { JsonLd } from '@/components/seo/json-ld';
import { Chip, Container } from '@/components/ui';
import { prepareBlogHtml } from '@/lib/blog-toc';
import { breadcrumbRecords, collectionPageJsonLd } from '@/lib/blog-schema';
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
  const result = await getBlogCategory(slug, page);
  if (!result.ok) {
    return buildMetadata({
      title: 'دسته وبلاگ',
      description: PAGE_DESCRIPTIONS.blog,
      path: ROUTES.blogCategory(slug),
      noindex: true,
      follow: true,
    });
  }
  const titleBase = result.data.seo?.title || `${result.data.category.title} | وبلاگ شارژینو`;
  const title = page > 1 ? `${titleBase} | صفحه ${page}` : titleBase;
  const path = page > 1 ? `${ROUTES.blogCategory(result.data.category.slug)}?page=${page}` : ROUTES.blogCategory(result.data.category.slug);
  return buildMetadata({
    title,
    description: result.data.seo?.description || result.data.category.description || PAGE_DESCRIPTIONS.blog,
    path,
    noindex: Boolean(result.data.seo?.noindex || result.data.category.noindex),
    follow: true,
  });
}

export default async function BlogCategoryPage({ params, searchParams }: PageProps) {
  const { slug } = await params;
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const result = await getBlogCategory(slug, page);
  if (!result.ok) {
    if ('redirectTo' in result && result.redirectTo) permanentRedirect(result.redirectTo);
    if ('notFound' in result && result.notFound) notFound();
    return (
      <div className="bg-surface">
        <Container className="py-12 text-sm text-text/70">{result.message}</Container>
      </div>
    );
  }
  const { category, items, meta } = result.data;
  const body = category.bodyHtml ? prepareBlogHtml(category.bodyHtml).html : '';

  return (
    <div className="bg-surface">
      <JsonLd
        data={[
          breadcrumbRecords([
            { name: 'خانه', path: ROUTES.home },
            { name: 'وبلاگ', path: ROUTES.blog },
            { name: category.title, path: ROUTES.blogCategory(category.slug) },
          ]),
          ...collectionPageJsonLd({
            name: category.title,
            description: category.description || PAGE_DESCRIPTIONS.blog,
            path: ROUTES.blogCategory(category.slug),
            items,
          }),
        ]}
      />
      <Container className="flex flex-col gap-6 py-8 sm:py-12">
        <header className="flex flex-col gap-4">
          <h1 className="text-2xl font-bold text-text">{category.title}</h1>
          {category.description ? <p className="text-sm text-text/70">{category.description}</p> : null}
          {body ? <BlogHtml html={body} /> : null}
          <BlogSearchForm />
          {(category.children ?? []).length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {(category.children ?? []).map((child) => (
                <Chip key={child.slug} appearance="filter" label={child.title} href={ROUTES.blogCategory(child.slug)} />
              ))}
            </div>
          ) : null}
        </header>
        {items.length === 0 ? (
          <p className="text-sm text-text/70">در این دسته مطلبی نیست.</p>
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
          hrefFor={(next) =>
            next > 1 ? `${ROUTES.blogCategory(category.slug)}?page=${next}` : ROUTES.blogCategory(category.slug)
          }
        />
      </Container>
    </div>
  );
}
