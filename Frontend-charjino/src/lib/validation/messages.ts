export const VALIDATION_MESSAGES = {
  required: 'این فیلد اجباری است',
  integer: 'فقط عدد صحیح وارد کنید',
  titleMax: 'عنوان نمی‌تواند بیشتر از ۲۵۵ کاراکتر باشد',
  pickCategory: 'یک دسته‌بندی انتخاب کنید.',
  pickProvince: 'استان را انتخاب کنید.',
  pickCity: 'شهر را انتخاب کنید.',
} as const;

export function minMessage(label: string, min: number) {
  return `${label} نمی‌تواند کمتر از ${min.toLocaleString('fa-IR')} باشد`;
}

export function maxMessage(label: string, max: number) {
  return `${label} نمی‌تواند بیشتر از ${max.toLocaleString('fa-IR')} باشد`;
}
