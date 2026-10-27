import type { Metadata } from 'next';
import { unstable_noStore as noStore } from 'next/cache';
import { getCatalogHome } from '@/actions/catalog.actions';
import { HomeScreen } from '@/components/home';
import { Container } from '@/components/ui';
import { ROUTES } from '@/lib/routes';
import { storefrontHomeCatalog } from '@/lib/catalog-page-data';
import { HOME_TITLE, PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const revalidate = 3600;

export const metadata: Metadata = {
  ...buildMetadata({
    title: HOME_TITLE,
    description: PAGE_DESCRIPTIONS.home,
    path: ROUTES.home,
  }),
  title: {
    absolute: HOME_TITLE,
  },
};

export default async function HomePage() {
  const result = await getCatalogHome();
  if (!result.ok) {
    noStore();
    return (
      <Container className="py-16">
        <h1 className="text-2xl font-bold text-text">کاتالوگ در دسترس نیست</h1>
        <p className="mt-3 text-sm text-text/60">{result.message}</p>
      </Container>
    );
  }

  const catalog = result.data;
  const empty =
    catalog.groups.length === 0 &&
    catalog.popular.length === 0 &&
    catalog.flashSale.length === 0 &&
    catalog.banners.length === 0;

  if (empty) {
    return (
      <Container className="py-16">
        <h1 className="text-2xl font-bold text-text">به‌زودی</h1>
        <p className="mt-3 text-sm text-text/60">هنوز محصولی برای نمایش در فروشگاه نیست.</p>
      </Container>
    );
  }

  return <HomeScreen catalog={storefrontHomeCatalog(catalog)} />;
}
