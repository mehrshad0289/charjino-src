'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

import { refreshFx, savePricing } from '@/actions/admin.actions';
import { Button, SelectField, TextField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { formatFaDate, formatToman } from '@/lib/fa';
import type { PricingSnapshot } from '@/types/admin';

type PricingScreenProps = {
  snapshot?: PricingSnapshot;
  error?: string;
};

export function PricingScreen({ snapshot, error }: PricingScreenProps) {
  const router = useRouter();
  const [usdTomanRate, setUsdTomanRate] = useState(snapshot?.usdTomanRate ?? '');
  const [marginPercent, setMarginPercent] = useState(snapshot?.marginPercent ?? '5');
  const [fxMode, setFxMode] = useState(snapshot?.fxMode ?? 'auto');
  const [busy, setBusy] = useState<string | null>(null);
  const [, setMessage] = useToastMessage(error ? { variant: 'error', text: error } : null);
  const current = snapshot;

  async function save() {
    setBusy('save');
    const result = await savePricing(
      fxMode === 'manual'
        ? { usdTomanRate, marginPercent, fxMode }
        : { marginPercent, fxMode }
    );
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setUsdTomanRate(result.data.usdTomanRate);
    setMarginPercent(result.data.marginPercent);
    setFxMode(result.data.fxMode);
    setMessage({ variant: 'success', text: 'نرخ و سود ذخیره شد و قیمت بسته‌ها به‌روز شد.' });
    router.refresh();
  }

  async function refresh() {
    setBusy('refresh');
    const result = await refreshFx();
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setUsdTomanRate(result.data.usdTomanRate);
    setMarginPercent(result.data.marginPercent);
    setFxMode(result.data.fxMode);
    setMessage({
      variant: 'success',
      text: result.data.reason || 'نرخ دلار از منبع بازار آزاد به‌روز شد.'
    });
    router.refresh();
  }

  return (
    <div className='space-y-6'>
      <div className='grid gap-4 sm:grid-cols-2'>
        <div className='rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <p className='text-theme-xs text-gray-500'>نرخ فعلی</p>
          <p className='mt-1 text-lg font-semibold text-gray-800 dark:text-white/90'>
            {formatToman(current?.usdTomanRate ?? 0)}
          </p>
          <p className='text-theme-xs mt-1 text-gray-500'>
            منبع: {current?.fxSource || 'تنظیم نشده'} · {formatFaDate(current?.fxUpdatedAt)}
          </p>
        </div>
        <div className='rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <p className='text-theme-xs text-gray-500'>نمونه فروش ۱ دلار</p>
          <p className='mt-1 text-lg font-semibold text-gray-800 dark:text-white/90'>
            {formatToman(current?.sample.toman ?? 0)}
          </p>
          <p className='text-theme-xs mt-1 text-gray-500'>
            سود فعلی {current?.marginPercent ?? '5'}٪ روی قیمت دلار
          </p>
        </div>
      </div>
      <SelectField
        label='حالت نرخ'
        value={fxMode}
          onChange={(value) => setFxMode(value === 'manual' ? 'manual' : 'auto')}
        options={[
          { value: 'auto', label: 'خودکار از بازار آزاد' },
          { value: 'manual', label: 'دستی' }
        ]}
      />
      <TextField
        label='نرخ دلار به تومان'
        type='number'
        value={usdTomanRate}
        onChange={setUsdTomanRate}
        previewState={fxMode === 'auto' ? 'disabled' : 'default'}
        hint={
          fxMode === 'auto'
            ? 'در حالت خودکار این فیلد قفل است. برای عدد دستی، حالت را «دستی» کن یا فقط «گرفتن نرخ روز» را بزن.'
            : 'فقط در حالت دستی ذخیره می‌شود.'
        }
        placeholder='100000'
      />
      <TextField
        label='حاشیه سود درصد'
        type='number'
        value={marginPercent}
        onChange={setMarginPercent}
        hint='سود پیش‌فرض همهٔ محصولات. در صفحهٔ هر محصول می‌توانی برای همان محصول یا فقط بعضی بسته‌ها درصد دیگری بگذاری.'
        placeholder='5'
      />
      <div className='flex flex-wrap gap-3'>
        <Button type='button' loading={busy === 'save'} disabled={busy !== null} onClick={() => void save()}>
          ذخیره نرخ و سود
        </Button>
        <Button type='button' variant='outline' loading={busy === 'refresh'} disabled={busy !== null} onClick={() => void refresh()}>
          گرفتن نرخ روز
        </Button>
      </div>
      <p className='text-theme-xs text-gray-500'>
        دکمهٔ «گرفتن نرخ روز» حالت نرخ را خودکار (auto) می‌کند. اگر می‌خواهی نرخ دستی بماند، فقط ذخیره کن و این دکمه را نزن.
      </p>
    </div>
  );
}
