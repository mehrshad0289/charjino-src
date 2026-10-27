import type { Metadata } from 'next';
import { Suspense } from 'react';
import { ZibalVerifyScreen } from '@/components/checkout/zibal-verify-screen';
import { Container } from '@/components/ui';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'تأیید پرداخت',
  description: PAGE_DESCRIPTIONS.zibal,
  path: ROUTES.zibalReturn,
  noindex: true,
});

export default function ZibalPaymentReturnPage() {
  return (
    <Suspense
      fallback={
        <Container className="py-16 text-center text-sm text-text/70">در حال بازگشت از درگاه…</Container>
      }
    >
      <ZibalVerifyScreen />
    </Suspense>
  );
}
