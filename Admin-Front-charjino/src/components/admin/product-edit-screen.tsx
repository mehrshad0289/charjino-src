'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';
import { useFormik } from 'formik';
import * as Yup from 'yup';

import { hideProduct, refreshProductRequirements, updateProduct, updateSku, createGiftSku } from '@/actions/admin.actions';
import { CatalogImageField } from '@/components/admin/catalog-image-field';
import { ProductGuideCard } from '@/components/admin/buy-guide/product-guide-card';
import { catalogImageGuide } from '@/lib/catalog-image-guide';
import { Button, DateField, SelectField, Switch, TextField, TextareaField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { originLabel, productOrigin } from '@/lib/catalog-origin';
import { formatToman, formatUsd, onlyDigits, toFaDigits } from '@/lib/fa';
import { jalaliToday } from '@/lib/jalali';
import type { AdminGroup, AdminProduct, AdminSku, GiftCardOption } from '@/types/admin';

type ProductEditScreenProps = {
  product: AdminProduct;
  groups: AdminGroup[];
  giftCards?: GiftCardOption[];
};

const productSchema = Yup.object({
  title: Yup.string().trim().required('نام فارسی لازم است').max(160, 'حداکثر ۱۶۰ کاراکتر'),
  nameEn: Yup.string().trim().required('نام انگلیسی لازم است').max(160, 'حداکثر ۱۶۰ کاراکتر'),
  subtitle: Yup.string().max(160, 'حداکثر ۱۶۰ کاراکتر'),
  seoTitle: Yup.string().max(180, 'حداکثر ۱۸۰ کاراکتر'),
  seoDescription: Yup.string().max(300, 'حداکثر ۳۰۰ کاراکتر'),
  description: Yup.string().max(8000, 'حداکثر ۸۰۰۰ کاراکتر'),
  publisher: Yup.string().max(120, 'حداکثر ۱۲۰ کاراکتر'),
  imageUrl: Yup.string().max(2048, 'حداکثر ۲۰۴۸ کاراکتر'),
  catalogGroupId: Yup.string(),
  sortOrder: Yup.string(),
  searchTags: Yup.string().max(500, 'حداکثر ۵۰۰ کاراکتر'),
  status: Yup.string().oneOf(['active', 'hidden', 'draft']),
  visible: Yup.boolean(),
  isPopular: Yup.boolean(),
  marginPercent: Yup.string().test('margin', 'سود بین ۰ تا ۱۰۰ درصد است', (value) => {
    const raw = (value ?? '').trim();
    if (!raw) return true;
    const amount = Number(raw);
    return Number.isFinite(amount) && amount >= 0 && amount <= 100;
  }),
  clearSkuMargins: Yup.boolean()
});

function roundTomanInput(value: string) {
  const amount = Number(onlyDigits(value));
  if (!Number.isFinite(amount) || amount <= 0) return 0;
  const rounded = Math.round(amount / 1000) * 1000;
  return rounded > 0 ? rounded : 1000;
}

function saleDateValue(iso: string | null | undefined) {
  return iso ? iso.slice(0, 10) : '';
}

function salePercentFromPrices(list: number, sale: number) {
  if (!list || !sale || sale >= list) return '';
  return String(Math.round((1 - sale / list) * 100));
}

function salePriceFromPercent(list: number, percent: number) {
  if (!list || !Number.isFinite(percent) || percent <= 0 || percent >= 100) return 0;
  return roundTomanInput(String(Math.round((list * (100 - percent)) / 100)));
}

function previewSellToman(purchaseToman: number, marginPercent: number) {
  if (!Number.isFinite(purchaseToman) || purchaseToman <= 0 || !Number.isFinite(marginPercent)) return 0;
  return Math.round((purchaseToman * (100 + marginPercent)) / 100 / 1000) * 1000;
}

function parseMargin(value: string) {
  const raw = value.trim();
  if (!raw) return null;
  const amount = Number(raw);
  return Number.isFinite(amount) ? amount : null;
}

const skuSchema = Yup.object({
  name: Yup.string().trim().required('نام فارسی بسته لازم است').max(160, 'حداکثر ۱۶۰ کاراکتر'),
  nameEn: Yup.string().trim().required('نام انگلیسی بسته لازم است').max(160, 'حداکثر ۱۶۰ کاراکتر'),
  priceManual: Yup.boolean(),
  marginPercent: Yup.string().test('sku-margin', 'سود بین ۰ تا ۱۰۰ درصد است', (value) => {
    const raw = (value ?? '').trim();
    if (!raw) return true;
    const amount = Number(raw);
    return Number.isFinite(amount) && amount >= 0 && amount <= 100;
  }),
  price: Yup.string().when('priceManual', {
    is: true,
    then: (schema) => schema.trim().required('قیمت فروش لازم است'),
    otherwise: (schema) => schema
  }),
  salePrice: Yup.string(),
  salePercent: Yup.string().test('sale-pct', 'تخفیف باید بین ۱ تا ۷۰ درصد باشد', (value) => {
    const raw = (value ?? '').trim();
    if (!raw) return true;
    const amount = Number(raw);
    return Number.isFinite(amount) && amount >= 1 && amount <= 70;
  }),
  saleEndsAt: Yup.string(),
  visible: Yup.boolean()
})
  .test('sale-complete', 'برای تخفیف درصد یا قیمت ویژه و تاریخ پایان لازم است', function (values) {
    const sale = onlyDigits(values.salePrice || '');
    const percent = (values.salePercent || '').trim();
    const ends = (values.saleEndsAt || '').trim();
    if (!sale && !percent && !ends) return true;
    if ((sale || percent) && ends) return true;
    return this.createError({
      path: ends ? (sale ? 'salePercent' : 'salePrice') : 'saleEndsAt',
      message: 'برای تخفیف درصد یا قیمت ویژه و تاریخ پایان لازم است'
    });
  })
  .test('sale-lt', 'قیمت تخفیف باید کمتر از قیمت فروش باشد', function (values) {
    const sale = roundTomanInput(values.salePrice || '');
    if (!sale) return true;
    const price = roundTomanInput(values.price || '');
    if (price && sale >= price) {
      return this.createError({
        path: 'salePrice',
        message: 'قیمت تخفیف باید کمتر از قیمت فروش باشد'
      });
    }
    return true;
  });

export function ProductEditScreen({ product, groups, giftCards = [] }: ProductEditScreenProps) {
  const router = useRouter();
  const origin = productOrigin(product);
  const shop2Refresh = origin === 'shop2' && Boolean(product.shop2CategoryId);
  const [busy, setBusy] = useState<string | null>(null);
  const [, setMessage] = useToastMessage();

  const form = useFormik({
    enableReinitialize: true,
    initialValues: {
      title: product.title,
      nameEn: product.nameEn ?? '',
      subtitle: product.subtitle ?? '',
      seoTitle: product.seoTitle ?? '',
      seoDescription: product.seoDescription ?? '',
      description: product.description ?? '',
      publisher: product.publisher ?? '',
      imageUrl: product.displayImage || product.imageUrl || product.thumbnail || '',
      visible: product.visible,
      isPopular: product.isPopular,
      status: product.status,
      sortOrder: String(product.sortOrder ?? 0),
      catalogGroupId: product.catalogGroupId ? String(product.catalogGroupId) : '',
      searchTags: (product.searchTags ?? []).join('، '),
      marginPercent: product.marginPercent ?? '',
      clearSkuMargins: false,
      regionNote: product.purchaseSchema?.regionNote ?? '',
      clearExcluded: false,
      substituteProductId: product.substituteProductId ? String(product.substituteProductId) : ''
    },
    validationSchema: productSchema,
    onSubmit: async (values) => {
      setBusy('save');
      const result = await updateProduct(product.id, {
        title: values.title.trim(),
        nameEn: values.nameEn.trim(),
        subtitle: values.subtitle,
        seoTitle: values.seoTitle.trim() === '' ? null : values.seoTitle.trim(),
        seoDescription: values.seoDescription.trim() === '' ? null : values.seoDescription.trim(),
        description: values.description,
        publisher: values.publisher,
        imageUrl: values.imageUrl,
        visible: values.visible,
        isPopular: values.isPopular,
        status: values.status as AdminProduct['status'],
        sortOrder: Number(values.sortOrder) || 0,
        catalogGroupId: values.catalogGroupId ? Number(values.catalogGroupId) : null,
        searchTags: values.searchTags
          .split(/[,،\n]+/u)
          .map((tag) => tag.trim())
          .filter(Boolean),
        marginPercent: values.marginPercent.trim() === '' ? null : values.marginPercent.trim(),
        clearSkuMargins: values.clearSkuMargins,
        regionNote: values.regionNote.trim() === '' ? null : values.regionNote.trim(),
        clearExcluded: values.clearExcluded,
        substituteProductId: values.substituteProductId.trim() === '' ? null : Number(values.substituteProductId)
      });
      setBusy(null);
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        return;
      }
      setMessage({ variant: 'success', text: 'محصول ذخیره شد.' });
      router.refresh();
    }
  });

  async function hide() {
    setBusy('hide');
    const result = await hideProduct(
      product.id,
      form.values.substituteProductId.trim() === '' ? null : Number(form.values.substituteProductId)
    );
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'محصول از فروشگاه مخفی شد.' });
    router.refresh();
  }

  async function refreshShop2() {
    setBusy('refresh');
    const result = await refreshProductRequirements(product.id);
    setBusy(null);
    if (!result.ok) {
      setMessage({ variant: 'error', text: result.message });
      return;
    }
    setMessage({ variant: 'success', text: 'فیلدهای بازیکن Shop2 به‌روز شد.' });
    router.refresh();
  }

  return (
    <div className='space-y-6'>
      <ProductGuideCard product={product} />
      <p className='text-sm text-gray-500'>منبع: {originLabel(origin)}</p>
      {origin === 'fazercards' ? (
        <p className='text-sm text-gray-500'>
          این محصول از FazerCards همگام می‌شود. نام فارسی و انگلیسی دست‌نویس در sync بعدی حفظ می‌شود. اگر تأمین‌کننده
          بسته را حذف کند، آن بسته در فروشگاه مخفی می‌شود و قابل خرید نیست.
        </p>
      ) : null}
      <form className='space-y-6' onSubmit={form.handleSubmit}>
        <TextField
          name='title'
          label='نام فارسی'
          hint='برای نمایش فروشگاه، جستجو و سئو'
          value={form.values.title}
          onChange={(value) => void form.setFieldValue('title', value)}
          onBlur={() => form.setFieldTouched('title', true)}
          previewState={form.touched.title && form.errors.title ? 'error' : 'default'}
        />
        {form.touched.title && form.errors.title ? (
          <p className='text-theme-xs text-error-500'>{form.errors.title}</p>
        ) : null}
        <TextField
          name='nameEn'
          label='نام انگلیسی'
          hint='برای جستجو و سئو؛ با همگام تأمین‌کننده بازنویسی نمی‌شود'
          dir='ltr'
          className='text-left'
          placeholder='PUBG Mobile'
          value={form.values.nameEn}
          onChange={(value) => void form.setFieldValue('nameEn', value)}
          onBlur={() => form.setFieldTouched('nameEn', true)}
          previewState={form.touched.nameEn && form.errors.nameEn ? 'error' : 'default'}
        />
        {form.touched.nameEn && form.errors.nameEn ? (
          <p className='text-theme-xs text-error-500'>{form.errors.nameEn}</p>
        ) : null}
        <TextField
          name='subtitle'
          label='زیرعنوان'
          value={form.values.subtitle}
          onChange={(value) => void form.setFieldValue('subtitle', value)}
        />
        <TextField
          name='seoTitle'
          label='عنوان سئو (seo_title)'
          hint='اگر خالی باشد الگوی «خرید {نام فارسی} ارزان | تحویل فوری» استفاده می‌شود. بدون ظاهر فروشگاه.'
          value={form.values.seoTitle}
          onChange={(value) => void form.setFieldValue('seoTitle', value)}
        />
        <TextareaField
          label='توضیح سئو (seo_description)'
          hint='اگر خالی باشد توضیح یکتا با قیمت تومانی، تحویل فوری و پشتیبانی ساخته می‌شود'
          rows={3}
          value={form.values.seoDescription}
          onChange={(value) => void form.setFieldValue('seoDescription', value)}
        />
        <TextareaField
          label='برچسب جستجو و سئو'
          hint='کلمه‌هایی که در نام فارسی/انگلیسی نیستند؛ با ویرگول جدا کنید. مثلاً فوتبال برای FC 27'
          placeholder='فوتبال، فیفا'
          rows={3}
          value={form.values.searchTags}
          onChange={(value) => void form.setFieldValue('searchTags', value)}
        />
        {form.touched.searchTags && form.errors.searchTags ? (
          <p className='text-theme-xs text-error-500'>{form.errors.searchTags}</p>
        ) : null}
        <TextareaField
          label='توضیح'
          hint='خط جدید و فاصله‌ها همان‌طور در صفحهٔ محصول دیده می‌شوند'
          rows={8}
          value={form.values.description}
          onChange={(value) => void form.setFieldValue('description', value)}
        />
        <TextField
          label='ناشر (نمایش فروشگاه اگر برند تأمین‌کننده باشد خالی می‌شود)'
          value={form.values.publisher}
          onChange={(value) => void form.setFieldValue('publisher', value)}
        />
        <SelectField
          label='دسته'
          value={form.values.catalogGroupId}
          onChange={(value) => void form.setFieldValue('catalogGroupId', value)}
          options={[
            { value: '', label: 'بدون دسته' },
            ...groups.map((group) => ({ value: String(group.id), label: group.title }))
          ]}
        />
        <CatalogImageField
          label='تصویر محصول'
          alt={form.values.title}
          guide={catalogImageGuide(
            groups.find((group) => String(group.id) === form.values.catalogGroupId)?.slug,
            product.fulfillmentType
          )}
          value={form.values.imageUrl}
          onChange={(value) => void form.setFieldValue('imageUrl', value)}
        />
        <TextField
          label='ترتیب'
          value={form.values.sortOrder}
          onChange={(value) => void form.setFieldValue('sortOrder', value)}
        />
        <SelectField
          label='وضعیت'
          value={form.values.status}
          onChange={(value) => void form.setFieldValue('status', value as AdminProduct['status'])}
          options={[
            { value: 'active', label: 'فعال' },
            { value: 'hidden', label: 'مخفی' },
            { value: 'draft', label: 'پیش‌نویس' }
          ]}
        />
        <TextareaField
          label='یادداشت ریجن (فارسی)'
          hint='بالای فرم خرید نمایش داده می‌شود. قیمت عددی ننویسید.'
          rows={3}
          value={form.values.regionNote}
          onChange={(value) => void form.setFieldValue('regionNote', value)}
        />
        {product.excludedReason ? (
          <p className='text-theme-sm text-error-500'>مستثنا: {product.excludedReason}</p>
        ) : null}
        {product.syncedAt ? <p className='text-theme-xs text-gray-500'>آخرین همگام: {product.syncedAt}</p> : null}
        {product.excludedReason && !['credential_field', 'login_required'].includes(product.excludedReason) ? (
          <Switch
            label='لغو مستثنا و نمایش در ویترین'
            checked={form.values.clearExcluded}
            onChange={(next) => void form.setFieldValue('clearExcluded', next)}
          />
        ) : null}
        <TextField
          label='محصول جایگزین (شناسه)'
          hint='اختیاری. اگر این محصول مخفی شود، بازدیدکننده به آن محصول ریدایرکت می‌شود.'
          dir='ltr'
          className='text-left'
          value={form.values.substituteProductId}
          onChange={(value) => void form.setFieldValue('substituteProductId', value.replace(/[^\d]/g, ''))}
        />
        <Switch
          label='نمایش در فروشگاه'
          checked={form.values.visible}
          onChange={(next) => void form.setFieldValue('visible', next)}
        />
        <Switch
          label='محبوب در صفحهٔ اصلی'
          checked={form.values.isPopular}
          onChange={(next) => void form.setFieldValue('isPopular', next)}
        />
        <div className='space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/5'>
          <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>سود این محصول</h3>
          <p className='text-theme-sm leading-6 text-gray-500'>
            سود سراسری الان {toFaDigits(product.globalMarginPercent ?? '5')}٪ است. اگر اینجا عددی بگذاری، همهٔ بسته‌هایی که
            سود اختصاصی ندارند با همین درصد قیمت می‌خورند. این تخفیف نیست؛ حاشیهٔ سود روی قیمت دلار است و با تغییر نرخ دلار
            دوباره حساب می‌شود.
          </p>
          <TextField
            name='marginPercent'
            label='سود محصول (٪)'
            hint='خالی = سود سراسری. مثلاً ۴ یعنی این محصول ۴٪ سود می‌گیرد نه ۵٪ فروشگاه'
            dir='ltr'
            className='text-left'
            value={form.values.marginPercent}
            onChange={(value) => void form.setFieldValue('marginPercent', value.replace(/[^\d.]/g, ''))}
            onBlur={() => form.setFieldTouched('marginPercent', true)}
            previewState={form.touched.marginPercent && form.errors.marginPercent ? 'error' : 'default'}
          />
          {form.touched.marginPercent && form.errors.marginPercent ? (
            <p className='text-theme-xs text-error-500'>{form.errors.marginPercent}</p>
          ) : null}
          <Switch
            label='سود اختصاصی بسته‌ها را بردار تا همه از سود این محصول پیروی کنند'
            checked={form.values.clearSkuMargins}
            onChange={(next) => void form.setFieldValue('clearSkuMargins', next)}
          />
        </div>
        <div className='flex flex-wrap gap-3'>
          <Button type='submit' loading={busy === 'save'} disabled={busy !== null}>
            ذخیره محصول
          </Button>
          <Button type='button' variant='outline' loading={busy === 'hide'} disabled={busy !== null} onClick={() => void hide()}>
            مخفی کردن
          </Button>
          {shop2Refresh ? (
            <Button
              type='button'
              variant='outline'
              loading={busy === 'refresh'}
              disabled={busy !== null}
              onClick={() => void refreshShop2()}
            >
              به‌روز کردن فیلدهای بازیکن Shop2
            </Button>
          ) : null}
        </div>
      </form>
      <div className='space-y-4'>
        <h3 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>بسته‌ها</h3>
        {product.giftProxy ? (
          <p className='text-theme-sm leading-6 text-gray-500'>
            این محصول در فروشگاه با همین نام و دسته دیده می‌شود. هر بسته با قیمت دلاری خودمان قیمت‌گذاری می‌شود و
            موقع تحویل، گیفت‌کارت انتخاب‌شده برای همان پلتفرم و ریجن سفارش می‌شود.
          </p>
        ) : null}
        {(product.skus ?? []).map((sku) => (
          <SkuEditRow
            key={sku.id}
            sku={sku}
            giftProxy={Boolean(product.giftProxy)}
            giftCards={giftCards}
            inheritMargin={product.marginPercent ?? product.globalMarginPercent ?? '5'}
            globalMargin={product.globalMarginPercent ?? '5'}
            onSaved={(text) => {
              setMessage({ variant: 'success', text });
              router.refresh();
            }}
            onError={(text) => setMessage({ variant: 'error', text })}
          />
        ))}
        {product.giftProxy ? (
          <GiftPackageForm
            productId={product.id}
            giftCards={giftCards}
            onSaved={(text) => {
              setMessage({ variant: 'success', text });
              router.refresh();
            }}
            onError={(text) => setMessage({ variant: 'error', text })}
          />
        ) : null}
      </div>
    </div>
  );
}

