import { listUsers } from '@/actions/admin.actions';
import { listBlogCategories, listBlogTags } from '@/actions/blog.actions';
import { BlogEditorScreen } from '@/components/admin/blog/blog-editor-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'مطلب جدید وبلاگ'
};

export const dynamic = 'force-dynamic';

export default async function AdminBlogNewPage() {
  const [categories, tags, authors] = await Promise.all([
    listBlogCategories(),
    listBlogTags(),
    listUsers({ role: 'admin' })
  ]);

  return (
    <div>
      <PageBreadcrumb pageTitle='مطلب جدید' />
      <ComponentCard title='ثبت مطلب' desc='اطلاعات، متن، سئو و پیش‌نمایش. ذخیره خودکار پیش‌نویس هر ۳۰ ثانیه اگر عنوان حداقل ۳ حرف باشد.'>
        <BlogEditorScreen
          categories={categories.ok ? categories.items : []}
          tags={tags.ok ? tags.items : []}
          authors={
            authors.ok
              ? authors.items.map((item) => ({
                  id: item.id,
                  name: item.name || item.email || item.id,
                  email: item.email
                }))
              : []
          }
        />
      </ComponentCard>
    </div>
  );
}
