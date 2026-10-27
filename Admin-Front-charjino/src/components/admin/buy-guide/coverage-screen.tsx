'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useMemo, useState } from 'react';
import { createBuyGuide } from '@/actions/buy-guide.actions';
import { Alert, Badge, Button } from '@/components/ui';
import { formatJalaliIso } from '@/lib/jalali';
import { toFaDigits } from '@/lib/fa';
import { useToastMessage } from '@/hooks/use-toast-message';
import type { BuyGuideCoverageNode, BuyGuideStatus } from '@/types/buy-guide';

function statusBadge(status: BuyGuideStatus) {
  if (status === 'published') return <Badge color='success'>منتشر</Badge>;
  if (status === 'draft') return <Badge color='warning'>پیش‌نویس</Badge>;
  return <Badge color='light'>ندارد</Badge>;
}

export function BuyGuideCoverageScreen({
  items,
  error
}: {
  items: BuyGuideCoverageNode[];
  error?: string;
}) {
  const router = useRouter();
  const [, setMessage] = useToastMessage();
  const [q, setQ] = useState('');
  const [busy, setBusy] = useState<string | null>(null);

  const filtered = useMemo(() => {
    const needle = q.trim();
    if (!needle) return items;
    return items
      .map((node) => ({
        ...node,
        children: node.children.filter(
          (child) => child.label.includes(needle) || child.subcategory.includes(needle) || node.label.includes(needle)
        )
      }))
      .filter((node) => node.label.includes(needle) || node.children.length > 0);
  }, [items, q]);

  async function openOrCreate(scope: {
    scopeType: 'category' | 'subcategory';
    category: string;
    subcategory?: string;
    guideId?: number | null;
  }) {
    if (scope.guideId) {
      router.push(`/content/buy-guides/${scope.guideId}`);
      return;
    }
    const key = `${scope.category}:${scope.subcategory ?? ''}`;
    setBusy(key);
    const result = await createBuyGuide({
      scopeType: scope.scopeType,
      category: scope.category,
      subcategory: scope.subcategory,
      status: 'draft',
      title: 'راهنمای خرید'
    });
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    router.push(`/content/buy-guides/${result.item.id}`);
    router.refresh();
  }

  if (error) return <Alert variant='error' title='پوشش خوانده نشد' message={error} />;

  return (
    <div className='space-y-4'>
      <input
        className='h-11 w-full rounded-xl border border-gray-200 px-3 text-sm dark:border-white/10 dark:bg-white/5'
        placeholder='جستجوی دسته یا زیردسته'
        value={q}
        onChange={(event) => setQ(event.target.value)}
      />
      {filtered.map((node) => (
        <section key={node.category} className='overflow-hidden rounded-2xl border border-gray-100 dark:border-white/5'>
          <div className='flex flex-wrap items-center justify-between gap-3 bg-gray-50 px-4 py-3 dark:bg-white/5'>
            <div>
              <h3 className='font-medium text-gray-800 dark:text-white/90'>{node.label}</h3>
              <p className='text-theme-xs text-gray-500'>{toFaDigits(node.productCount)} محصول</p>
            </div>
            <div className='flex flex-wrap items-center gap-2'>
              {statusBadge(node.status)}
              <span className='text-theme-xs text-gray-500'>بازبینی {formatJalaliIso(node.lastReviewedAt)}</span>
              <Button
                type='button'
                size='sm'
                onClick={() =>
                  void openOrCreate({
                    scopeType: 'category',
                    category: node.category,
                    guideId: node.guideId
                  })
                }
                disabled={busy === `${node.category}:`}
              >
                {node.guideId ? 'ویرایش پایه' : 'ساخت پایه دسته'}
              </Button>
            </div>
          </div>
          <div className='divide-y divide-gray-100 dark:divide-white/5'>
            {node.children.length === 0 ? (
              <p className='px-4 py-3 text-theme-sm text-gray-500'>زیردسته‌ای در کاتالوگ نیست.</p>
            ) : (
              node.children.map((child) => (
                <div key={child.subcategory} className='flex flex-wrap items-center justify-between gap-3 px-4 py-3'>
                  <div>
                    <p className='text-sm text-gray-800 dark:text-white/90'>{child.label}</p>
                    <p className='text-theme-xs text-gray-500'>
                      {toFaDigits(child.productCount)} محصول · {toFaDigits(child.overrideCount)} override محصولی
                    </p>
                    {child.duplicateRisk ? (
                      <p className='text-theme-xs text-warning-600'>مقدمه اختصاصی ندارد — ریسک محتوای تکراری</p>
                    ) : null}
                  </div>
                  <div className='flex flex-wrap items-center gap-2'>
                    {statusBadge(child.status)}
                    <span className='text-theme-xs text-gray-500'>{formatJalaliIso(child.lastReviewedAt)}</span>
                    <Button
                      type='button'
                      size='sm'
                      variant='outline'
                      disabled={busy === `${node.category}:${child.subcategory}`}
                      onClick={() =>
                        void openOrCreate({
                          scopeType: 'subcategory',
                          category: node.category,
                          subcategory: child.subcategory,
                          guideId: child.guideId
                        })
                      }
                    >
                      {child.guideId ? 'ویرایش' : 'ساخت'}
                    </Button>
                    {child.guideId ? (
                      <Link className='text-theme-sm text-brand-500' href={`/content/buy-guides/${child.guideId}`}>
                        باز کردن
                      </Link>
                    ) : null}
                  </div>
                </div>
              ))
            )}
          </div>
        </section>
      ))}
    </div>
  );
}
