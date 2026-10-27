'use client';

import { buildSeoChecks, type SeoCheckInput } from './blog-seo-checks';

const STATUS_CLASS = {
  ok: 'text-success-500',
  warn: 'text-warning-500',
  info: 'text-gray-500 dark:text-gray-400'
} as const;

const STATUS_MARK = {
  ok: '✓',
  warn: '⚠',
  info: 'ℹ'
} as const;

export function BlogSeoChecklist({ values }: { values: SeoCheckInput }) {
  const items = buildSeoChecks(values);
  const title = values.metaTitle.trim() || `${values.title.trim()} | شارژینو`;
  const desc = values.metaDescription.trim() || values.excerpt.trim();

  return (
    <aside className='space-y-2 rounded-2xl border border-gray-200 p-4 text-sm dark:border-gray-800'>
      <p className='font-medium text-gray-800 dark:text-white'>چک‌لیست SEO (هشدار)</p>
      <ul className='space-y-1'>
        {items.map((item) => (
          <li key={item.text} className={STATUS_CLASS[item.status]}>
            {STATUS_MARK[item.status]} {item.text}
          </li>
        ))}
      </ul>
      <div className='mt-4 rounded-xl bg-gray-50 p-3 dark:bg-gray-900'>
        <p className='text-xs text-success-600'>charjino.ir › blog › {values.slug || '...'}</p>
        <p className='text-base text-blue-700'>{title || 'عنوان'}</p>
        <p className='text-sm text-gray-600 dark:text-gray-300'>{desc || 'توضیح متا'}</p>
      </div>
    </aside>
  );
}
