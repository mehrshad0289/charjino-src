import { getHomeCms } from '@/actions/admin.actions';
import { FlashSaleScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'فروش ویژه'
};

export default async function HomeFlashSalePage() {
  const home = await getHomeCms();
  const error = home.ok ? undefined : home.message;

  return (
    <div>
      <PageBreadcrumb pageTitle='فروش ویژه' />
      <ComponentCard
        title='اسلایدر فروش ویژه'
        desc='عنوان، شمارنده و کارت‌های محصول از اینجا به هوم می‌روند. حرکت اسلایدر در فروشگاه پیوسته است.'
      >
        <FlashSaleScreen
          home={
            home.ok
              ? home.data
              : {
                  flashSaleTitle: 'فروش ویژه',
                  flashSaleSubtitle: '',
                  flashSaleEndsAt: null,
                  popularTitle: '',
                  popularSubtitle: '',
                  banners: [],
                  flashSale: [],
                  popular: []
                }
          }
          error={error}
        />
      </ComponentCard>
    </div>
  );
}
