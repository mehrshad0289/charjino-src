import { getGroup } from '@/actions/admin.actions';
import { GroupEditScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { notFound } from 'next/navigation';

type GroupEditPageProps = {
  params: Promise<{ id: string }>;
};

export default async function GroupEditPage({ params }: GroupEditPageProps) {
  const { id } = await params;
  const result = await getGroup(Number(id));
  if (!result.ok) {
    return (
      <div>
        <PageBreadcrumb pageTitle='ویرایش دسته' />
        <ComponentCard title='ویرایش دسته'>
          <p className='text-sm text-gray-500'>{result.message}</p>
        </ComponentCard>
      </div>
    );
  }

  if (!result.item) notFound();

  return (
    <div>
      <PageBreadcrumb pageTitle='ویرایش دسته' />
      <ComponentCard title={result.item.title} desc='نام، slug، توضیح و عکس نمایشی برای کاربران ایرانی'>
        <GroupEditScreen group={result.item} />
      </ComponentCard>
    </div>
  );
}
