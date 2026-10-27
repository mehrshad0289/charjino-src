'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';

import { importFazerCatalog, searchFazerCatalog } from '@/actions/admin.actions';
import { Button, SelectField, TextField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { toFaDigits } from '@/lib/fa';
import type { FazerCatalogSearchHit } from '@/types/admin';

export const FAZER_SHOP_FAMILIES = [
  { value: 'steam-gifts', label: 'گیفت استیم' },
  { value: 'gamekeys', label: 'کلید بازی' },
  { value: 'topups', label: 'شارژ بازی' },
  { value: 'giftcards', label: 'گیفت کارت' },
  { value: 'manual', label: 'سرویس دستی' }
] as const;

const HINT: Record<string, string> = {
  'steam-gifts': 'مثلاً FC 27 یا Counter-Strike. اگر بازی گیفت‌شدنی باشد به فروشگاه اضافه می‌شود.',
  gamekeys: 'مثلاً FC 27. نسخهٔ کلید بازی را اینجا اضافه کن.',
  topups: 'مثلاً PUBG. شارژ با شناسه بازیکن.',
  giftcards: 'مثلاً Steam یا Xbox.',
  manual: 'نام سرویس دستی نزد Fazer.'
};

type FazerCatalogAddPanelProps = {
  family?: string;
  lockFamily?: boolean;
  configured?: boolean;
};

export function FazerCatalogAddPanel({
  family = 'steam-gifts',
  lockFamily = false,
  configured = false
}: FazerCatalogAddPanelProps) {
  const router = useRouter();
  const [selected, setSelected] = useState(family);
  const [query, setQuery] = useState('');
  const [hits, setHits] = useState<FazerCatalogSearchHit[]>([]);
  const [shopMax, setShopMax] = useState<number | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [, setMessage] = useToastMessage();
  const current = lockFamily ? family : selected;
  const label = FAZER_SHOP_FAMILIES.find((row) => row.value === current)?.label || current;

  async function runSearch() {
    setBusy('search');
    const result = await searchFazerCatalog(current, query);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setHits(result.items);
    setShopMax(result.shopMax);
    if (result.items.length === 0) {
      setMessage({
        variant: 'warning',
        text:
          current === 'steam-gifts'
            ? 'در گیفت استیم پیدا نشد. اگر نسخهٔ کلید است، صفحهٔ کلید بازی را بزن. سفارش زنده زده نمی‌شود.'
            : 'در این دسته پیدا نشد. دستهٔ دیگر را هم امتحان کن. سفارش زنده زده نمی‌شود.'
      });
      return;
    }
    setMessage(null);
  }

  async function addItem(remoteId: string) {
    setBusy(`add:${remoteId}`);
    const result = await importFazerCatalog(current, remoteId);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: `«${result.item.title}» به فروشگاه اضافه شد.` });
    setHits((rows) =>
      rows.map((row) =>
        row.remoteId === remoteId ? { ...row, inShop: true, visible: true, productId: result.item.id } : row
      )
    );
    router.refresh();
  }

  return (
    <div className='space-y-3 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
      <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>افزودن به فروشگاه چارژینو</h3>
      <p className='text-theme-sm text-gray-500'>
        نام را بزن و «افزودن به چارژینو» را بزن. همگام کل فهرست را وارد نمی‌کند. سفارش زنده زده نمی‌شود.
        {shopMax ? ` سقف همگام این دسته حدود ${toFaDigits(shopMax)} مورد است.` : ''}
      </p>
      <form
        className={`grid gap-3 sm:items-end ${lockFamily ? 'sm:grid-cols-[1fr_auto]' : 'sm:grid-cols-[minmax(0,14rem)_1fr_auto]'}`}
        onSubmit={(event) => {
          event.preventDefault();
          void runSearch();
        }}
      >
        {lockFamily ? null : (
          <SelectField
            label='دسته'
            placeholder='انتخاب دسته'
            options={[...FAZER_SHOP_FAMILIES]}
            value={selected}
            onChange={(value) => {
              setSelected(value);
              setHits([]);
              setMessage(null);
            }}
          />
        )}
        <TextField
          label={lockFamily ? `جستجو در ${label}` : 'نام یا شناسه'}
          placeholder={HINT[current] || 'نام محصول'}
          value={query}
          onChange={setQuery}
        />
        <Button type='submit' loading={busy === 'search'} disabled={busy !== null || !configured}>
          جستجو در Fazer
        </Button>
      </form>
      {hits.length > 0 ? (
        <ul className='space-y-2'>
          {hits.map((hit) => (
            <li key={`${hit.family}:${hit.remoteId}`} className='flex flex-wrap items-center justify-between gap-2'>
              <span className='text-theme-sm text-gray-800 dark:text-white/90'>
                {hit.name || 'بدون نام'} · {hit.remoteId}
                {hit.visible ? ' · در فروشگاه' : hit.inShop ? ' · مخفی' : ''}
              </span>
              <Button
                type='button'
                size='sm'
                variant='outline'
                loading={busy === `add:${hit.remoteId}`}
                disabled={busy !== null || hit.visible}
                onClick={() => void addItem(hit.remoteId)}
              >
                {hit.visible ? 'اضافه شده' : 'افزودن به چارژینو'}
              </Button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
