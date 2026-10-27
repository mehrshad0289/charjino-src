'use client';

import { useRouter } from 'next/navigation';
import { useFormik } from 'formik';
import { replyToTicket } from '@/actions/tickets.actions';
import { Button, TextareaField } from '@/components/ui';
import { notify } from '@/lib/toast';
import { ticketReplySchema } from '@/lib/validation/profile';

export function TicketReplyForm({ ticketId }: { ticketId: string }) {
  const router = useRouter();
  const formik = useFormik({
    initialValues: { body: '' },
    validationSchema: ticketReplySchema,
    onSubmit: async (values, helpers) => {
      const result = await replyToTicket(ticketId, values.body);
      if (!result.ok) {
        notify.error(result.message);
        helpers.setStatus(result.message);
        return;
      }
      notify.success('پیام ثبت شد.');
      helpers.resetForm();
      router.refresh();
    },
  });

  return (
    <form onSubmit={formik.handleSubmit} className="flex flex-col gap-3">
      <TextareaField
        label="پاسخ شما"
        placeholder="پیام را بنویسید"
        rows={4}
        value={formik.values.body}
        onChange={(value) => void formik.setFieldValue('body', value)}
        onBlur={() => formik.setFieldTouched('body')}
        hint={formik.touched.body ? formik.errors.body : undefined}
        previewState={formik.touched.body && formik.errors.body ? 'error' : 'default'}
      />
      {typeof formik.status === 'string' ? <p className="text-sm text-error-200">{formik.status}</p> : null}
      <Button type="submit" loading={formik.isSubmitting} disabled={formik.isSubmitting}>
        ارسال
      </Button>
    </form>
  );
}
