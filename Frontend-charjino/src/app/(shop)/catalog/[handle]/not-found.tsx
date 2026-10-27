import type { Metadata } from 'next';
import { NotFoundScreen } from '@/components/seo/not-found-screen';
import { PRODUCT_NOT_FOUND_TITLE, notFoundMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = notFoundMetadata(PRODUCT_NOT_FOUND_TITLE);

export default function ProductNotFound() {
  return (
    <NotFoundScreen
      heading={PRODUCT_NOT_FOUND_TITLE}
      message="این محصول در فروشگاه نیست یا دیگر نمایش داده نمی‌شود."
    />
  );
}
