'use client';

import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import * as Yup from 'yup';
import { createCoupon, updateCoupon } from '@/actions/coupons.actions';
import { Button, DateField, SelectField, Switch, TextField, TextareaField } from '@/components/ui';
import { useToastMessage } from '@/hooks/use-toast-message';
import { jalaliToday } from '@/lib/jalali';
import type { AdminCoupon } from '@/types/coupons';

const schema = Yup.object({
  name: Yup.string().trim().required('نام کد لازم است').max(80),
  code: Yup.string()
    .trim()
    .required('کد لازم است')
    .min(3, 'حداقل ۳ کاراکتر')
    .max(32)
    .matches(/^[A-Za-z0-9_-]+$/, 'فقط حروف انگلیسی، عدد، خط تیره'),
  type: Yup.string().oneOf(['amount', 'percentage']).required(),
  amount: Yup.string().when('type', {
    is: 'amount',
    then: (s) => s.trim().required('مبلغ تخفیف لازم است'),
    otherwise: (s) => s
  }),
  percentage: Yup.string().when('type', {
    is: 'percentage',
    then: (s) =>
      s
        .trim()
        .required('درصد لازم است')
        .test('pct', 'درصد بین ۱ تا ۱۰۰ باشد', (value) => {
          const n = Number(value);
          return Number.isInteger(n) && n >= 1 && n <= 100;
        }),
    otherwise: (s) => s
  }),
  maxPercentageAmount: Yup.string().when('type', {
    is: 'percentage',
    then: (s) => s.trim().required('سقف تومان تخفیف درصدی لازم است'),
    otherwise: (s) => s
  }),
  minOrderToman: Yup.string().when('type', {
    is: 'amount',
    then: (s) =>
      s
        .trim()
        .required('حداقل خرید برای تخفیف مبلغی لازم است')
        .test('min-gte-amount', 'حداقل خرید نباید از مبلغ تخفیف کمتر باشد', function minGteAmount(value) {
          const min = Number(value);
          const amount = Number(this.parent.amount);
          if (!Number.isFinite(min) || !Number.isFinite(amount) || amount < 1) return true;
          return min >= amount;
        }),
    otherwise: (s) => s
  }),
  usageLimitPerUser: Yup.string().trim().required('سهمیه هر کاربر لازم است'),
  maxUses: Yup.string(),
  expiredAt: Yup.string().required('تاریخ انقضا لازم است'),
  description: Yup.string().max(500),
  isActive: Yup.boolean()
});

type CouponEditorScreenProps = {
  coupon?: AdminCoupon;
};

