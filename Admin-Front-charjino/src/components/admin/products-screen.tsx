'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useState } from 'react';

import {
  Alert,
  Badge,
  Button,
  DataTable,
  SelectField,
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
  TextField
} from '@/components/ui';
import { ORIGIN_FILTER_OPTIONS, originBadgeColor, originLabel, productOrigin } from '@/lib/catalog-origin';
import { gameKeyPlatformLabel } from '@/lib/game-key-platform';
import { toFaDigits } from '@/lib/fa';
import { parseTablePerPage } from '@/lib/table-page';
import type { AdminGroup, AdminProduct } from '@/types/admin';

type ProductsScreenProps = {
  items: AdminProduct[];
  groups?: AdminGroup[];
  lockedGroupId?: number;
  error?: string;
  q?: string;
  category?: string;
  status?: string;
  visible?: string;
  source?: string;
  platform?: string;
  region?: string;
  platforms?: string[];
  regions?: string[];
  page?: number;
  perPage?: number;
  totalPages?: number;
  total?: number;
  basePath?: string;
};

export function ProductsScreen({
  items,
  groups = [],
  lockedGroupId,
  error,
  q = '',
  category = '',
  status = '',
  visible = '',
  source = '',
  platform = '',
  region = '',
  platforms = [],
  regions = [],
  page = 1,
  perPage = 10,
  totalPages = 1,
  total,
  basePath = '/catalog/products'
}: ProductsScreenProps) {
  const router = useRouter();
  const [query, setQuery] = useState(q);
  const [categoryFilter, setCategoryFilter] = useState(category || (lockedGroupId ? String(lockedGroupId) : 'all'));
  const [statusFilter, setStatusFilter] = useState(status || 'all');
  const [visibleFilter, setVisibleFilter] = useState(visible || 'all');
  const [sourceFilter, setSourceFilter] = useState(source || 'all');
  const [platformFilter, setPlatformFilter] = useState(platform || 'all');
  const [regionFilter, setRegionFilter] = useState(region || 'all');

  function apply(nextPage = 1, nextPerPage = perPage) {
    const params = new URLSearchParams();
    if (!lockedGroupId && categoryFilter && categoryFilter !== 'all') params.set('category', categoryFilter);
    if (statusFilter && statusFilter !== 'all') params.set('status', statusFilter);
    if (visibleFilter === 'visible') params.set('visible', '1');
    if (visibleFilter === 'hidden') params.set('visible', '0');
    if (sourceFilter && sourceFilter !== 'all') params.set('source', sourceFilter);
    if (platformFilter && platformFilter !== 'all') params.set('platform', platformFilter);
    if (regionFilter && regionFilter !== 'all') params.set('region', regionFilter);
    if (query.trim()) params.set('q', query.trim());
    if (nextPerPage !== 10) params.set('perPage', String(nextPerPage));
    if (nextPage > 1) params.set('page', String(nextPage));
    const suffix = params.toString();
    router.push(suffix ? `${basePath}?${suffix}` : basePath);
  }

  if (error) {
    return <Alert variant='error' title='محصولات خوانده نشد' message={error} />;
  }

  return (
    <div className='space-y-4'>
      <div className='flex justify-end'>
        <Link href='/catalog/products/new' className='text-theme-sm font-medium text-brand-500'>
          محصول گیفت‌معادل
        </Link>
      </div>
      <form
        className='grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
        onSubmit={(event) => {
          event.preventDefault();
          apply(1);
        }}
      >
        <TextField label='جستجو' value={query} onChange={setQuery} placeholder='فارسی، انگلیسی، handle یا کد' />
        {lockedGroupId ? null : (
          <SelectField
            label='دسته'
            value={categoryFilter}
            onChange={setCategoryFilter}
            options={[
              { value: 'all', label: 'همه دسته‌ها' },
              ...groups.map((group) => ({ value: String(group.id), label: group.title }))
            ]}
          />
        )}
        <SelectField
          label='وضعیت'
          value={statusFilter}
          onChange={setStatusFilter}
          options={[
            { value: 'all', label: 'همه' },
            { value: 'active', label: 'فعال' },
            { value: 'hidden', label: 'مخفی' },
            { value: 'draft', label: 'پیش‌نویس' }
          ]}
        />
        <SelectField
          label='نمایش'
          value={visibleFilter}
          onChange={setVisibleFilter}
          options={[
            { value: 'all', label: 'همه' },
            { value: 'visible', label: 'قابل نمایش' },
            { value: 'hidden', label: 'مخفی از فروشگاه' }
          ]}
        />
        <SelectField
          label='منبع'
          value={sourceFilter}
          onChange={setSourceFilter}
          options={ORIGIN_FILTER_OPTIONS}
        />
        <SelectField
          label='پلتفرم'
          value={platformFilter}
          onChange={setPlatformFilter}
          options={[
            { value: 'all', label: 'همه' },
            ...(platform && platform !== 'all' && !platforms.includes(platform)
              ? [{ value: platform, label: gameKeyPlatformLabel(platform) }]
              : []),
            ...platforms.map((item) => ({ value: item, label: gameKeyPlatformLabel(item) }))
          ]}
        />
        <SelectField
          label='ریجن'
          value={regionFilter}
          onChange={setRegionFilter}
          options={[
            { value: 'all', label: 'همه' },
            ...(region && !regions.includes(region) ? [{ value: region, label: region }] : []),
            ...regions.map((item) => ({ value: item, label: item }))
          ]}
        />
        <div className='flex items-end'>
          <Button type='submit'>فیلتر</Button>
        </div>
      </form>
      {items.length === 0 ? (
        <Alert variant='info' title='محصولی نیست' message='با این فیلتر محصولی پیدا نشد.' />
      ) : (
        <DataTable
          page={page}
          perPage={perPage}
          total={total ?? items.length}
          totalPages={totalPages}
          onPageChange={(next) => apply(next)}
          onPerPageChange={(next) => apply(1, parseTablePerPage(next))}
        >
          <Table>
            <TableHeader className='border-b border-gray-100 dark:border-white/5'>
              <TableRow>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  محصول
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  دسته
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  بسته‌ها
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  منبع
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  وضعیت
                </TableCell>
              </TableRow>
            </TableHeader>
            <TableBody className='divide-y divide-gray-100 dark:divide-white/5'>
              {items.map((product) => {
                const origin = productOrigin(product);
                return (
                  <TableRow key={product.id}>
                    <TableCell className='px-5 py-4'>
                      <Link href={`/catalog/products/${product.id}`} className='text-theme-sm font-medium text-brand-500'>
                        {product.title}
                      </Link>
                      {product.nameEn ? (
                        <p className='text-theme-xs mt-1 text-left text-gray-500' dir='ltr'>
                          {product.nameEn}
                        </p>
                      ) : null}
                      <p className='text-theme-xs mt-1 text-gray-500'>{product.handle}</p>
                      {product.platform || product.region ? (
                        <p className='text-theme-xs mt-1 text-gray-500'>
                          {[gameKeyPlatformLabel(product.platform) || product.platform, product.region]
                            .filter(Boolean)
                            .join(' · ')}
                        </p>
                      ) : null}
                    </TableCell>
                    <TableCell className='text-theme-sm px-5 py-4 text-gray-500'>
                      {product.categoryName || '—'}
                    </TableCell>
                    <TableCell className='text-theme-sm px-5 py-4 text-gray-500'>
                      {toFaDigits(product.skusCount ?? product.skus?.length ?? 0)}
                    </TableCell>
                    <TableCell className='px-5 py-4'>
                      <Badge color={originBadgeColor(origin)}>{originLabel(origin)}</Badge>
                      {product.giftProxy ? (
                        <span className='ms-2 inline-flex'>
                          <Badge color='primary'>گیفت معادل</Badge>
                        </span>
                      ) : null}
                    </TableCell>
                    <TableCell className='px-5 py-4'>
                      <Badge color={product.status === 'active' && product.visible ? 'success' : 'warning'}>
                        {product.visible ? (product.status === 'active' ? 'فعال' : product.status) : 'مخفی'}
                      </Badge>
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
          </Table>
        </DataTable>
      )}
    </div>
  );
}
