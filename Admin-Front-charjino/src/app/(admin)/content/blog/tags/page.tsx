import { listBlogTags } from '@/actions/blog.actions';
import { BlogTagsScreen } from '@/components/admin/blog/blog-tags-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'تگ‌های وبلاگ'
};

export const dynamic = 'force-dynamic';

export default async function AdminBlogTagsPage() {
  const result = await listBlogTags();

  return (
    <div>
      <PageBreadcrumb pageTitle='تگ‌های وبلاگ' />
      <ComponentCard title='تگ مطالب' desc='تگ با کمتر از سه مطلب منتشرشده به‌صورت خودکار noindex می‌شود.'>
        <BlogTagsScreen items={result.ok ? result.items : []} error={result.ok ? undefined : result.message} />
      </ComponentCard>
    </div>
  );
}
