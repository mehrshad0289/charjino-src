'use client';

import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import { createTicket } from '@/actions/tickets.actions';
import { Button, SelectField, TextField, TextareaField } from '@/components/ui';
import { notify } from '@/lib/toast';
import { ROUTES } from '@/lib/routes';
import { TICKET_CATEGORY_OPTIONS } from '@/lib/ticket-labels';
import { ticketCreateSchema } from '@/lib/validation/profile';
import { ProfileBack, ProfileHeading } from './profile-chrome';
import { ProfileSubnav } from './profile-subnav';
import styles from './profile.module.css';

export function TicketCreateForm() {
  const router = useRouter();
  const formik = useFormik({
    initialValues: { subject: '', body: '', category: 'other', orderNumber: '' },
    validationSchema: ticketCreateSchema,
    onSubmit: async (values, helpers) => {
      const result = await createTicket({
        subject: values.subject,
        body: values.body,
        category: values.category,
        orderNumber: values.orderNumber,
      });
      if (!result.ok) {
        notify.error(result.message);
        helpers.setStatus(result.message);
        return;
      }
      notify.success('تیکت ثبت شد.');
      router.push(ROUTES.profileTicket(result.data.id));
      router.refresh();
    },
  });

  return (
    <>
      <ProfileSubnav />
      <ProfileBack href={ROUTES.profileTickets} label="بازگشت به تیکت‌ها" />
      <ProfileHeading title="تیکت جدید" />
      <form onSubmit={formik.handleSubmit} className={`${styles.panel} flex flex-col gap-4`}>
        <TextField
          label="موضوع"
          placeholder="مثلاً سفارش پرداخت‌شده"
          value={formik.values.subject}
          onChange={(value) => void formik.setFieldValue('subject', value)}
          onBlur={() => formik.setFieldTouched('subject')}
          hint={formik.touched.subject ? formik.errors.subject : undefined}
          previewState={formik.touched.subject && formik.errors.subject ? 'error' : 'default'}
        />
        <SelectField
          label="دسته‌بندی"
          placeholder="انتخاب دسته"
          variant="shop"
          searchable={false}
          showItemIcon={false}
          options={[...TICKET_CATEGORY_OPTIONS]}
          value={formik.values.category}
          onChange={(value) => void formik.setFieldValue('category', value)}
        />
        <TextField
          label="شماره سفارش (اختیاری)"
          placeholder="CH…"
          value={formik.values.orderNumber}
          onChange={(value) => void formik.setFieldValue('orderNumber', value)}
          hint={formik.touched.orderNumber ? formik.errors.orderNumber : undefined}
          previewState={formik.touched.orderNumber && formik.errors.orderNumber ? 'error' : 'default'}
        />
        <TextareaField
          label="متن"
          placeholder="شرح درخواست"
          rows={6}
          value={formik.values.body}
          onChange={(value) => void formik.setFieldValue('body', value)}
          onBlur={() => formik.setFieldTouched('body')}
          hint={formik.touched.body ? formik.errors.body : undefined}
          previewState={formik.touched.body && formik.errors.body ? 'error' : 'default'}
        />
        {typeof formik.status === 'string' ? <p className="text-sm text-error-200">{formik.status}</p> : null}
        <Button type="submit" loading={formik.isSubmitting} disabled={formik.isSubmitting}>
          ثبت تیکت
        </Button>
      </form>
    </>
  );
}
