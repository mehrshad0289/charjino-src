'use client';

import { COMMON_COPY } from '@/data/ecommerce';
import { ComponentCard } from '@/components/ui/component-card';
import { useState } from 'react';
import { Pagination } from './pagination';

export function PaginationBoard() {
  const [currentPage, setCurrentPage] = useState(1);

  return (
    <ComponentCard title={COMMON_COPY.pagination}>
      <div className='flex justify-center'>
        <Pagination currentPage={currentPage} totalPages={10} onPageChange={setCurrentPage} />
      </div>
    </ComponentCard>
  );
}
