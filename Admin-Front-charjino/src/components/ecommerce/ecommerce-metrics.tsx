'use client';

import { ECOMMERCE_COPY, ECOMMERCE_METRICS } from '@/data/ecommerce';
import { Badge } from '@/components/ui/badge';
import { ArrowDownIcon, ArrowUpIcon, BoxIconLine, GroupIcon } from '@/components/ui/icon';

export function EcommerceMetrics() {
  return (
    <div className='grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6'>
      <div className='rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3'>
        <div className='flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800'>
          <GroupIcon className='size-6 text-gray-800 dark:text-white/90' />
        </div>
        <div className='mt-5 flex items-end justify-between'>
          <div>
            <span className='text-sm text-gray-500 dark:text-gray-400'>{ECOMMERCE_COPY.customers}</span>
            <h4 className='mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90'>
              {ECOMMERCE_METRICS.customers}
            </h4>
          </div>
          <Badge color='success'>
            <ArrowUpIcon />
            {ECOMMERCE_METRICS.customersChange}
          </Badge>
        </div>
      </div>

      <div className='rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3'>
        <div className='flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800'>
          <BoxIconLine className='text-gray-800 dark:text-white/90' />
        </div>
        <div className='mt-5 flex items-end justify-between'>
          <div>
            <span className='text-sm text-gray-500 dark:text-gray-400'>{ECOMMERCE_COPY.orders}</span>
            <h4 className='mt-2 text-title-sm font-bold text-gray-800 dark:text-white/90'>
              {ECOMMERCE_METRICS.orders}
            </h4>
          </div>
          <Badge color='error'>
            <ArrowDownIcon className='text-error-500' />
            {ECOMMERCE_METRICS.ordersChange}
          </Badge>
        </div>
      </div>
    </div>
  );
}
