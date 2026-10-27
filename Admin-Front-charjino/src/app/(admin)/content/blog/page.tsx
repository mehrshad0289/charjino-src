import { listBlogCategories, listBlogPosts } from '@/actions/blog.actions';
import { BlogLibraryScreen } from '@/components/admin/blog/blog-library-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { parseTablePage, parseTablePerPage } from '@/lib/table-page';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'کتابخانه وبلاگ'
};

export const dynamic = 'force-dynamic';

type PageProps = {
  searchParams: Promise<{ q?: string; status?: string; categoryId?: string; page?: string; perPage?: string }>;
};

export default async function AdminBlogLibraryPage({ searchParams }: PageProps) {
  const params = await searchParams;
  const page = parseTablePage(params.page);
  const perPage = parseTablePerPage(params.perPage);
  const [posts, categories] = await Promise.all([
    listBlogPosts({
      q: params.q,
      status: params.status,
      categoryId: params.categoryId,
      page,
      perPage
    }),
    listBlogCategories()
  ]);

  return (
    <div>
      <PageBreadcrumb pageTitle='کتابخانه وبلاگ' />
      <ComponentCard title='مطالب' desc='فقط از این پنل مطلب ثبت می‌شود. بعد از انتشار در بخش وبلاگ فروشگاه دیده می‌شود.'>
        <BlogLibraryScreen
          items={posts.ok ? posts.items : []}
          categories={categories.ok ? categories.items : []}
          error={posts.ok ? undefined : posts.message}
          q={params.q}
          status={params.status}
          categoryId={params.categoryId}
          page={posts.ok ? posts.meta.page : page}
          perPage={posts.ok ? posts.meta.perPage : perPage}
          totalPages={posts.ok ? posts.meta.totalPages : 1}
          total={posts.ok ? posts.meta.total : 0}
        />
      </ComponentCard>
    </div>
  );
}
