'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';
import { createBuyGuide, listBuyGuides, resolveBuyGuidePreview } from '@/actions/buy-guide.actions';
import { Button } from '@/components/ui';
import { notify } from '@/lib/toast';
import type { AdminBuyGuide } from '@/types/buy-guide';
import type { AdminProduct } from '@/types/admin';

const SOURCE_LABEL: Record<string, string> = {
  product: 'این محصول',
  subcategory: 'زیردسته',
  category: 'دسته'
};

export function ProductGuideCard({ product }: { product: AdminProduct }) {
  const [guide, setGuide] = useState<AdminBuyGuide | null>(null);
  const [sources, setSources] = useState<{ type: string; source: string }[]>([]);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let cancelled = false;
    void listBuyGuides({ productId: product.id }).then((result) => {
      if (cancelled || !result.ok) return;
      setGuide(result.items[0] ?? null);
    });
    void resolveBuyGuidePreview(product.id).then((result) => {
      if (cancelled || !result.ok) return;
      const sections = Array.isArray(result.data.sections) ? result.data.sections : [];
      setSources(
        sections.map((section) => {
          const row = section as { type?: string; source?: string };
          return { type: row.type ?? '', source: row.source ?? '' };
        })
      );
    });
    return () => {
      cancelled = true;
    };
  }, [product.id]);

  async function createOverride() {
    setBusy(true);
    const result = await createBuyGuide({
      scopeType: 'product',
      productId: product.id,
      status: 'draft',
      title: product.title
    });
    setBusy(false);
    if (!result.ok) {
      notify.error(result.message);
      return;
    }
    window.location.href = `/content/buy-guides/${result.item.id}`;
  }

  return (
    <div className='space-y-2 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
      <h3 className='text-sm font-medium text-gray-800 dark:text-white/90'>راهنمای اختصاصی محصول</h3>
      <p className='text-theme-sm text-gray-500'>
        اگر خالی بماند، راهنمای زیردسته و دسته نمایش داده می‌شود. فقط برای استثنا override بساز.
      </p>
      {sources.length > 0 ? (
        <ul className='space-y-1 text-theme-xs text-gray-500'>
          {sources.map((item) => (
            <li key={item.type}>
              {item.type}: {SOURCE_LABEL[item.source] ?? item.source}
            </li>
          ))}
        </ul>
      ) : null}
      {guide ? (
        <Link className='text-theme-sm text-brand-500' href={`/content/buy-guides/${guide.id}`}>
          ویرایش override ({guide.status === 'published' ? 'منتشر' : 'پیش‌نویس'})
        </Link>
      ) : (
        <Button type='button' size='sm' variant='outline' disabled={busy} onClick={() => void createOverride()}>
          ساخت راهنمای این محصول
        </Button>
      )}
    </div>
  );
}
