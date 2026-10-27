'use client';

import type { ReactNode } from 'react';

import { tableScrollClass, tableShellClass } from './styles';
import { TablePager } from './table-pager';

type DataTableProps = {
  children: ReactNode;
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
};

export function DataTable({
  children,
  page,
  perPage,
  total,
  totalPages,
  onPageChange,
  onPerPageChange
}: DataTableProps) {
  return (
    <div className={tableShellClass}>
      <div className={tableScrollClass}>{children}</div>
      <TablePager
        page={page}
        perPage={perPage}
        total={total}
        totalPages={totalPages}
        onPageChange={onPageChange}
        onPerPageChange={onPerPageChange}
      />
    </div>
  );
}
