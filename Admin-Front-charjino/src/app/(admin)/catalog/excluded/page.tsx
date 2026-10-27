import { listExcludedProducts } from '@/actions/admin.actions';
import { Alert, ComponentCard, PageBreadcrumb } from '@/components/ui';
import Link from 'next/link';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'مستثناها'
};

export default async function ExcludedProductsPage() {
  const result = await listExcludedProducts(1);

  return (
    <div>
      <PageBreadcrumb pageTitle='مستثناها' />
      <ComponentCard title='محصولات خارج از ویترین' desc='manual، Via Login و فیلدهای ورود. مشتری این‌ها را در فروشگاه نمی‌بیند.'>
        {!result.ok ? (
          <Alert variant='error' title='خوانده نشد' message={result.message} />
        ) : result.items.length === 0 ? (
          <Alert variant='info' title='خالی' message='محصول مستثنایی ثبت نشده است.' />
        ) : (
          <ul className='space-y-2'>
            {result.items.map((product) => (
              <li key={product.id} className='text-sm text-gray-600 dark:text-white/80'>
                <Link href={`/catalog/products/${product.id}`} className='font-medium text-brand-500'>
                  {product.title}
                </Link>
                <span className='ms-2'>{product.excludedReason}</span>
              </li>
            ))}
          </ul>
        )}
      </ComponentCard>
    </div>
  );
}
