import { SiteFooter, SiteHeader } from '@/components/layout';
import { FavoritesProvider } from '@/components/profile/favorites-provider';

export default function ShopLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <FavoritesProvider>
      <SiteHeader />
      <div className="relative z-0 pb-24 lg:pb-0">
        {children}
        <SiteFooter />
      </div>
    </FavoritesProvider>
  );
}
