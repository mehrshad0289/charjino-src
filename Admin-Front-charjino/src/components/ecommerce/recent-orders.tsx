import { COMMON_COPY, ECOMMERCE_COPY, RECENT_ORDERS } from '@/data/ecommerce';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import Image from 'next/image';

export function RecentOrders() {
  return (
    <div className='overflow-hidden rounded-2xl border border-gray-200 bg-white px-4 pt-4 pb-3 sm:px-6 dark:border-gray-800 dark:bg-white/3'>
      <div className='mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between'>
        <div>
          <h3 className='text-lg font-semibold text-gray-800 dark:text-white/90'>{ECOMMERCE_COPY.recentOrders}</h3>
        </div>
        <div className='flex items-center gap-3'>
          <button
            type='button'
            className='inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/3 dark:hover:text-gray-200'
          >
            <svg
              className='fill-white stroke-current dark:fill-gray-800'
              width='20'
              height='20'
              viewBox='0 0 20 20'
              fill='none'
              xmlns='http://www.w3.org/2000/svg'
            >
              <path d='M2.29004 5.90393H17.7067' stroke='' strokeWidth='1.5' strokeLinecap='round' strokeLinejoin='round' />
              <path d='M17.7075 14.0961H2.29085' stroke='' strokeWidth='1.5' strokeLinecap='round' strokeLinejoin='round' />
              <path
                d='M12.0826 3.33331C13.5024 3.33331 14.6534 4.48431 14.6534 5.90414C14.6534 7.32398 13.5024 8.47498 12.0826 8.47498C10.6627 8.47498 9.51172 7.32398 9.51172 5.90415C9.51172 4.48432 10.6627 3.33331 12.0826 3.33331Z'
                fill=''
                stroke=''
                strokeWidth='1.5'
              />
              <path
                d='M7.91745 11.525C6.49762 11.525 5.34662 12.676 5.34662 14.0959C5.34661 15.5157 6.49762 16.6667 7.91745 16.6667C9.33728 16.6667 10.4883 15.5157 10.4883 14.0959C10.4883 12.676 9.33728 11.525 7.91745 11.525Z'
                fill=''
                stroke=''
                strokeWidth='1.5'
              />
            </svg>
            {COMMON_COPY.filter}
          </button>
          <button
            type='button'
            className='inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 hover:text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/3 dark:hover:text-gray-200'
          >
            {COMMON_COPY.seeAll}
          </button>
        </div>
      </div>
      <div className='max-w-full overflow-x-auto'>
        <Table>
          <TableHeader className='border-y border-gray-100 dark:border-gray-800'>
            <TableRow>
              <TableCell isHeader className='py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400'>
                {ECOMMERCE_COPY.products}
              </TableCell>
              <TableCell isHeader className='py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400'>
                {ECOMMERCE_COPY.category}
              </TableCell>
              <TableCell isHeader className='py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400'>
                {ECOMMERCE_COPY.price}
              </TableCell>
              <TableCell isHeader className='py-3 text-start text-theme-xs font-medium text-gray-500 dark:text-gray-400'>
                {ECOMMERCE_COPY.status}
              </TableCell>
            </TableRow>
          </TableHeader>
          <TableBody className='divide-y divide-gray-100 dark:divide-gray-800'>
            {RECENT_ORDERS.length === 0 ? (
              <TableRow>
                <TableCell className='text-theme-sm py-6 text-gray-500'>سفارش نمونه در مسیر اصلی نیست.</TableCell>
              </TableRow>
            ) : (
            RECENT_ORDERS.map((product) => (
              <TableRow key={product.id}>
                <TableCell className='py-3'>
                  <div className='flex items-center gap-3'>
                    <div className='h-12.5 w-12.5 overflow-hidden rounded-md'>
                      <Image
                        width={50}
                        height={50}
                        src={product.image}
                        className='h-12.5 w-12.5'
                        alt={product.name}
                      />
                    </div>
                    <div>
                      <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{product.name}</p>
                      <span className='text-theme-xs text-gray-500 dark:text-gray-400'>
                        {ECOMMERCE_COPY.variants(product.variants)}
                      </span>
                    </div>
                  </div>
                </TableCell>
                <TableCell className='py-3 text-theme-sm text-gray-500 dark:text-gray-400'>{product.price}</TableCell>
                <TableCell className='py-3 text-theme-sm text-gray-500 dark:text-gray-400'>
                  {ECOMMERCE_COPY.categories[product.category]}
                </TableCell>
                <TableCell className='py-3 text-theme-sm text-gray-500 dark:text-gray-400'>
                  <Badge
                    size='sm'
                    color={
                      product.status === 'delivered' ? 'success' : product.status === 'pending' ? 'warning' : 'error'
                    }
                  >
                    {ECOMMERCE_COPY.statuses[product.status]}
                  </Badge>
                </TableCell>
              </TableRow>
            ))
            )}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
