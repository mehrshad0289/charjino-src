'use client';

import { Button, Container } from '@/components/ui';

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <Container className="py-16">
      <h1 className="text-2xl font-bold text-text">خطا</h1>
      <p className="mt-3 text-sm text-text/70">{error.message || 'درخواست انجام نشد'}</p>
      <div className="mt-6">
        <Button type="button" onClick={() => reset()}>
          تلاش مجدد
        </Button>
      </div>
    </Container>
  );
}