export function CouponEditorScreen({ coupon }: CouponEditorScreenProps) {
  const router = useRouter();
  const [, setMessage] = useToastMessage();
  const formik = useFormik({
    enableReinitialize: true,
    initialValues: {
      name: coupon?.name ?? '',
      code: coupon?.code ?? '',
      type: coupon?.type ?? 'percentage',
      amount: coupon?.amount ? String(coupon.amount) : '',
      percentage: coupon?.percentage ? String(coupon.percentage) : '',
      maxPercentageAmount: coupon?.maxPercentageAmount ? String(coupon.maxPercentageAmount) : '',
      minOrderToman: coupon?.minOrderToman ? String(coupon.minOrderToman) : '',
      usageLimitPerUser: String(coupon?.usageLimitPerUser ?? 1),
      maxUses: coupon?.maxUses ? String(coupon.maxUses) : '',
      expiredAt: coupon?.expiredAt ?? '',
      description: coupon?.description ?? '',
      isActive: coupon?.isActive ?? true
    },
    validationSchema: schema,
    onSubmit: async (values, helpers) => {
      const type = values.type as 'amount' | 'percentage';
      const payload: Record<string, unknown> = {
        name: values.name.trim(),
        code: values.code.trim(),
        type,
        usageLimitPerUser: Number(values.usageLimitPerUser) || 1,
        expiredAt: values.expiredAt,
        description: values.description.trim() || undefined,
        isActive: values.isActive
      };
      if (type === 'amount') {
        payload.amount = Number(values.amount);
        payload.minOrderToman = Number(values.minOrderToman);
      } else {
        payload.percentage = Number(values.percentage);
        payload.maxPercentageAmount = Number(values.maxPercentageAmount);
        payload.minOrderToman = values.minOrderToman.trim() ? Number(values.minOrderToman) : 0;
      }
      if (values.maxUses.trim()) payload.maxUses = Number(values.maxUses);
      const result = coupon ? await updateCoupon(coupon.id, payload) : await createCoupon(payload);
      if (!result.ok) {
        setMessage({ variant: 'error', text: result.message });
        helpers.setSubmitting(false);
        return;
      }
      setMessage({ variant: 'success', text: coupon ? 'کد تخفیف به‌روز شد.' : 'کد تخفیف ذخیره شد.' });
      router.push('/coupons');
      router.refresh();
    }
  });

  const percent = formik.values.type === 'percentage';

  return (
    <form className='space-y-4' onSubmit={formik.handleSubmit}>
      <TextField
        label='نام'
        value={formik.values.name}
        hint={formik.touched.name ? formik.errors.name : undefined}
        onChange={(value) => void formik.setFieldValue('name', value)}
      />
      <TextField
        label='کد'
        value={formik.values.code}
        hint={formik.touched.code ? formik.errors.code : undefined}
        onChange={(value) => void formik.setFieldValue('code', value.toUpperCase())}
      />
      <SelectField
        label='نوع'
        value={formik.values.type}
        onChange={(value) => void formik.setFieldValue('type', value)}
        options={[
          { value: 'percentage', label: 'درصدی (با سقف تومان)' },
          { value: 'amount', label: 'مبلغی (با حداقل خرید)' }
        ]}
      />
      {percent ? (
        <>
          <TextField
            label='درصد'
            value={formik.values.percentage}
            hint={formik.touched.percentage ? formik.errors.percentage : 'مثلاً ۵۰ یعنی نیم‌بها'}
            onChange={(value) => void formik.setFieldValue('percentage', value)}
          />
          <TextField
            label='سقف تخفیف (تومان)'
            value={formik.values.maxPercentageAmount}
            hint={
              formik.touched.maxPercentageAmount
                ? formik.errors.maxPercentageAmount
                : 'اگر ۵۰٪ از سقف بیشتر شد، فقط همین مبلغ کم می‌شود'
            }
            onChange={(value) => void formik.setFieldValue('maxPercentageAmount', value)}
          />
          <TextField
            label='حداقل خرید (تومان) — اختیاری'
            value={formik.values.minOrderToman}
            hint={formik.touched.minOrderToman ? formik.errors.minOrderToman : undefined}
            onChange={(value) => void formik.setFieldValue('minOrderToman', value)}
          />
        </>
      ) : (
        <>
          <TextField
            label='مبلغ تخفیف (تومان)'
            value={formik.values.amount}
            hint={formik.touched.amount ? formik.errors.amount : undefined}
            onChange={(value) => void formik.setFieldValue('amount', value)}
          />
          <TextField
            label='حداقل خرید (تومان)'
            value={formik.values.minOrderToman}
            hint={formik.touched.minOrderToman ? formik.errors.minOrderToman : 'کمتر از این مبلغ، کد اعمال نمی‌شود'}
            onChange={(value) => void formik.setFieldValue('minOrderToman', value)}
          />
        </>
      )}
      <DateField
        label='انقضا'
        value={formik.values.expiredAt}
        hint={formik.touched.expiredAt ? formik.errors.expiredAt : undefined}
        maxYear={jalaliToday().jy + 10}
        onChange={(value) => void formik.setFieldValue('expiredAt', value)}
      />
      <TextField
        label='سهمیه هر کاربر'
        value={formik.values.usageLimitPerUser}
        hint={formik.touched.usageLimitPerUser ? formik.errors.usageLimitPerUser : undefined}
        onChange={(value) => void formik.setFieldValue('usageLimitPerUser', value)}
      />
      <TextField
        label='سقف کل استفاده — خالی یعنی نامحدود'
        value={formik.values.maxUses}
        onChange={(value) => void formik.setFieldValue('maxUses', value)}
      />
      <TextareaField
        label='توضیح'
        rows={3}
        value={formik.values.description}
        onChange={(value) => void formik.setFieldValue('description', value)}
      />
      <Switch label='فعال' checked={formik.values.isActive} onChange={(checked) => void formik.setFieldValue('isActive', checked)} />
      <Button type='submit' loading={formik.isSubmitting}>
        {coupon ? 'ذخیره' : 'ثبت کد تخفیف'}
      </Button>
    </form>
  );
}
