import Link from 'next/link';
import { CatalogCover } from '@/components/catalog/catalog-cover';
import { productImageAlt } from '@/lib/image-alt';
import { ROUTES } from '@/lib/routes';
import type { BlogRelatedProduct } from '@/types/blog';

type BlogRelatedProductsProps = {
  products: BlogRelatedProduct[];
};

export function BlogRelatedProducts({ products }: BlogRelatedProductsProps) {
  if (products.length === 0) return null;
  return (
    <section className="flex flex-col gap-4">
      <h2 className="text-lg font-bold text-text">محصولات مرتبط</h2>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {products.map((product) => (
          <Link
            key={product.id}
            href={ROUTES.catalog(product.handle)}
            className="flex items-center gap-3 rounded-2xl bg-card p-3 ring-1 ring-border hover:ring-primary-600"
          >
            <div className="relative h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-card-muted">
              {product.image ? (
                <CatalogCover src={product.image} alt={productImageAlt(product.title)} fill fit="cover" sizes="64px" />
              ) : null}
            </div>
            <p className="text-sm font-medium text-text">{product.title}</p>
          </Link>
        ))}
      </div>
    </section>
  );
}
