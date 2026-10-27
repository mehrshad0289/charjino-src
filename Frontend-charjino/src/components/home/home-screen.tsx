import dynamic from 'next/dynamic';
import { WaveDivider } from '@/components/ui/divider';
import type { CatalogHome } from '@/types/catalog';
import { HomeCategories } from './home-categories';
import { HomeFlashSale } from './home-flash-sale';
import { HomePopular } from './home-popular';

const HomeIntro = dynamic(() => import('./home-intro').then((mod) => mod.HomeIntro), { ssr: true });
const HomeFaq = dynamic(() => import('./home-faq').then((mod) => mod.HomeFaq), { ssr: true });

const HomeCarousel = dynamic(() => import('./home-carousel').then((mod) => mod.HomeCarousel), {
  ssr: true,
});

type HomeScreenProps = {
  catalog: CatalogHome;
};

export function HomeScreen({ catalog }: HomeScreenProps) {
  return (
    <div className="relative z-0 max-w-full overflow-x-clip">
      <main className="flex min-w-0 max-w-full flex-col overflow-x-clip">
        <HomeCarousel banners={catalog.banners} />
        <div className="flex min-w-0 max-w-full flex-col gap-y-8 overflow-x-clip pt-8">
          <HomeCategories groups={catalog.groups} />
          <HomeFlashSale
            items={catalog.flashSale}
            title={catalog.flashSaleTitle}
            subtitle={catalog.flashSaleSubtitle}
            endsAt={catalog.flashSaleEndsAt}
          />
          <HomePopular
            products={catalog.popular}
            title={catalog.popularTitle}
            subtitle={catalog.popularSubtitle}
          />
          <HomeIntro />
          <HomeFaq />
          <WaveDivider />
        </div>
      </main>
    </div>
  );
}
