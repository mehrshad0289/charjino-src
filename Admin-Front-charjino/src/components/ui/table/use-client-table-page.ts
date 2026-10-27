'use client';

import { useMemo, useState } from 'react';

import { parseTablePerPage, type TablePageSize } from '@/lib/table-page';

export function useClientTablePage<T>(items: T[], initialPerPage: TablePageSize = 10) {
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState<TablePageSize>(initialPerPage);

  const total = items.length;
  const totalPages = Math.max(1, Math.ceil(total / perPage) || 1);
  const safePage = Math.min(page, totalPages);

  const rows = useMemo(() => {
    const start = (safePage - 1) * perPage;
    return items.slice(start, start + perPage);
  }, [items, perPage, safePage]);

  return {
    rows,
    page: safePage,
    perPage,
    total,
    totalPages,
    onPageChange: setPage,
    onPerPageChange: (next: number) => {
      setPerPage(parseTablePerPage(next));
      setPage(1);
    }
  };
}
