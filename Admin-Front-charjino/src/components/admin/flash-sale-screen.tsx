'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';

import { deleteFlashSaleItem, getProduct, listProducts, saveFlashSaleItem, saveHomeCopy } from '@/actions/admin.actions';
import {
  Alert,
  Button,
  DataTable,
  Switch,
  Table,
  TableBody,
  TableCell,
  TableHeader,
  TableRow,
  TextField,
  useClientTablePage
} from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { formatToman } from '@/lib/fa';
import type { AdminFlashSaleItem, AdminHomePayload, AdminProduct, AdminSku } from '@/types/admin';

type FlashSaleScreenProps = {
  home: AdminHomePayload;
  error?: string;
};

function toLocalInput(iso: string | null) {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const pad = (value: number) => String(value).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function FlashSaleScreen({ home, error }: FlashSaleScreenProps) {
  const router = useRouter();
  const [title, setTitle] = useState(home.flashSaleTitle);
  const [subtitle, setSubtitle] = useState(home.flashSaleSubtitle);
  const [endsAt, setEndsAt] = useState(toLocalInput(home.flashSaleEndsAt));
  const [query, setQuery] = useState('');
  const [hits, setHits] = useState<AdminProduct[]>([]);
  const [searching, setSearching] = useState(false);
  const [product, setProduct] = useState<AdminProduct | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [, setMessage] = useToastMessage();
  const pager = useClientTablePage(home.flashSale);

  useEffect(() => {
    const needle = query.trim();
    if (!needle) {
      setHits([]);
      setSearching(false);
      return;
    }
    let cancelled = false;
    const handle = window.setTimeout(() => {
      setSearching(true);
      void listProducts({ q: needle, visible: '1', perPage: 8 }).then((result) => {
        if (cancelled) return;
        setSearching(false);
        if (!result.ok) {
          setHits([]);
          setMessage({ variant: 'error', text: result.message });
          return;
        }
        setHits(result.items);
      });
    }, 280);
    return () => {
      cancelled = true;
      window.clearTimeout(handle);
    };
  }, [query]);

  async function saveCopy() {
    setBusy('save');
    const result = await saveHomeCopy({
      flashSaleTitle: title,
      flashSaleSubtitle: subtitle,
      flashSaleEndsAt: endsAt ? new Date(endsAt).toISOString() : null
    });
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'متن و شمارنده ذخیره شد.' });
    router.refresh();
  }

  async function chooseProduct(id: number) {
    setBusy(`choose:${id}`);
    const result = await getProduct(id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setProduct(result.item);
    setHits([]);
    setQuery(result.item.title);
  }

  async function addSku(sku: AdminSku) {
    if (!product || !sku.saleActive) return;
    setBusy(`add:${sku.id}`);
    const result = await saveFlashSaleItem({
      skuId: sku.id,
      price: sku.sellPrice,
      originalPrice: sku.price,
      image: product.displayImage || product.imageUrl || product.thumbnail || '',
      visible: true,
      sortOrder: home.flashSale.length
    });
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'آیتم فروش ویژه اضافه شد.' });
    router.refresh();
  }

  async function toggle(item: AdminFlashSaleItem, visible: boolean) {
    const result = await saveFlashSaleItem({ ...item, visible, id: item.id });
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: visible ? 'آیتم در فروش ویژه نمایش داده می‌شود.' : 'آیتم از فروش ویژه مخفی شد.' });
    router.refresh();
  }

  async function remove(id: number) {
    setBusy(`remove:${id}`);
    const result = await deleteFlashSaleItem(id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'آیتم فروش ویژه حذف شد.' });
    router.refresh();
  }

  if (error) {
    return <Alert variant='error' title='فروش ویژه' message={error} />;
  }

  return (
    <div className='space-y-8'>

      <div className='space-y-4 rounded-xl border border-gray-200 p-5 dark:border-white/5'>
        <TextField label='عنوان بخش' value={title} onChange={setTitle} />
        <TextField label='زیرعنوان' value={subtitle} onChange={setSubtitle} />
        <TextField label='پایان شمارنده' type='datetime-local' value={endsAt} onChange={setEndsAt} />
        <Button type='button' loading={busy === 'save'} disabled={busy !== null} onClick={() => void saveCopy()}>
          ذخیره عنوان و زمان
        </Button>
      </div>

      <div className='space-y-4 rounded-xl border border-gray-200 p-5 dark:border-white/5'>
        <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>افزودن محصول به فروش ویژه</h3>
        <p className='text-theme-xs text-gray-500'>
          با ذخیرهٔ تخفیف روی بسته در صفحهٔ محصول، کارت همین‌جا و در هوم ساخته می‌شود. اینجا برای ترتیب، مخفی‌کردن و شمارنده است. اگر خواستی دستی اضافه کنی، فقط بسته‌ای که تخفیف فعال دارد قابل افزودن است.
        </p>
        <TextField
          label='جستجوی محصول'
          placeholder='نام محصول...'
          value={query}
          onChange={setQuery}
        />
        {searching ? <p className='text-theme-xs text-gray-500'>در حال جستجو...</p> : null}
        {hits.length > 0 ? (
          <ul className='space-y-2'>
            {hits.map((hit) => (
              <li key={hit.id} className='flex items-center justify-between gap-2'>
                <span className='text-theme-sm text-gray-800 dark:text-white/90'>{hit.title}</span>
                <Button
                  type='button'
                  size='sm'
                  variant='outline'
                  loading={busy === `choose:${hit.id}`}
                  disabled={busy !== null}
                  onClick={() => void chooseProduct(hit.id)}
                >
                  انتخاب
                </Button>
              </li>
            ))}
          </ul>
        ) : null}
        {product ? (
          <div className='space-y-3'>
            <div className='flex flex-wrap items-center justify-between gap-2'>
              <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{product.title}</p>
              <Link className='text-theme-xs text-brand-500' href={`/catalog/products/${product.id}`}>
                تخفیف بسته در صفحهٔ محصول
              </Link>
            </div>
            {product.skus.length === 0 ? (
              <p className='text-theme-xs text-gray-500'>این محصول بستهٔ فعالی ندارد.</p>
            ) : (
              <ul className='space-y-2'>
                {product.skus.map((sku) => (
                  <li key={sku.id} className='flex flex-wrap items-center justify-between gap-2'>
                    <span className='text-theme-sm text-gray-800 dark:text-white/90'>
                      {sku.name}
                      {sku.saleActive ? (
                        <>
                          {' '}
                          — {formatToman(sku.sellPrice)}
                          <span className='text-theme-xs text-gray-400 line-through'> {formatToman(sku.price)}</span>
                        </>
                      ) : (
                        <span className='text-theme-xs text-gray-500'> — بدون تخفیف فعال</span>
                      )}
                    </span>
                    <Button
                      type='button'
                      size='sm'
                      loading={busy === `add:${sku.id}`}
                      disabled={busy !== null || !sku.saleActive}
                      onClick={() => void addSku(sku)}
                    >
                      افزودن
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        ) : null}
      </div>

      <DataTable
        page={pager.page}
        perPage={pager.perPage}
        total={pager.total}
        totalPages={pager.totalPages}
        onPageChange={pager.onPageChange}
        onPerPageChange={pager.onPerPageChange}
      >
        <Table>
            <TableHeader className='border-b border-gray-100 dark:border-white/5'>
              <TableRow>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  محصول
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  قیمت
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  نمایش
                </TableCell>
                <TableCell isHeader className='text-theme-xs px-5 py-3 text-start font-medium text-gray-500'>
                  حذف
                </TableCell>
              </TableRow>
            </TableHeader>
            <TableBody className='divide-y divide-gray-100 dark:divide-white/5'>
              {pager.rows.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className='px-5 py-4'>
                    <p className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>{item.productName}</p>
                    <p className='text-theme-xs text-gray-500'>{item.productVariantName}</p>
                  </TableCell>
                  <TableCell className='px-5 py-4'>
                    <p>{formatToman(item.price)}</p>
                    <p className='text-theme-xs text-gray-400 line-through'>{formatToman(item.originalPrice)}</p>
                  </TableCell>
                  <TableCell className='px-5 py-4'>
                    <Switch
                      label={item.visible ? 'فعال' : 'مخفی'}
                      checked={item.visible}
                      onChange={(visible) => void toggle(item, visible)}
                    />
                  </TableCell>
                  <TableCell className='px-5 py-4'>
                    <Button
                      variant='outline'
                      size='sm'
                      type='button'
                      loading={busy === `remove:${item.id}`}
                      disabled={busy !== null}
                      onClick={() => void remove(item.id)}
                    >
                      حذف
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
        </Table>
      </DataTable>
    </div>
  );
}
