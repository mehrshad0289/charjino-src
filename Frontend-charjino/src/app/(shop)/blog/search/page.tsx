import type { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { listBlogPosts } from '@/actions/blog.actions';
import { BlogCard } from '@/components/blog/blog-card';
import { BlogSearchForm } from '@/components/blog/blog-search-form';
import { Container } from '@/components/ui';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata, searchDescription } from '@/lib/seo';
import { canonicalPath } from '@/lib/site';

export const dynamic = 'force-dynamic';

type PageProps = {
  searchParams: Promise<{ q?: string; page?: string }>;
};

export async function generateMetadata({ searchParams }: PageProps): Promise<Metadata> {
  const params = await searchParams;
  const q = (params.q ?? '').trim().slice(0, 100);
  return buildMetadata({
    title: q ? `جستجوی وبلاگ: ${q}` : 'جستجوی وبلاگ',
    description: q ? searchDescription(q) : PAGE_DESCRIPTIONS.blog,
    path: canonicalPath(ROUTES.blogSearch, q ? { q } : undefined),
    noindex: true,
    follow: true,
  });
}

export default async function BlogSearchPage({ searchParams }: PageProps) {
  const params = await searchParams;
  const q = (params.q ?? '').trim().slice(0, 100);
  const page = Math.max(1, Number.parseInt(params.page ?? '1', 10) || 1);
  const result = await listBlogPosts({ q: q.length >= 2 ? q : undefined, page });
  if (!result.ok && 'notFound' in result && result.notFound) notFound();
  const items = result.ok ? result.data.items : [];

  return (
    <div className="bg-surface">
      <Container className="flex flex-col gap-6 py-8 sm:py-12">
        <header className="flex flex-col gap-4">
          <h1 className="text-2xl font-bold text-text">{q ? `نتایج «${q}»` : 'جستجوی وبلاگ'}</h1>
          <BlogSearchForm initialQ={q} />
        </header>
        {result.ok ? null : <p className="text-sm text-text/70">{result.message}</p>}
        {q.length > 0 && q.length < 2 ? (
          <p className="text-sm text-text/70">برای جستجو حداقل دو حرف بنویس.</p>
        ) : items.length === 0 ? (
          <p className="text-sm text-text/70">مطلبی پیدا نشد.</p>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {items.map((post) => (
              <BlogCard key={post.id} post={post} />
            ))}
          </div>
        )}
      </Container>
    </div>
  );
}
