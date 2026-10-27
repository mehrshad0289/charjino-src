'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { useFormik } from 'formik';
import * as Yup from 'yup';

import {
  clearFazerCardsKey,
  saveFazerCardsKey,
  saveFazerCardsSettings,
  saveSteamGiftBlocklist
} from '@/actions/admin.actions';
import { Badge, Button, Checkbox, PasswordField, TextareaField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { formatFaDate } from '@/lib/fa';
import type { FazerCardsAuditRow, FazerCardsStatus } from '@/types/admin';

import { FazerCardsConfirmModal } from './fazer-cards-confirm-modal';
import { useModal } from '@/hooks/useModal';

type FazerCardsSettingsScreenProps = {
  status?: FazerCardsStatus;
  audits?: FazerCardsAuditRow[];
  error?: string;
};

export function FazerCardsSettingsScreen({ status, audits = [], error }: FazerCardsSettingsScreenProps) {
  const router = useRouter();
  const [busy, setBusy] = useState<string | null>(null);
  const [liveOn, setLiveOn] = useState(Boolean(status?.liveOrderEnabled));
  const [, setMessage] = useToastMessage(error ? { variant: 'error', text: error } : null);
  const clearModal = useModal();
  const liveModal = useModal();

  const blocklistForm = useFormik({
    enableReinitialize: true,
    initialValues: { steamGiftBlockedWords: status?.steamGiftBlockedWords ?? '' },
    validationSchema: Yup.object({
      steamGiftBlockedWords: Yup.string().trim().required('حداقل یک کلمه لازم است').max(4000, 'متن خیلی بلند است')
    }),
    onSubmit: async (values) => {
      setBusy('blocklist');
      const result = await saveSteamGiftBlocklist(values.steamGiftBlockedWords);
      setBusy(null);
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        return;
      }
      setMessage({
        variant: 'success',
        text: 'فهرست کلمات ممنوعه ذخیره شد. عنوان‌های فعلیِ مطابق فهرست از فروشگاه خارج می‌شوند.'
      });
      router.refresh();
    }
  });

  const keyForm = useFormik({
    initialValues: { apiKey: '' },
    validationSchema: Yup.object({
      apiKey: Yup.string().trim().min(8, 'کلید خیلی کوتاه است').required('کلید لازم است')
    }),
    onSubmit: async (values, helpers) => {
      setBusy('save');
      const result = await saveFazerCardsKey(values.apiKey.trim());
      setBusy(null);
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        return;
      }
      helpers.resetForm();
      setMessage({
        variant: 'success',
        text: `کلید ذخیره شد. چهار رقم آخر: ${result.data.keyLast4 ?? '—'}. مقدار کامل نمایش داده نمی‌شود.`
      });
      router.refresh();
    }
  });

  async function removeKey() {
    setBusy('clear');
    const result = await clearFazerCardsKey();
    setBusy(null);
    clearModal.closeModal();
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'کلید از تنظیمات حذف شد.' });
    router.refresh();
  }

  async function persistLive() {
    setBusy('live');
    const result = await saveFazerCardsSettings(liveOn);
    setBusy(null);
    liveModal.closeModal();
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({
      variant: 'success',
      text: result.data.liveOrderEnabled ? 'سفارش زنده FazerCards باز شد.' : 'سفارش زنده FazerCards قفل شد.'
    });
    router.refresh();
  }

  return (
    <div className='space-y-6'>
      <div className='rounded-xl border border-gray-100 p-4 dark:border-white/5'>
        <p className='text-theme-xs text-gray-500'>وضعیت کلید</p>
        <div className='mt-2'>
          <Badge color={status?.configured ? 'success' : 'error'}>
            {status?.configured ? `****${status.keyLast4 || ''}` : 'کلید ذخیره نشده'}
          </Badge>
        </div>
        <p className='text-theme-xs mt-2 text-gray-500'>Base: {status?.host || 'api.fzr.cards'} · فقط از env بک‌اند</p>
      </div>

      <form className='space-y-4' onSubmit={keyForm.handleSubmit}>
        <PasswordField
          id='fc-api-key'
          name='apiKey'
          label='کلید API (X-API-Key)'
          placeholder='fc_…'
          autoComplete='off'
          value={keyForm.values.apiKey}
          hint={
            keyForm.touched.apiKey && keyForm.errors.apiKey
              ? keyForm.errors.apiKey
              : 'مقدار کامل بعد از ذخیره هرگز برنمی‌گردد.'
          }
          previewState={keyForm.touched.apiKey && keyForm.errors.apiKey ? 'error' : 'default'}
          onBlur={() => keyForm.setFieldTouched('apiKey', true)}
          onChange={(value) => void keyForm.setFieldValue('apiKey', value)}
        />
        <div className='flex flex-wrap gap-3'>
          <Button type='submit' loading={busy === 'save'} disabled={busy !== null}>
            ذخیره کلید
          </Button>
          <Button type='button' variant='outline' disabled={busy !== null || !status?.configured} onClick={clearModal.openModal}>
            حذف کلید
          </Button>
        </div>
      </form>

      <form className='space-y-3 rounded-xl border border-gray-100 p-4 dark:border-white/5' onSubmit={blocklistForm.handleSubmit}>
        <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>کلمات ممنوعه گیفت استیم</h3>
        <p className='text-theme-xs text-gray-500'>
          هر خط یک عبارت. عنوان‌های مطابق این فهرست هنگام همگام وارد فروشگاه نمی‌شوند و آدرس فعلی‌شان به دسته گیفت
          استیم می‌رود.
        </p>
        <TextareaField
          label='فهرست کلمات'
          rows={8}
          value={blocklistForm.values.steamGiftBlockedWords}
          hint={
            blocklistForm.touched.steamGiftBlockedWords && blocklistForm.errors.steamGiftBlockedWords
              ? blocklistForm.errors.steamGiftBlockedWords
              : 'نمونه: femboy ، futa ، nsfw'
          }
          previewState={
            blocklistForm.touched.steamGiftBlockedWords && blocklistForm.errors.steamGiftBlockedWords ? 'error' : 'default'
          }
          onChange={(value) => void blocklistForm.setFieldValue('steamGiftBlockedWords', value)}
        />
        <Button type='submit' loading={busy === 'blocklist'} disabled={busy !== null}>
          ذخیره فهرست ممنوعه
        </Button>
      </form>

      <div className='space-y-3 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
        <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>سفارش زنده FazerCards</h3>
        <p className='text-theme-xs text-gray-500'>
          این پرچم جدا از ALLOW_LIVE_ORDER فروشگاه Shop2 است. با روشن کردن، سفارش از والت USD کم می‌شود.
        </p>
        <Checkbox label='FAZERCARDS_ALLOW_LIVE_ORDER را از پنل روشن کن' checked={liveOn} onChange={setLiveOn} />
        <Button type='button' loading={busy === 'live'} disabled={busy !== null} onClick={liveModal.openModal}>
          ذخیره پرچم زنده
        </Button>
      </div>

      {audits.length > 0 ? (
        <div className='space-y-2'>
          <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>گزارش عملیات حساس</h3>
          <ul className='space-y-1 text-theme-xs text-gray-500'>
            {audits.map((row) => (
              <li key={row.id}>
                {formatFaDate(row.createdAt)} · {row.method} {row.path} · {row.action} · {row.ok ? 'ok' : 'fail'}
              </li>
            ))}
          </ul>
        </div>
      ) : (
        <p className='text-theme-sm text-gray-500'>هنوز عملیات حساسی ثبت نشده است.</p>
      )}

      <FazerCardsConfirmModal
        isOpen={clearModal.isOpen}
        title='حذف کلید'
        message='کلید رمزشده از تنظیمات بک‌اند پاک می‌شود. فروشگاه تا ذخیره دوباره نمی‌تواند FazerCards را صدا بزند.'
        busy={busy}
        onClose={clearModal.closeModal}
        onConfirm={() => void removeKey()}
      />
      <FazerCardsConfirmModal
        isOpen={liveModal.isOpen}
        title='تغییر سفارش زنده'
        message={
          liveOn
            ? 'سفارش زنده از والت دلاری FazerCards کم می‌کند. مطمئنی؟'
            : 'سفارش زنده قفل می‌شود و فقط dry-run می‌ماند.'
        }
        busy={busy}
        onClose={liveModal.closeModal}
        onConfirm={() => void persistLive()}
      />
    </div>
  );
}
