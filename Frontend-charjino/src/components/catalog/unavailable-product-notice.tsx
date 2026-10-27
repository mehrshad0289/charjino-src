import Link from 'next/link';
import { Button } from '@/components/ui';
import { ROUTES } from '@/lib/routes';
import type { CatalogProduct } from '@/types/catalog';

export function UnavailableProductNotice({ product }: { product: CatalogProduct }) {
  const alternate = product.alternate ?? null;
  const similar = (product.similar ?? [])
    .filter((item) => item.handle && item.handle !== alternate?.handle)
    .slice(0, 4);

  return (
    <div className="flex flex-col gap-3 rounded-2xl bg-card px-4 py-4 ring-1 ring-border">
      <p className="text-sm font-semibold text-text">این محصول فعلاً ناموجود است</p>
      <p className="text-sm leading-7 text-text/80">
        ثبت سفارش برای این صفحه بسته است. نسخهٔ زندهٔ همین بازی یا محصولات همین دسته را ببینید.
      </p>
      {alternate ? (
        <Button href={ROUTES.catalog(alternate.handle)} fullWidth>
          خرید نسخهٔ موجود: {alternate.title}
        </Button>
      ) : null}
      {similar.length > 0 ? (
        <ul className="flex flex-col gap-2">
          {similar.map((item) => (
            <li key={item.handle}>
              <Link href={ROUTES.catalog(item.handle)} className="text-sm font-semibold text-primary-600 hover:underline">
                {item.title}
              </Link>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
