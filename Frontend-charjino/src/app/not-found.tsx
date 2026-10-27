import type { Metadata } from 'next';
import { SiteFooter, SiteHeader } from '@/components/layout';
import { NotFoundScreen } from '@/components/seo/not-found-screen';
import { notFoundMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = notFoundMetadata();

export default function NotFound() {
  return (
    <>
      <SiteHeader />
      <NotFoundScreen />
      <SiteFooter />
    </>
  );
}
