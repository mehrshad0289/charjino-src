import * as Yup from 'yup';

export const profileDisplayNameSchema = Yup.object({
  name: Yup.string()
    .trim()
    .required('نام نمایشی را وارد کنید.')
    .min(2, 'نام نمایشی کوتاه است.')
    .max(40, 'نام نمایشی طولانی است.'),
});

export const ticketCreateSchema = Yup.object({
  subject: Yup.string().trim().required('موضوع را بنویسید.').min(4, 'موضوع کوتاه است.').max(160, 'موضوع طولانی است.'),
  body: Yup.string().trim().required('متن تیکت را بنویسید.').min(8, 'متن تیکت کوتاه است.').max(4000, 'متن تیکت طولانی است.'),
  category: Yup.string().oneOf(['order', 'payment', 'delivery', 'account', 'other']).required(),
  orderNumber: Yup.string().trim().max(32, 'شماره سفارش نامعتبر است.'),
});

export const ticketReplySchema = Yup.object({
  body: Yup.string().trim().required('متن پیام را بنویسید.').min(2, 'متن پیام کوتاه است.').max(4000, 'متن پیام طولانی است.'),
});
