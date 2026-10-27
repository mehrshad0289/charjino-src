import { BASIC_TABLE_COPY, BASIC_TABLE_ROWS } from '@/data/basic-table';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHeader, TableRow } from '@/components/ui/table';
import type { BasicTableStatus } from '@/types/basic-table';
import Image from 'next/image';

const STATUS_COLOR: Record<BasicTableStatus, 'success' | 'warning' | 'error'> = {
  active: 'success',
  pending: 'warning',
  cancel: 'error'
};

export function BasicTableOne() {
  return (
    <div className='overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/5 dark:bg-white/3'>
      <div className='max-w-full overflow-x-auto'>
        <Table>
          <TableHeader className='border-b border-gray-100 dark:border-white/5'>
            <TableRow>
              <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500 dark:text-gray-400'>
                {BASIC_TABLE_COPY.user}
              </TableCell>
              <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500 dark:text-gray-400'>
                {BASIC_TABLE_COPY.project}
              </TableCell>
              <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500 dark:text-gray-400'>
                {BASIC_TABLE_COPY.team}
              </TableCell>
              <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500 dark:text-gray-400'>
                {BASIC_TABLE_COPY.status}
              </TableCell>
              <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500 dark:text-gray-400'>
                {BASIC_TABLE_COPY.budget}
              </TableCell>
            </TableRow>
          </TableHeader>
          <TableBody className='divide-y divide-gray-100 dark:divide-white/5'>
            {BASIC_TABLE_ROWS.map((order) => (
              <TableRow key={order.id}>
                <TableCell className='px-5 py-4 text-start sm:px-6'>
                  <div className='flex items-center gap-3'>
                    <div className='h-10 w-10 overflow-hidden rounded-full'>
                      <Image width={40} height={40} src={order.user.image} alt={order.user.name} />
                    </div>
                    <div>
                      <span className='text-theme-sm block font-medium text-gray-800 dark:text-white/90'>
                        {order.user.name}
                      </span>
                      <span className='text-theme-xs block text-gray-500 dark:text-gray-400'>{order.user.role}</span>
                    </div>
                  </div>
                </TableCell>
                <TableCell className='text-theme-sm px-4 py-3 text-start text-gray-500 dark:text-gray-400'>
                  {order.projectName}
                </TableCell>
                <TableCell className='text-theme-sm px-4 py-3 text-start text-gray-500 dark:text-gray-400'>
                  <div className='flex -space-x-2'>
                    {order.team.images.map((teamImage, index) => (
                      <div
                        key={`${order.id}-${teamImage}`}
                        className='h-6 w-6 overflow-hidden rounded-full border-2 border-white dark:border-gray-900'
                      >
                        <Image width={24} height={24} src={teamImage} alt='' className='w-full' />
                      </div>
                    ))}
                  </div>
                </TableCell>
                <TableCell className='text-theme-sm px-4 py-3 text-start text-gray-500 dark:text-gray-400'>
                  <Badge size='sm' color={STATUS_COLOR[order.status]}>
                    {BASIC_TABLE_COPY.statusLabel[order.status]}
                  </Badge>
                </TableCell>
                <TableCell className='text-theme-sm px-4 py-3 text-gray-500 dark:text-gray-400'>{order.budget}</TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </div>
  );
}