function SkuEditRow({
  sku,
  giftProxy = false,
  giftCards = [],
  inheritMargin,
  globalMargin,
  onSaved,
  onError
}: {
  sku: AdminSku;
  giftProxy?: boolean;
  giftCards?: GiftCardOption[];
  inheritMargin: string;
  globalMargin: string;
  onSaved: (text: string) => void;
  onError: (text: string) => void;
}) {
  const form = useFormik({
    enableReinitialize: true,
    initialValues: {
      name: sku.name,
      nameEn: sku.nameEn ?? '',
      priceManual: Boolean(sku.priceManual),
      marginPercent: sku.marginPercent ?? '',
      price: String(sku.price ?? ''),
      costUsd: sku.costUsd && sku.costUsd !== '0' ? sku.costUsd : '',
      giftSkuId: sku.giftSkuId ? String(sku.giftSkuId) : '',
      salePrice: sku.salePrice ? String(sku.salePrice) : '',
      salePercent: sku.salePrice ? salePercentFromPrices(sku.price, sku.salePrice) : '',
      saleEndsAt: saleDateValue(sku.saleEndsAt),
      visible: sku.visible
    },
    validationSchema: skuSchema,
    onSubmit: async (values) => {
      const list = values.priceManual ? roundTomanInput(values.price) || sku.price : sku.price;
      const percent = Number((values.salePercent || '').trim());
      let saleDigits = onlyDigits(values.salePrice);
      if (!saleDigits && Number.isFinite(percent) && percent >= 1 && list > 0) {
        const computed = salePriceFromPercent(list, percent);
        saleDigits = computed > 0 ? String(computed) : '';
      }
      if (giftProxy) {
        const usd = values.costUsd.trim();
        const giftId = Number(values.giftSkuId);
        if (!usd || Number(usd) <= 0) {
          onError('قیمت دلاری بسته را بنویس.');
          return;
        }
        if (!giftId) {
          onError('گیفت‌کارت معادل این بسته را انتخاب کن.');
          return;
        }
        const result = await updateSku(sku.id, {
          name: values.name.trim(),
          nameEn: values.nameEn.trim(),
          visible: values.visible,
          costUsd: usd,
          giftSkuId: giftId,
          marginPercent: values.marginPercent.trim() === '' ? null : values.marginPercent.trim(),
          salePrice: saleDigits ? roundTomanInput(saleDigits) : null,
          saleEndsAt: values.saleEndsAt.trim() || null
        });
        if (!result.ok) {
          onError(result.message);
          return;
        }
        onSaved('بسته ذخیره شد.');
        return;
      }
      const payload: Partial<AdminSku> = {
        name: values.name.trim(),
        nameEn: values.nameEn.trim(),
        visible: values.visible,
        salePrice: saleDigits ? roundTomanInput(saleDigits) : null,
        saleEndsAt: values.saleEndsAt.trim() || null
      };
      if (values.priceManual) {
        payload.price = roundTomanInput(values.price);
      } else {
        payload.marginPercent = values.marginPercent.trim() === '' ? null : values.marginPercent.trim();
      }
      const result = await updateSku(sku.id, payload);
      if (!result.ok) {
        onError(result.message);
        return;
      }
      onSaved('بسته ذخیره شد.');
    }
  });

  return (
    <form className='space-y-3 rounded-xl border border-gray-200 p-4 dark:border-white/5' onSubmit={form.handleSubmit}>
      <p className='text-theme-sm text-gray-500'>
        {sku.code} · {sku.fulfillmentType || '—'}
        {sku.status !== 'active' ? ` · ${sku.status}` : ''}
      </p>
      <div className='grid gap-2 sm:grid-cols-2'>
        <p className='text-theme-sm text-gray-800 dark:text-white/90'>
          قیمت فروش فعلی: {formatToman(sku.sellPrice ?? sku.price)}
        </p>
        <p className='text-theme-sm text-gray-800 dark:text-white/90'>
          قیمت خرید: {sku.purchaseToman ? formatToman(sku.purchaseToman) : '—'} · {formatUsd(sku.purchaseUsd)}
        </p>
      </div>
      {sku.saleActive ? (
        <p className='text-theme-xs text-success-500'>تخفیف فعال است تا پایان تاریخ انتخاب‌شده.</p>
      ) : sku.salePrice && sku.saleEndsAt ? (
        <p className='text-theme-xs text-warning-500'>تخفیف این بسته منقضی شده است.</p>
      ) : null}
      {giftProxy ? (
        <>
          <TextField
            name='costUsd'
            label='قیمت دلاری بسته'
            hint='تومان فروش از همین دلار و نرخ فروشگاه حساب می‌شود'
            dir='ltr'
            className='text-left'
            value={form.values.costUsd}
            onChange={(value) => void form.setFieldValue('costUsd', value.replace(/[^\d.]/g, ''))}
          />
          <SelectField
            label='گیفت‌کارت معادل'
            value={form.values.giftSkuId || 'none'}
            onChange={(value) => void form.setFieldValue('giftSkuId', value === 'none' ? '' : value)}
            options={[
              { value: 'none', label: giftCards.length ? 'انتخاب کن' : 'گیفت‌کارتی برای این پلتفرم نیست' },
              ...giftCards.map((card) => ({
                value: String(card.id),
                label: [card.productTitle, card.name, card.region, card.costUsd ? `$${card.costUsd}` : '']
                  .filter(Boolean)
                  .join(' · ')
              }))
            ]}
          />
        </>
      ) : (
        <Switch
          label='قفل تومان دستی (با تغییر نرخ دلار عوض نشود)'
          checked={form.values.priceManual}
          onChange={(next) => void form.setFieldValue('priceManual', next)}
        />
      )}
      {!giftProxy && form.values.priceManual ? (
        <>
          <TextField
            name='price'
            label='قیمت فروش (تومان)'
            hint='قیمت قفل می‌شود و از فرمول سود خارج می‌گردد'
            value={form.values.price}
            onChange={(value) => void form.setFieldValue('price', onlyDigits(value))}
            onBlur={() => form.setFieldTouched('price', true)}
            previewState={form.touched.price && form.errors.price ? 'error' : 'default'}
          />
          {form.touched.price && form.errors.price ? (
            <p className='text-theme-xs text-error-500'>{form.errors.price}</p>
          ) : null}
        </>
      ) : null}
      {(giftProxy || !form.values.priceManual) && (
        <>
          <TextField
            name='marginPercent'
            label='سود این بسته (٪)'
            hint={`خالی = سود محصول/سراسری (${toFaDigits(inheritMargin)}٪). سراسری فروشگاه ${toFaDigits(globalMargin)}٪ است`}
            dir='ltr'
            className='text-left'
            value={form.values.marginPercent}
            onChange={(value) => void form.setFieldValue('marginPercent', value.replace(/[^\d.]/g, ''))}
            onBlur={() => form.setFieldTouched('marginPercent', true)}
            previewState={form.touched.marginPercent && form.errors.marginPercent ? 'error' : 'default'}
          />
          {form.touched.marginPercent && form.errors.marginPercent ? (
            <p className='text-theme-xs text-error-500'>{form.errors.marginPercent}</p>
          ) : null}
          {sku.purchaseToman ? (
            <p className='text-theme-xs text-gray-500'>
              پیش‌نمایش فروش با این سود:{' '}
              {formatToman(
                previewSellToman(
                  sku.purchaseToman,
                  parseMargin(form.values.marginPercent) ?? Number(inheritMargin)
                )
              )}
            </p>
          ) : null}
        </>
      )}
      <TextField
        name='salePercent'
        label='تخفیف (٪)'
        hint='مثلاً ۲۰ یعنی بیست درصد تخفیف. حداکثر ۷۰٪. با ذخیره روی فروش ویژهٔ هوم می‌آید'
        dir='ltr'
        className='text-left'
        value={form.values.salePercent}
        onChange={(value) => {
          const raw = value.replace(/[^\d]/g, '');
          void form.setFieldValue('salePercent', raw);
          const amount = Number(raw);
          const list = form.values.priceManual ? roundTomanInput(form.values.price) || sku.price : sku.price;
          if (Number.isFinite(amount) && amount >= 1 && amount <= 70 && list > 0) {
            void form.setFieldValue('salePrice', String(salePriceFromPercent(list, amount)));
            return;
          }
          if (!raw) {
            void form.setFieldValue('salePrice', '');
          }
        }}
        onBlur={() => form.setFieldTouched('salePercent', true)}
        previewState={form.touched.salePercent && form.errors.salePercent ? 'error' : 'default'}
      />
      {form.touched.salePercent && form.errors.salePercent ? (
        <p className='text-theme-xs text-error-500'>{form.errors.salePercent}</p>
      ) : null}
      <TextField
        name='salePrice'
        label='قیمت تخفیف (تومان)'
        hint='اگر درصد زدی خودش پر می‌شود. باید کمتر از قیمت فروش باشد'
        value={form.values.salePrice}
        onChange={(value) => {
          const digits = onlyDigits(value);
          void form.setFieldValue('salePrice', digits);
          const sale = roundTomanInput(digits);
          const list = form.values.priceManual ? roundTomanInput(form.values.price) || sku.price : sku.price;
          void form.setFieldValue('salePercent', salePercentFromPrices(list, sale));
        }}
        onBlur={() => form.setFieldTouched('salePrice', true)}
        previewState={form.touched.salePrice && form.errors.salePrice ? 'error' : 'default'}
      />
      {form.touched.salePrice && form.errors.salePrice ? (
        <p className='text-theme-xs text-error-500'>{form.errors.salePrice}</p>
      ) : null}
      <DateField
        name='saleEndsAt'
        label='پایان تخفیف'
        hint='برای تخفیف الزامی است'
        placeholder='تاریخ پایان را انتخاب کنید'
        maxYear={jalaliToday().jy + 5}
        value={form.values.saleEndsAt}
        onChange={(value) => void form.setFieldValue('saleEndsAt', value)}
        onBlur={() => form.setFieldTouched('saleEndsAt', true)}
        previewState={form.touched.saleEndsAt && form.errors.saleEndsAt ? 'error' : 'default'}
      />
      {form.touched.saleEndsAt && form.errors.saleEndsAt ? (
        <p className='text-theme-xs text-error-500'>{form.errors.saleEndsAt}</p>
      ) : null}
      <Button
        type='button'
        variant='outline'
        onClick={() => {
          void form.setFieldValue('salePrice', '');
          void form.setFieldValue('salePercent', '');
          void form.setFieldValue('saleEndsAt', '');
        }}
      >
        حذف تخفیف
      </Button>
      <TextField
        name='name'
        label='نام فارسی بسته'
        hint='برای جستجو و سئو'
        value={form.values.name}
        onChange={(value) => void form.setFieldValue('name', value)}
        onBlur={() => form.setFieldTouched('name', true)}
        previewState={form.touched.name && form.errors.name ? 'error' : 'default'}
      />
      {form.touched.name && form.errors.name ? (
        <p className='text-theme-xs text-error-500'>{form.errors.name}</p>
      ) : null}
      <TextField
        name='nameEn'
        label='نام انگلیسی بسته'
        hint='برای جستجو و سئو'
        dir='ltr'
        className='text-left'
        placeholder='60 UC'
        value={form.values.nameEn}
        onChange={(value) => void form.setFieldValue('nameEn', value)}
        onBlur={() => form.setFieldTouched('nameEn', true)}
        previewState={form.touched.nameEn && form.errors.nameEn ? 'error' : 'default'}
      />
      {form.touched.nameEn && form.errors.nameEn ? (
        <p className='text-theme-xs text-error-500'>{form.errors.nameEn}</p>
      ) : null}
      <Switch
        label='نمایش بسته'
        checked={form.values.visible}
        onChange={(next) => void form.setFieldValue('visible', next)}
      />
      <p className='text-theme-xs text-gray-500'>شناسه تأمین‌کننده: {toFaDigits(sku.id)}</p>
      <Button type='submit' variant='outline' loading={form.isSubmitting}>
        ذخیره بسته
      </Button>
    </form>
  );
}

