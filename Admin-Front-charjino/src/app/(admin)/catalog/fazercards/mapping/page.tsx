import { getFazerCardsStatus, getFazerCardsSyncStatus, listProducts } from '@/actions/admin.actions';
import { FazerCardsMappingScreen } from '@/components/admin/fazer-cards/fazer-cards-mapping-screen';
import { ComponentCard, PageBreadcrumb } from '@/components/ui';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'نگاشت SKU FazerCards'
};

const SCOPE_GROUP: Record<string, string> = {
  'steam-gifts': 'steam-gifts',
  'steam-topup': 'steam-wallet',
  telegram: 'telegram',
  funpay: 'funpay',
  gamekeys: 'game-keys',
  manual: 'manual-services',
  giftcards: 'gift-card',
  topups: 'game-topup'
};

export default async function FazerCardsMappingPage() {
  const [result, lastSync] = await Promise.all([getFazerCardsStatus(), getFazerCardsSyncStatus()]);
  const scope = lastSync.ok ? lastSync.data.scope : undefined;
  const category = scope && scope !== 'all' ? SCOPE_GROUP[scope] : undefined;
  const products = await listProducts({
    perPage: 20,
    page: 1,
    source: 'supplier',
    category
  });

  return (
    <div>
      <PageBreadcrumb pageTitle='نگاشت SKU' />
      <ComponentCard
        title='اتصال آفر FazerCards به فروشگاه'
        desc='برای افزودن بازی مثل FC 27: دسته را گیفت استیم بگذار، نام را جستجو کن، افزودن به چارژینو. اگر نسخه کلید است دسته را کلید بازی بگذار.'
      >
        <FazerCardsMappingScreen
          products={products.ok ? products.items : []}
          lastSync={lastSync.ok ? lastSync.data : undefined}
          status={result.ok ? result.data : undefined}
          error={!result.ok ? result.message : !products.ok ? products.message : undefined}
        />
      </ComponentCard>
    </div>
  );
}
