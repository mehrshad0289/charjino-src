import type { Metadata } from 'next';
import { NotFoundScreen } from '@/components/seo/not-found-screen';
import { CATEGORY_NOT_FOUND_TITLE, notFoundMetadata } from '@/lib/seo';

export const metadata: Metadata = notFoundMetadata(CATEGORY_NOT_FOUND_TITLE);

export default function CategorySlugNotFound() {
  return (
    <NotFoundScreen
      heading={CATEGORY_NOT_FOUND_TITLE}
      message="این دسته در فروشگاه نیست یا پنهان شده."
    />
  );
}
