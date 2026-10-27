'use client';

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { verifyZibalPayment } from '@/actions/checkout.actions';
import { Button, Container, PageLoader, WaveDivider } from '@/components/ui';
import { ROUTES } from '@/lib/routes';
import { onlyDigits } from '@/lib/fa';
import { notify } from '@/lib/toast';

export function ZibalVerifyScreen() {
  const router = useRouter();
  const params = useSearchParams();
  const started = useRef(false);
  const [message, setMessage] = useState('در حال تأیید پرداخت زیبال…');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (started.current) return;
    started.current = true;

    const trackId = onlyDigits(params.get('trackId') ?? '');
    const success = onlyDigits(params.get('success') ?? '');
    const status = onlyDigits(params.get('status') ?? '');
    const orderId = (params.get('orderId') ?? '').trim();

    if (!trackId) {
      const text = 'بازگشت از درگاه ناقص است. از پیگیری سفارش ادامه بده.';
      setError(text);
      notify.error(text);
      return;
    }

    void (async () => {
      const result = await verifyZibalPayment({ trackId, success, status, orderId });
      if (!result.ok) {
        setError(result.message);
        notify.error(result.message);
        return;
      }
      setMessage('پرداخت تأیید شد. در حال رفتن به فاکتور…');
      notify.success('پرداخت تأیید شد.');
      router.replace(`${ROUTES.profileOrder(result.data.number)}?from=psp`);
    })();
  }, [params, router]);

  return (
    <>
      <Container className="flex flex-col items-center gap-5 py-16 text-center">
        {error ? (
          <>
            <h1 className="text-3xl font-bold text-text">پرداخت تأیید نشد</h1>
            <p className="max-w-md text-sm leading-7 text-error-200">{error}</p>
            <Button href={ROUTES.profileOrders}>سفارش‌های من</Button>
          </>
        ) : (
          <>
            <PageLoader label={message} />
            <Link href={ROUTES.profileOrders} className="text-sm text-primary-600">
              اگر منتقل نشدی، سفارش‌های من را باز کن
            </Link>
          </>
        )}
      </Container>
      <WaveDivider />
    </>
  );
}
