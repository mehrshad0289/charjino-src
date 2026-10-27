'use client';

import { Alert } from '@/components/ui';
import { lintBuyGuideText } from '@/lib/buy-guide-lint';

export function ContentLint({ text }: { text: string }) {
  const warnings = lintBuyGuideText(text);
  if (warnings.length === 0) return null;
  return (
    <div className='space-y-2'>
      {warnings.map((item) => (
        <Alert key={item} variant='warning' title='بررسی محتوا' message={item} />
      ))}
    </div>
  );
}
