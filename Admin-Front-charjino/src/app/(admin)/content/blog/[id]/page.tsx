import { listUsers } from '@/actions/admin.actions';
import { getBlogPost, listBlogCategories, listBlogTags } from '@/actions/blog.actions';
import { BlogEditorScreen } from '@/components/admin/blog/blog-editor-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { notFound } from 'next/navigation';

export const dynamic = 'force-dynamic';

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function AdminBlogEditPage({ params }: PageProps) {
  const { id } = await params;
  const postId = Number(id);
  if (!Number.isInteger(postId) || postId < 1) notFound();
  const [post, categories, tags, authors] = await Promise.all([
    getBlogPost(postId),
    listBlogCategories(),
    listBlogTags(),
    listUsers({ role: 'admin' })
  ]);
  if (!post.ok) notFound();

  return (
    <div>
      <PageBreadcrumb pageTitle='ویرایش مطلب' />
      <ComponentCard title={post.item.title} desc='ذخیره خودکار پیش‌نویس هر ۳۰ ثانیه، نسخه‌ها و انتشار زمان‌بندی‌شده.'>
        <BlogEditorScreen
          post={post.item}
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
