'use client';

import { Button } from '@/components/ui';
import type { ReactNode } from 'react';

type SortableListProps<T> = {
  items: T[];
  onChange: (items: T[]) => void;
  renderItem: (item: T, index: number) => ReactNode;
  addLabel: string;
  create: () => T;
};

export function SortableList<T>({ items, onChange, renderItem, addLabel, create }: SortableListProps<T>) {
  function move(index: number, dir: -1 | 1) {
    const target = index + dir;
    if (target < 0 || target >= items.length) return;
    const next = [...items];
    const [row] = next.splice(index, 1);
    next.splice(target, 0, row);
    onChange(next);
  }

  return (
    <div className='space-y-3'>
      {items.map((item, index) => (
        <div key={index} className='space-y-3 rounded-xl border border-gray-100 p-4 dark:border-white/5'>
          <div className='flex justify-end gap-2'>
            <Button type='button' size='sm' variant='outline' disabled={index === 0} onClick={() => move(index, -1)}>
              بالا
            </Button>
            <Button
              type='button'
              size='sm'
              variant='outline'
              disabled={index === items.length - 1}
              onClick={() => move(index, 1)}
            >
              پایین
            </Button>
            <Button
              type='button'
              size='sm'
              variant='outline'
              onClick={() => onChange(items.filter((_, i) => i !== index))}
            >
              حذف
            </Button>
          </div>
          {renderItem(item, index)}
        </div>
      ))}
      <Button type='button' size='sm' variant='outline' onClick={() => onChange([...items, create()])}>
        {addLabel}
      </Button>
    </div>
  );
}
