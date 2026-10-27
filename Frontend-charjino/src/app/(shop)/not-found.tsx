import type { Metadata } from 'next';
import { NotFoundScreen } from '@/components/seo/not-found-screen';
import { notFoundMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = notFoundMetadata();

export default function ShopNotFound() {
  return <NotFoundScreen />;
}
