'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

import { updateGroup } from '@/actions/admin.actions';
import { PurchaseGuideEditor } from '@/components/admin/purchase-guide-editor';
import { Alert, Button } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { normalizePurchaseGuideItems, type PurchaseGuideItem } from '@/lib/purchase-guide';
import type { AdminGroup } from '@/types/admin';

type PurchaseGuidesScreenProps = {
  groups: AdminGroup[];
  error?: string;
};

function draftsFrom(groups: AdminGroup[]) {
  return Object.fromEntries(
    groups.map((group) => [group.id, normalizePurchaseGuideItems(group.purchaseGuideItems, group.purchaseGuide)]),
  );
}

export function PurchaseGuidesScreen({ groups, error }: PurchaseGuidesScreenProps) {
  const router = useRouter();
  const [busyId, setBusyId] = useState<number | null>(null);
  const [drafts, setDrafts] = useState<Record<number, PurchaseGuideItem[]>>(() => draftsFrom(groups));
  const [, setMessage] = useToastMessage();

  useEffect(() => {
    setDrafts(draftsFrom(groups));
  }, [groups]);

  async function save(group: AdminGroup) {
    setBusyId(group.id);
    const result = await updateGroup(group.id, { purchaseGuideItems: drafts[group.id] ?? [] });
    setBusyId(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: `راهنمای «${group.title}» ذخیره شد.` });
    router.refresh();
  }

  if (error) {
    return <Alert variant='error' title='محتوا خوانده نشد' message={error} />;
  }

  if (groups.length === 0) {
    return <Alert variant='info' title='دسته‌ای نیست' message='اول یک دسته در کاتالوگ بساز.' />;
  }

  return (
    <div className='space-y-6'>
      {groups.map((group) => (
        <article key={group.id} className='space-y-3 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <div>
            <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{group.title}</h3>
            <p className='text-theme-xs mt-1 text-gray-500' dir='ltr'>
              {group.slug}
            </p>
          </div>
          <PurchaseGuideEditor
            items={drafts[group.id] ?? []}
            onChange={(items) => setDrafts((current) => ({ ...current, [group.id]: items }))}
          />
          <Button type='button' loading={busyId === group.id} disabled={busyId !== null} onClick={() => void save(group)}>
            ذخیره این دسته
          </Button>
        </article>
      ))}
    </div>
  );
}
