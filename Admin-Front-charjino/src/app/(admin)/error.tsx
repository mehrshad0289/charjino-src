'use client';

import { Button } from '@/components/ui';

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <div className='space-y-4 rounded-xl border border-gray-200 p-6 dark:border-white/5'>
      <h1 className='text-lg font-semibold text-gray-800 dark:text-white/90'>خطا</h1>
      <p className='text-sm text-gray-500'>{error.message || 'درخواست انجام نشد'}</p>
      <Button type='button' onClick={() => reset()}>
        تلاش مجدد
      </Button>
    </div>
  );
}
