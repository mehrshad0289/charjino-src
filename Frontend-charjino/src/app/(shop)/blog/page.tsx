import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { getBlogHub } from '@/actions/blog.actions';
import { BlogCard } from '@/components/blog/blog-card';
import { BlogPager } from '@/components/blog/blog-pager';
import { BlogSearchForm } from '@/components/blog/blog-search-form';
import { JsonLd } from '@/components/seo/json-ld';
import { Chip, Container } from '@/components/ui';
import { collectionPageJsonLd, breadcrumbRecords } from '@/lib/blog-schema';
import { ROUTES } from '@/lib/routes';
import { BLOG_HUB_LEDE, PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const revalidate = 120;

type PageProps = {
  searchParams: Promise<{ page?: string }>;
};

export async function generateMetadata({ searchParams }: PageProps): Promise<Metadata> {
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const title = page > 1 ? `آموزش شارژ بازی و گیفت کارت | صفحه ${page}` : 'آموزش خرید شارژ بازی و گیفت کارت';
  return buildMetadata({
    title,
    description: PAGE_DESCRIPTIONS.blog,
    path: page > 1 ? `${ROUTES.blog}?page=${page}` : ROUTES.blog,
    noindex: page > 1,
    follow: page > 1 ? true : undefined,
  });
}

export default async function BlogHubPage({ searchParams }: PageProps) {
  const query = await searchParams;
  const page = Math.max(1, Number.parseInt(query.page ?? '1', 10) || 1);
  const result = await getBlogHub(page);
  if (!result.ok) {
    if ('notFound' in result && result.notFound) notFound();
    return (
      <div className="bg-surface">
        <Container className="py-12 text-sm text-text/70">{result.message}</Container>
      </div>
    );
  }
  const { categories, latest, popular, meta } = result.data;
  const items = latest ?? [];

  return (
    <div className="bg-surface">
      <JsonLd
        data={[
          breadcrumbRecords([
            { name: 'خانه', path: ROUTES.home },
            { name: 'وبلاگ', path: ROUTES.blog },
          ]),
          ...collectionPageJsonLd({
            name: 'وبلاگ شارژینو',
            description: PAGE_DESCRIPTIONS.blog,
            path: ROUTES.blog,
            items,
          }),
        ]}
      />
      <Container className="flex flex-col gap-8 py-8 sm:py-12">
        <header className="flex flex-col gap-4">
          <h1 className="text-2xl font-bold text-text">وبلاگ شارژینو</h1>
          <p className="text-sm text-text/70">{BLOG_HUB_LEDE}</p>
          <BlogSearchForm />
        </header>
        {categories.length > 0 ? (
          <section className="flex flex-col gap-3">
            <h2 className="text-lg font-bold text-text">دسته‌ها</h2>
            <div className="flex flex-wrap gap-2">
              {categories.map((category) => (
                <Chip key={category.slug} appearance="filter" label={category.title} href={ROUTES.blogCategory(category.slug)} />
              ))}
            </div>
          </section>
        ) : null}
        <section className="flex flex-col gap-4">
          <h2 className="text-lg font-bold text-text">{page > 1 ? `مطالب · صفحه ${page}` : 'تازه‌ها'}</h2>
          {items.length === 0 ? (
            <p className="text-sm text-text/60">هنوز مطلب منتشرشده‌ای نیست.</p>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {items.map((post) => (
                <BlogCard key={post.id} post={post} />
              ))}
            </div>
          )}
          <BlogPager
            page={meta?.page ?? page}
            totalPages={meta?.totalPages ?? 1}
            hrefFor={(next) => (next > 1 ? `${ROUTES.blog}?page=${next}` : ROUTES.blog)}
          />
        </section>
        {page === 1 && popular.length > 0 ? (
          <section className="flex flex-col gap-4">
            <h2 className="text-lg font-bold text-text">پربازدید</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {popular.map((post) => (
                <BlogCard key={post.id} post={post} />
              ))}
            </div>
          </section>
        ) : null}
      </Container>
    </div>
  );
}
