'use client';

import { useRouter } from 'next/navigation';
import { FormEvent, useState } from 'react';
import { Button, SearchField } from '@/components/ui';
import { ROUTES } from '@/lib/routes';

type BlogSearchFormProps = {
  initialQ?: string;
};

export function BlogSearchForm({ initialQ = '' }: BlogSearchFormProps) {
  const router = useRouter();
  const [q, setQ] = useState(initialQ);

  function onSubmit(event: FormEvent) {
    event.preventDefault();
    const query = q.trim();
    if (query.length < 2) {
      router.push(ROUTES.blog);
      return;
    }
    router.push(`${ROUTES.blogSearch}?q=${encodeURIComponent(query)}`);
  }

  return (
    <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={onSubmit}>
      <div className="min-w-0 flex-1">
        <SearchField label="جستجوی وبلاگ" value={q} onChange={setQ} placeholder="جستجو در مطالب..." />
      </div>
      <Button type="submit" size="M">
        جستجو
      </Button>
    </form>
  );
}
