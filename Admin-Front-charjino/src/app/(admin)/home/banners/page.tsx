import { getHomeCms } from '@/actions/admin.actions';
import { BannersScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'اسلایدر بنر'
};

export default async function HomeBannersPage() {
  const result = await getHomeCms();

  return (
    <div>
      <PageBreadcrumb pageTitle='اسلایدر بنر' />
      <ComponentCard
        title='سکشن یک هوم'
        desc='عکس، گیف یا ویدیو آپلود کن و لینک اسلاید را بگذار. ترتیب همان ترتیب لیست است.'
      >
        <BannersScreen items={result.ok ? result.data.banners : []} error={result.ok ? undefined : result.message} />
      </ComponentCard>
    </div>
  );
}
