'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { updateSearchTag, type AdminSearchTag } from '@/actions/admin.actions';
import { Button, SelectField, TextField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';

export function SearchTagsScreen({ items }: { items: AdminSearchTag[] }) {
  const router = useRouter();
  const [busy, setBusy] = useState<number | null>(null);
  const [, setMessage] = useToastMessage(null);
  const [draft, setDraft] = useState<Record<number, { kind: string; aliases: string; sort_order: string }>>({});

  function rowDraft(tag: AdminSearchTag) {
    return (
      draft[tag.id] ?? {
        kind: tag.kind || 'tag',
        aliases: (tag.aliases ?? []).join('، '),
        sort_order: String(tag.sort_order ?? 0),
      }
    );
  }

  async function save(tag: AdminSearchTag) {
    const values = rowDraft(tag);
    setBusy(tag.id);
    const result = await updateSearchTag(tag.id, {
      kind: values.kind,
      is_active: tag.is_active !== false,
      sort_order: values.sort_order,
      aliases: values.aliases,
    });
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'برچسب به‌روز شد.' });
    router.refresh();
  }

  return (
    <ul className="divide-y divide-gray-200 dark:divide-gray-800">
      {items.map((tag) => {
        const values = rowDraft(tag);
        return (
          <li key={tag.id} className="grid gap-3 py-3 sm:grid-cols-4">
            <div className="text-sm">
              <div className="font-medium">{tag.label}</div>
              <div className="text-gray-500">{tag.slug}</div>
            </div>
            <SelectField
              label="نوع"
              value={values.kind}
              options={[
                { value: 'tag', label: 'برچسب' },
                { value: 'brand', label: 'برند' },
              ]}
              onChange={(value) =>
                setDraft((current) => ({ ...current, [tag.id]: { ...values, kind: value } }))
              }
            />
            <TextField
              label="مترادف‌ها"
              value={values.aliases}
              onChange={(value) =>
                setDraft((current) => ({ ...current, [tag.id]: { ...values, aliases: value } }))
              }
            />
            <div className="flex items-end">
              <Button type="button" variant="outline" disabled={busy === tag.id} onClick={() => void save(tag)}>
                ذخیره
              </Button>
            </div>
          </li>
        );
      })}
    </ul>
  );
}
