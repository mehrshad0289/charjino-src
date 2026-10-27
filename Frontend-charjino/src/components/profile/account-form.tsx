'use client';

import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import { updateDisplayName } from '@/actions/auth.actions';
import { Button, TextField } from '@/components/ui';
import { toFaDigits } from '@/lib/fa';
import { notify } from '@/lib/toast';
import { profileDisplayNameSchema } from '@/lib/validation/profile';
import { ProfileHeading } from './profile-chrome';
import { ProfileSubnav } from './profile-subnav';
import styles from './profile.module.css';

type AccountFormProps = {
  phoneNumber?: string | null;
  name?: string | null;
};

export function AccountForm({ phoneNumber, name }: AccountFormProps) {
  const router = useRouter();
  const formik = useFormik({
    enableReinitialize: true,
    initialValues: { name: name?.trim() ?? '' },
    validationSchema: profileDisplayNameSchema,
    onSubmit: async (values, helpers) => {
      const result = await updateDisplayName(values.name);
      if (!result.ok) {
        notify.error(result.message);
        helpers.setStatus(result.message);
        return;
      }
      notify.success('نام نمایشی ذخیره شد.');
      router.refresh();
    },
  });

  return (
    <>
      <ProfileSubnav />
      <ProfileHeading title="اطلاعات حساب" />
      <form onSubmit={formik.handleSubmit} className={`${styles.panel} flex flex-col gap-4`}>
        <TextField
          label="شماره موبایل"
          value={phoneNumber ? toFaDigits(phoneNumber) : '—'}
          previewState="disabled"
          hint="شماره با پیامک تأیید شده و در این فاز قابل ویرایش نیست."
        />
        <TextField
          label="نام نمایشی"
          placeholder="مثلاً علی"
          value={formik.values.name}
          onChange={(value) => void formik.setFieldValue('name', value)}
          onBlur={() => formik.setFieldTouched('name')}
          hint={formik.touched.name ? formik.errors.name : undefined}
          previewState={formik.touched.name && formik.errors.name ? 'error' : 'default'}
          maxLength={40}
        />
        {typeof formik.status === 'string' ? <p className="text-sm text-error-200">{formik.status}</p> : null}
        <Button type="submit" loading={formik.isSubmitting} disabled={formik.isSubmitting}>
          ذخیره
        </Button>
      </form>
    </>
  );
}
