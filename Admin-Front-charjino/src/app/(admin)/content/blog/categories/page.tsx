import { listBlogCategories } from '@/actions/blog.actions';
import { BlogCategoriesScreen } from '@/components/admin/blog/blog-categories-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'دسته‌های وبلاگ'
};

export const dynamic = 'force-dynamic';

export default async function AdminBlogCategoriesPage() {
  const result = await listBlogCategories();

  return (
    <div>
      <PageBreadcrumb pageTitle='دسته‌های وبلاگ' />
      <ComponentCard title='دسته‌بندی مطالب' desc='دسته‌ها در صفحه وبلاگ فروشگاه دیده می‌شوند.'>
        <BlogCategoriesScreen items={result.ok ? result.items : []} error={result.ok ? undefined : result.message} />
      </ComponentCard>
    </div>
  );
}
