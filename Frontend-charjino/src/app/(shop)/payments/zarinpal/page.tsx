import type { Metadata } from 'next';
import { Suspense } from 'react';
import { ZarinpalVerifyScreen } from '@/components/checkout/zarinpal-verify-screen';
import { Container } from '@/components/ui';
import { ROUTES } from '@/lib/routes';
import { PAGE_DESCRIPTIONS, buildMetadata } from '@/lib/seo';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = buildMetadata({
  title: 'تأیید پرداخت',
  description: PAGE_DESCRIPTIONS.zarinpal,
  path: ROUTES.zarinpalReturn,
  noindex: true,
});

export default function ZarinpalPaymentReturnPage() {
  return (
    <Suspense
      fallback={
        <Container className="py-16 text-center text-sm text-text/70">در حال بازگشت از درگاه…</Container>
      }
    >
      <ZarinpalVerifyScreen />
    </Suspense>
  );
}
