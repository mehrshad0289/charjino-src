'use client';

import { InvoicePayButton } from '@/components/invoices/invoice-pay-button';
import { Button } from '@/components/ui';

export function OrderPrintButton() {
  return (
    <Button type="button" variant="outline" size="M" onClick={() => window.print()}>
      چاپ فاکتور
    </Button>
  );
}

export function ProfilePayButton({ number, amountToman }: { number: string; amountToman?: number }) {
  return <InvoicePayButton number={number} amountToman={amountToman} />;
}
