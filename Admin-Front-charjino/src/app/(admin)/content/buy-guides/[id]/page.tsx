import { getBuyGuide } from '@/actions/buy-guide.actions';
import { BuyGuideEditorScreen } from '@/components/admin/buy-guide/editor-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { notFound } from 'next/navigation';

export const dynamic = 'force-dynamic';

type PageProps = {
  params: Promise<{ id: string }>;
};

export default async function BuyGuideEditPage({ params }: PageProps) {
  const { id } = await params;
  const result = await getBuyGuide(Number(id));
  if (!result.ok) notFound();

  return (
    <div>
      <PageBreadcrumb pageTitle='ویرایش راهنمای خرید' />
      <ComponentCard title={result.item.title || 'راهنمای خرید'} desc='سکشن‌ها را جدا ذخیره کنید؛ ارث‌بری فقط در زیردسته و محصول معنا دارد.'>
        <BuyGuideEditorScreen guide={result.item} />
      </ComponentCard>
    </div>
  );
}
