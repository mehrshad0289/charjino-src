import Link from 'next/link';
import { CatalogCover } from '@/components/catalog/catalog-cover';
import { productImageAlt } from '@/lib/image-alt';
import { formatFromToman } from '@/lib/price';
import { ROUTES } from '@/lib/routes';
import type { FavoriteItem } from '@/types/profile';
import { FavoriteToggle } from './favorite-toggle';
import { ProfileEmpty, ProfileHeading, ProfilePager } from './profile-chrome';
import { ProfileSubnav } from './profile-subnav';

type ProfileFavoritesGridProps = {
  items: FavoriteItem[];
  page: number;
  totalPages: number;
  listError?: string | null;
};

export function ProfileFavoritesGrid({
  items,
  page,
  totalPages,
  listError = null,
}: ProfileFavoritesGridProps) {
  return (
    <>
      <ProfileSubnav />
      <ProfileHeading title="علاقه‌مندی‌ها" />
      {listError ? (
        <p className="text-sm text-error-200">{listError}</p>
      ) : items.length === 0 ? (
        <ProfileEmpty>هنوز محصولی ذخیره نشده است.</ProfileEmpty>
      ) : (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
          {items.map((item) => {
            const product = item.product;
            if (!product) return null;
            return (
              <li key={item.id} className="relative">
                <Link
                  href={ROUTES.catalog(product.handle)}
                  className="flex h-full flex-col overflow-hidden rounded-[20px] bg-paper ring-1 ring-border"
                >
                  <div className="relative aspect-square bg-card-muted">
                    <CatalogCover src={product.thumbnail ?? ''} alt={productImageAlt(product.title)} fill fit="contain" sizes="40vw" />
                  </div>
                  <div className="flex flex-col gap-1 px-3 py-3">
                    <h2 className="truncate text-sm font-semibold">{product.title}</h2>
                    <p className="text-xs text-primary-600">{formatFromToman(product.minPrice) || ' '}</p>
                  </div>
                </Link>
                <div className="absolute start-2 top-2">
                  <FavoriteToggle productId={product.id} returnTo={ROUTES.profileFavorites} compact />
                </div>
              </li>
            );
          })}
        </ul>
      )}
      <ProfilePager
        page={page}
        totalPages={totalPages}
        hrefFor={(next) => (next > 1 ? `${ROUTES.profileFavorites}?page=${next}` : ROUTES.profileFavorites)}
      />
    </>
  );
}