function GiftPackageForm({
  productId,
  giftCards,
  onSaved,
  onError
}: {
  productId: number;
  giftCards: GiftCardOption[];
  onSaved: (text: string) => void;
  onError: (text: string) => void;
}) {
  const form = useFormik({
    initialValues: { name: '', nameEn: '', costUsd: '', giftSkuId: '' },
    onSubmit: async (values, helpers) => {
      const usd = values.costUsd.trim();
      const giftId = Number(values.giftSkuId);
      if (!values.name.trim() || !values.nameEn.trim()) {
        onError('نام فارسی و انگلیسی بسته لازم است.');
        return;
      }
      if (!usd || Number(usd) <= 0) {
        onError('قیمت دلاری بسته را بنویس.');
        return;
      }
      if (!giftId) {
        onError('گیفت‌کارت معادل این بسته را انتخاب کن.');
        return;
      }
      const result = await createGiftSku(productId, {
        name: values.name.trim(),
        nameEn: values.nameEn.trim(),
        costUsd: usd,
        giftSkuId: giftId
      });
      if (!result.ok) {
        onError(result.message);
        return;
      }
      helpers.resetForm();
      onSaved('بسته اضافه شد.');
    }
  });

  return (
    <form className='space-y-3 rounded-xl border border-dashed border-gray-300 p-4 dark:border-white/10' onSubmit={form.handleSubmit}>
      <h4 className='text-theme-sm font-medium text-gray-800 dark:text-white/90'>بستهٔ جدید</h4>
      <TextField label='نام فارسی بسته' value={form.values.name} onChange={(value) => void form.setFieldValue('name', value)} />
      <TextField
        label='نام انگلیسی بسته'
        dir='ltr'
        className='text-left'
        value={form.values.nameEn}
        onChange={(value) => void form.setFieldValue('nameEn', value)}
      />
      <TextField
        label='قیمت دلاری'
        dir='ltr'
        className='text-left'
        hint='مثلاً ۶۰ برای گیفت ۶۰ دلاری همان پلتفرم'
        value={form.values.costUsd}
        onChange={(value) => void form.setFieldValue('costUsd', value.replace(/[^\d.]/g, ''))}
      />
      <SelectField
        label='گیفت‌کارت معادل'
        value={form.values.giftSkuId || 'none'}
        onChange={(value) => void form.setFieldValue('giftSkuId', value === 'none' ? '' : value)}
        options={[
          { value: 'none', label: giftCards.length ? 'انتخاب کن' : 'گیفت‌کارتی برای این پلتفرم در فروشگاه نیست' },
          ...giftCards.map((card) => ({
            value: String(card.id),
            label: [card.productTitle, card.name, card.region, card.costUsd ? `$${card.costUsd}` : ''].filter(Boolean).join(' · ')
          }))
        ]}
      />
      <Button type='submit' variant='outline' loading={form.isSubmitting}>
        افزودن بسته
      </Button>
    </form>
  );
}
