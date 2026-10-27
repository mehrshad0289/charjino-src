import { getHomeCms, listProducts } from '@/actions/admin.actions';
import { PopularScreen } from '@/components/admin';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'محبوب‌های امروز'
};

export default async function HomePopularPage() {
  const [home, products] = await Promise.all([getHomeCms(), listProducts({ perPage: 100 })]);
  const error = !home.ok ? home.message : !products.ok ? products.message : undefined;

  return (
    <div>
      <PageBreadcrumb pageTitle='محبوب‌های امروز' />
      <ComponentCard title='بخش محبوب هوم' desc='عنوان بخش و محصولاتی که در هوم با برچسب محبوب دیده می‌شوند.'>
        <PopularScreen
          home={
            home.ok
              ? home.data
              : {
                  flashSaleTitle: '',
                  flashSaleSubtitle: '',
                  flashSaleEndsAt: null,
                  popularTitle: 'محبوب‌های امروز',
                  popularSubtitle: '',
                  banners: [],
                  flashSale: [],
                  popular: []
                }
          }
          products={products.ok ? products.items : []}
          error={error}
        />
      </ComponentCard>
    </div>
  );
}
