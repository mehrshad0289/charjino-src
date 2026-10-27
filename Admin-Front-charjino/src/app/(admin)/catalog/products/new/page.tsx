import { listGroups } from '@/actions/admin.actions';
import { GiftProductForm } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'محصول گیفت‌معادل'
};

export const dynamic = 'force-dynamic';

export default async function NewGiftProductPage() {
  const groups = await listGroups();

  return (
    <div>
      <PageBreadcrumb pageTitle='محصول گیفت‌معادل' />
      <ComponentCard title='محصول با تحویل گیفت‌کارت' desc='دسته، مشخصات و پلتفرم را بنویس. بسته‌ها را در صفحهٔ بعد اضافه می‌کنی.'>
        <GiftProductForm groups={groups.ok ? groups.items : []} />
      </ComponentCard>
    </div>
  );
}
