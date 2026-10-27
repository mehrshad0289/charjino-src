'use client';

import { useEffect, useState } from 'react';
import { getMyWallet, startOrderPayment } from '@/actions/checkout.actions';
import { Button, LoadingOverlay } from '@/components/ui';
import { formatTomanNumber } from '@/lib/price';
import { continueShopPayment } from '@/lib/payment-return';
import { notify } from '@/lib/toast';

export function InvoicePayButton({ number, amountToman }: { number: string; amountToman?: number }) {
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [wallet, setWallet] = useState<number | null>(null);

  useEffect(() => {
    void getMyWallet().then((result) => {
      if (result.ok) setWallet(result.data.balanceToman);
    });
  }, []);

  async function pay(method?: 'wallet') {
    setLoading(true);
    setMessage(null);
    const result = await startOrderPayment(number, window.location.origin, method);
    if (!result.ok) {
      setLoading(false);
      setMessage(result.message);
      notify.error(result.message);
      return;
    }
    const next = await continueShopPayment(result.data);
    setLoading(false);
    if (!next.ok) {
      setMessage(next.message);
      notify.error(next.message);
      return;
    }
    window.location.assign(next.href);
  }

  const canWallet = wallet != null && amountToman != null && wallet >= amountToman && amountToman > 0;

  return (
    <div className="flex flex-col gap-2">
      <LoadingOverlay open={loading} label="در حال رفتن به درگاه..." />
      <Button type="button" loading={loading} disabled={loading} onClick={() => void pay()}>
        ادامه پرداخت
      </Button>
      {canWallet ? (
        <Button type="button" variant="outline" loading={loading} disabled={loading} onClick={() => void pay('wallet')}>
          پرداخت با کیف پول ({formatTomanNumber(wallet ?? 0)} تومان)
        </Button>
      ) : null}
      {message ? <p className="text-sm text-error-200">{message}</p> : null}
    </div>
  );
}
