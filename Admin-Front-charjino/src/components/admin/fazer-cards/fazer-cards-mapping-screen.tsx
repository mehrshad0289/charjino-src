'use client';

import { useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import * as Yup from 'yup';

import {
  cancelFazerCardsCatalogSync,
  getFazerCardsSyncStatus,
  getProduct,
  syncFazerCardsCatalog,
  updateProduct,
  updateSku
} from '@/actions/admin.actions';
import { Alert, Button, TextField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { toFaDigits } from '@/lib/fa';
import type {
  AdminProduct,
  AdminSku,
  CatalogSyncStatus,
  FazerCardsCatalogScope,
  FazerCardsStatus
} from '@/types/admin';

import { FazerCatalogAddPanel } from './fazer-catalog-add-panel';

const FALLBACK_SCOPES: FazerCardsCatalogScope[] = [
  { id: 'steam-topup', title: 'شارژ استیم', titleEn: 'Steam Top-Up (CIS)' },
  { id: 'steam-gifts', title: 'گیفت استیم', titleEn: 'Steam Gift Games' },
  { id: 'telegram', title: 'تلگرام (ستاره)', titleEn: 'Telegram stars' },
  { id: 'gamekeys', title: 'کلید بازی', titleEn: 'Game Keys' },
  { id: 'funpay', title: 'FunPay', titleEn: 'FunPay panel' },
  { id: 'manual', title: 'سرویس دستی', titleEn: 'Manual Services' },
  { id: 'giftcards', title: 'گیفت کارت', titleEn: 'Gift cards' },
  { id: 'topups', title: 'شارژ بازی', titleEn: 'Service top-up' }
];

type FazerCardsMappingScreenProps = {
  products: AdminProduct[];
  lastSync?: CatalogSyncStatus;
  status?: FazerCardsStatus;
  error?: string;
};

export function FazerCardsMappingScreen({ products, lastSync, status, error }: FazerCardsMappingScreenProps) {
  const router = useRouter();
  const stopRequested = useRef(false);
  const [busy, setBusy] = useState<string | null>(null);
  const [message, setMessage] = useToastMessage(
    error ? { variant: /اتصال/.test(error) ? 'warning' : 'error', text: error } : null
  );
  const [sync, setSync] = useState<CatalogSyncStatus | null>(
    lastSync && lastSync.status !== 'idle' ? lastSync : null
  );
  const [detail, setDetail] = useState<AdminProduct | null>(null);

  const form = useFormik({
    enableReinitialize: true,
    initialValues: {
      productId: '',
      fazercardsCategoryId: '',
      skuId: '',
      skuCategoryId: '',
      skuOfferId: ''
    },
    validationSchema: Yup.object({
      fazercardsCategoryId: Yup.string().trim(),
      skuCategoryId: Yup.string().trim(),
      skuOfferId: Yup.string().trim()
    }),
    onSubmit: async () => undefined
  });

  const scopes = status?.catalogScopes?.length ? status.catalogScopes : FALLBACK_SCOPES;
  const live = sync ?? lastSync;
  const running = live?.status === 'running';

  function clearSyncBusy() {
    setBusy((current) => (current?.startsWith('sync:') ? null : current));
  }

  async function runSync(family?: string) {
    stopRequested.current = false;
    setBusy(`sync:${family || 'resume'}`);
    setMessage({
      variant: 'warning',
      text: 'همگام تکه‌تکهٔ همان دسته است. قطع اتصال را با «لغو همگام» متوقف کن. صفحه را نبند.'
    });
    try {
      for (let step = 0; ; step++) {
        if (stopRequested.current) {
          return;
        }
        const result = await syncFazerCardsCatalog(family);
        if (stopRequested.current) {
          return;
        }
        if (!result.ok) {
          const transient = /اتصال|timeout|UNREACHABLE|503/i.test(result.message);
          if (transient) {
            setMessage({
              variant: 'warning',
              text: 'اتصال لحظه‌ای قطع شد؛ چند ثانیه بعد ادامه می‌دهیم. می‌توانی لغو کنی.'
            });
            await sleep(4000, () => stopRequested.current);
            const paused = await getFazerCardsSyncStatus();
            if (paused.ok) {
              setSync(paused.data);
              if (paused.data.status === 'cancelled') {
                setMessage({ variant: 'warning', text: paused.data.message || 'همگام لغو شد.' });
                return;
              }
            }
            continue;
          }
          setMessage({ variant: 'error', text: result.message });
          return;
        }
        setSync(result.data);
        if (result.data.status === 'cancelled') {
          setMessage({ variant: 'warning', text: result.data.message || 'همگام لغو شد.' });
          router.refresh();
          return;
        }
        if (/اول همگام/.test(result.data.message || '')) {
          setMessage({ variant: 'warning', text: result.data.message || 'اول همگام جاری تمام شود یا لغو کن.' });
          return;
        }
        if (result.data.status === 'completed') {
          const label = result.data.scopeLabel || 'این دسته';
          setMessage({
            variant: 'success',
            text: `«${label}» همگام شد: ${toFaDigits(result.data.products ?? 0)} محصول، ${toFaDigits(result.data.skus ?? 0)} بسته.`
          });
          router.refresh();
          return;
        }
        if (result.data.status === 'failed') {
          setMessage({ variant: 'error', text: result.data.message || 'همگام شکست خورد.' });
          return;
        }
        const waitSec = result.data.retryAfterSec ?? 0;
        if (waitSec > 0 || /قطع|صبر|محدودیت/.test(result.data.message || '')) {
          setMessage({
            variant: 'warning',
            text:
              waitSec > 0
                ? `محدودیت نرخ فهرست گیفت استیم. حدود ${toFaDigits(waitSec)} ثانیه دیگر ادامه می‌دهیم. صفحه را نبند و لغو نزن.`
                : result.data.message || 'صبر کن؛ محدودیت نرخ هنوز فعال است.'
          });
          await sleep(Math.min(Math.max(waitSec, 4) * 1000, 180_000), () => stopRequested.current);
        } else {
          setMessage(
            {
              variant: 'warning',
              text: `${result.data.scopeLabel ? `«${result.data.scopeLabel}» · ` : ''}${result.data.phaseLabel || 'همگام'} · ${toFaDigits(result.data.processedItems ?? 0)}${result.data.catalogTotal ? ` از ${toFaDigits(result.data.catalogTotal)}` : ''} مورد`
            },
            { toast: false }
          );
        }
      }
      setMessage({ variant: 'warning', text: 'هنوز تمام نشده. «ادامه همگام» یا «لغو همگام» را بزن.' });
    } finally {
      clearSyncBusy();
    }
  }

  async function stopSync() {
    stopRequested.current = true;
    setBusy('stop');
    const result = await cancelFazerCardsCatalogSync();
    setBusy(null);
    if (result.ok) {
      setSync(result.data);
      setMessage({ variant: 'warning', text: result.data.message || 'همگام لغو شد.' });
    } else {
      setMessage({
        variant: 'warning',
        text: result.message || 'لغو ثبت شد. اگر تکهٔ جاری به تأمین‌کننده وصل است چند ثانیه صبر کن.'
      });
    }
    router.refresh();
  }

  async function applyProduct(item: AdminProduct) {
    setDetail(item);
    const firstSku = item.skus?.[0];
    void form.setValues({
      productId: String(item.id),
      fazercardsCategoryId: item.fazercardsCategoryId || '',
      skuId: firstSku ? String(firstSku.id) : '',
      skuCategoryId: firstSku?.fazercardsCategoryId || '',
      skuOfferId: firstSku?.fazercardsOfferId || ''
    });
  }

  async function loadProduct(id: number, silent = false) {
    if (!silent) setBusy('load');
    const result = await getProduct(id);
    if (!silent) setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    await applyProduct(result.item);
  }

  async function saveProductMap() {
    const id = Number(form.values.productId);
    if (!id) return;
    setBusy('saveProduct');
    const result = await updateProduct(id, { fazercardsCategoryId: form.values.fazercardsCategoryId || null });
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'دستهٔ محصول ذخیره شد.' });
    await loadProduct(id, true);
  }

  async function saveSkuMap() {
    const id = Number(form.values.skuId);
    if (!id) return;
    setBusy('saveSku');
    const result = await updateSku(id, {
      fazercardsCategoryId: form.values.skuCategoryId || null,
      fazercardsOfferId: form.values.skuOfferId || null
    });
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'نگاشت بسته ذخیره شد.' });
    if (form.values.productId) await loadProduct(Number(form.values.productId), true);
  }

  function pickSku(sku: AdminSku) {
    void form.setFieldValue('skuId', String(sku.id));
    void form.setFieldValue('skuCategoryId', sku.fazercardsCategoryId || '');
    void form.setFieldValue('skuOfferId', sku.fazercardsOfferId || '');
  }

  const mapped = products.filter((item) => item.fazercardsCategoryId && productMatchesScope(item, live?.scope));
  const mappedPreview = mapped.slice(0, 12);

  return (
    <div className='space-y-6'>
      {message ? (
        <Alert
          variant={message.variant}
          title={message.variant === 'success' ? 'نگاشت' : message.variant === 'warning' ? 'همگام' : 'خطا'}
          message={message.text}
        />
      ) : null}
      {status?.accountError ? (
        <Alert variant='warning' title='حساب FazerCards' message={status.accountError} />
      ) : null}

      <div className='space-y-3'>
        <p className='text-theme-sm text-gray-500'>
          هر دکمه فقط همان دسته را همگام می‌کند. برای افزودن موردی مثل FC 27 از کادر جستجو پایین استفاده کن؛ دسته را
          گیفت استیم یا کلید بازی بگذار. گیفت استیم کل ۱۷۷ هزار را وارد نمی‌کند. تلگرام و شارژ استیم نقل‌قول کامل است.
          FunPay محصول فروشگاه ندارد. همزمان فقط یک همگام اجرا می‌شود. اگر گیر کرد «لغو همگام» را بزن.
        </p>
        <div className='flex flex-wrap gap-2'>
          {scopes.map((scope) => {
            const active = busy === `sync:${scope.id}` || (Boolean(busy?.startsWith('sync:')) && live?.scope === scope.id);
            return (
              <Button
                key={scope.id}
                type='button'
                size='sm'
                variant={active ? 'primary' : 'outline'}
                loading={busy === `sync:${scope.id}`}
                disabled={busy !== null || running || status?.configured === false}
                onClick={() => void runSync(scope.id)}
              >
                {scope.title}
              </Button>
            );
          })}
          <Button
            type='button'
            size='sm'
            variant='outline'
            loading={busy === 'sync:all'}
            disabled={busy !== null || running || status?.configured === false}
            onClick={() => void runSync('all')}
          >
            همهٔ دسته‌ها
          </Button>
          {running && (busy === null || busy === 'sync:resume') ? (
            <Button
              type='button'
              size='sm'
              loading={busy === 'sync:resume'}
              disabled={status?.configured === false || (busy !== null && busy !== 'sync:resume')}
              onClick={() => void runSync()}
            >
              ادامه همگام
            </Button>
          ) : null}
          {busy !== null || running ? (
            <Button type='button' size='sm' variant='outline' loading={busy === 'stop'} onClick={() => void stopSync()}>
              لغو همگام
            </Button>
          ) : null}
        </div>
      </div>

      <FazerCatalogAddPanel configured={status?.configured === true} family='steam-gifts' />

      {(sync ?? lastSync) && (sync ?? lastSync)?.status !== 'idle' ? (
        <p className='text-theme-sm text-gray-500'>
          آخرین sync
          {(sync ?? lastSync)?.scopeLabel ? ` «${(sync ?? lastSync)?.scopeLabel}»` : ''}: {(sync ?? lastSync)?.status} ·{' '}
          {toFaDigits((sync ?? lastSync)?.products ?? 0)} محصول
        </p>
      ) : null}
      {sync ? (
        <p className='text-theme-sm text-gray-500'>
          {sync.scopeLabel ? `«${sync.scopeLabel}» · ` : ''}
          {sync.phaseLabel ? `${sync.phaseLabel} · ` : ''}
          {toFaDigits(sync.products ?? 0)} محصول / {toFaDigits(sync.skus ?? 0)} بسته
          {sync.processedItems != null ? ` · ${toFaDigits(sync.processedItems)} مورد` : ''}
          {sync.catalogTotal ? ` از ${toFaDigits(sync.catalogTotal)}` : ''}
        </p>
      ) : null}

      {mapped.length === 0 ? (
        <p className='text-theme-sm text-gray-500'>
          {running
            ? `هنوز محصولی از «${live?.scopeLabel || 'این دسته'}» نیامده. لیست شارژ بازی مال همگام قبلی است و اینجا نشان داده نمی‌شود.`
            : 'هنوز محصولی از این دسته نیست. همگام همان دسته را بزن.'}
        </p>
      ) : (
        <div className='space-y-2'>
          <p className='text-theme-sm text-gray-500'>
            در این صفحه نمونه است. همه را در{' '}
            <a href='/catalog/products?source=supplier' className='text-brand-500'>
              محصولات تأمین‌کننده
            </a>{' '}
            ببین.
          </p>
          <ul className='space-y-2'>
            {mappedPreview.map((item) => (
              <li key={item.id}>
                <button
                  type='button'
                  className='text-theme-sm text-brand-500'
                  onClick={() => void loadProduct(item.id)}
                >
                  {item.title} · {item.handle} · {item.fazercardsCategoryId}
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}

      <div className='space-y-3 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>اتصال دستی</h3>
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField
              label='شناسه محصول چارژینو'
              placeholder='عدد id مثل ۱۴۴'
              value={form.values.productId}
              onChange={(value) => void form.setFieldValue('productId', value)}
            />
            <TextField
              label='fazercards_category_id محصول'
              placeholder='مثلاً pubg_mobile_auto'
              value={form.values.fazercardsCategoryId}
              onChange={(value) => void form.setFieldValue('fazercardsCategoryId', value)}
            />
          </div>
          <div className='flex flex-wrap gap-2'>
            <Button
              type='button'
              variant='outline'
              loading={busy === 'load'}
              disabled={busy !== null || !form.values.productId}
              onClick={() => void loadProduct(Number(form.values.productId))}
            >
              خواندن محصول
            </Button>
            <Button type='button' loading={busy === 'saveProduct'} disabled={busy !== null || !form.values.productId} onClick={() => void saveProductMap()}>
              ذخیره دسته محصول
            </Button>
          </div>
        </div>

      {detail ? (
        <div className='space-y-3 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{detail.title}</h3>
          <div className='flex flex-wrap gap-2'>
            {(detail.skus ?? []).map((sku) => (
              <Button key={sku.id} type='button' variant='outline' onClick={() => pickSku(sku)}>
                {sku.name} {sku.fazercardsOfferId ? `· ${sku.fazercardsOfferId}` : ''}
              </Button>
            ))}
          </div>
          {(detail.skus ?? []).length === 0 ? (
            <p className='text-theme-sm text-gray-500'>بسته‌ای برای این محصول نیست.</p>
          ) : null}
          <div className='grid gap-3 sm:grid-cols-2'>
            <TextField label='sku id' value={form.values.skuId} onChange={(value) => void form.setFieldValue('skuId', value)} />
            <TextField
              label='category_id بسته'
              value={form.values.skuCategoryId}
              onChange={(value) => void form.setFieldValue('skuCategoryId', value)}
            />
            <TextField
              label='offer_id / card_id / key_id'
              value={form.values.skuOfferId}
              onChange={(value) => void form.setFieldValue('skuOfferId', value)}
            />
          </div>
          <Button type='button' loading={busy === 'saveSku'} disabled={busy !== null || !form.values.skuId} onClick={() => void saveSkuMap()}>
            ذخیره نگاشت بسته
          </Button>
        </div>
      ) : null}
    </div>
  );
}

function sleep(ms: number, shouldStop?: () => boolean) {
  const step = 250;
  const until = Date.now() + ms;
  return new Promise<void>((resolve) => {
    const tick = () => {
      if (shouldStop?.() || Date.now() >= until) {
        resolve();
        return;
      }
      setTimeout(tick, Math.min(step, until - Date.now()));
    };
    setTimeout(tick, Math.min(step, ms));
  });
}

function productMatchesScope(product: AdminProduct, scope?: string | null) {
  const id = product.fazercardsCategoryId || '';
  const type = product.fulfillmentType || '';
  if (!scope || scope === 'all') {
    return Boolean(id);
  }
  switch (scope) {
    case 'steam-gifts':
      return type === 'steam-gift' || id.startsWith('steam-gift:');
    case 'steam-topup':
      return type === 'steam-topup' || id.startsWith('steam-topup:');
    case 'telegram':
      return type.startsWith('telegram') || id.startsWith('telegram-');
    case 'funpay':
      return type === 'funpay' || id.startsWith('funpay:');
    case 'gamekeys':
      return type === 'gamekey' || id.startsWith('gamekey:');
    case 'manual':
      return type === 'manual' || id.startsWith('manual:');
    case 'giftcards':
      return type === 'giftcard' || id.startsWith('giftcard:');
    case 'topups':
      return type === 'topup' || (id !== '' && !id.includes(':'));
    default:
      return Boolean(id);
  }
}
