'use client';

import { SelectField } from '../field';
import { Pagination } from '../pagination';
import { TABLE_PAGE_SIZES } from '@/lib/table-page';
import { toFaDigits } from '@/lib/fa';

import { tablePagerClass } from './styles';

type TablePagerProps = {
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
  onPageChange: (page: number) => void;
  onPerPageChange: (perPage: number) => void;
};

export function TablePager({ page, perPage, total, totalPages, onPageChange, onPerPageChange }: TablePagerProps) {
  const pages = Math.max(1, totalPages || 1);

  return (
    <div className={tablePagerClass}>
      <SelectField
        label='سطر در صفحه'
        value={String(perPage)}
        onChange={(value) => onPerPageChange(Number(value))}
        options={TABLE_PAGE_SIZES.map((size) => ({ value: String(size), label: toFaDigits(size) }))}
      />
      <div className='flex flex-wrap items-center gap-3'>
        <p className='text-theme-xs text-gray-500'>
          {toFaDigits(total)} مورد · صفحه {toFaDigits(page)} از {toFaDigits(pages)}
        </p>
        <Pagination currentPage={Math.min(page, pages)} totalPages={pages} onPageChange={onPageChange} />
      </div>
    </div>
  );
}
