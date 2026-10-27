import { getProduct, listGiftCards, listGroups } from '@/actions/admin.actions';
import { ProductEditScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import { notFound } from 'next/navigation';

export const dynamic = 'force-dynamic';

type ProductEditPageProps = {
  params: Promise<{ id: string }>;
};

export default async function ProductEditPage({ params }: ProductEditPageProps) {
  const { id } = await params;
  const [result, groups] = await Promise.all([getProduct(Number(id)), listGroups()]);
  if (!result.ok) notFound();

  let giftCards: Awaited<ReturnType<typeof listGiftCards>> = { ok: true, items: [] };
  if (result.item.giftProxy) {
    giftCards = await listGiftCards({ platform: result.item.platform, region: result.item.region });
    if (giftCards.ok && giftCards.items.length === 0 && result.item.region) {
      giftCards = await listGiftCards({ platform: result.item.platform });
    }
  }

  return (
    <div>
      <PageBreadcrumb pageTitle='ویرایش محصول' />
      <ComponentCard title={result.item.title} desc='اطلاعات نمایشی، دسته، قیمت فروش و تخفیف هر بسته'>
        <ProductEditScreen product={result.item} groups={groups.ok ? groups.items : []} giftCards={giftCards.ok ? giftCards.items : []} />
      </ComponentCard>
    </div>
  );
}
