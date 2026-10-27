const PASSWORD =
  /(وارد|بفرست|ارسال|بدهید|بده|لازم|نیاز|فیلد).{0,40}(رمز|پسورد|password)|(رمز|پسورد|password).{0,40}(وارد|بفرست|ارسال)/u;
const DELIVERY = /تحویل\s*(فوری|آنی|در\s*\d+\s*(دقیقه|ساعت))/u;
const PRICE = /\d[\d٬,.]{2,}\s*(تومان|ریال)|(?:USD|\$)\s*\d/u;

export function lintBuyGuideText(text: string) {
  const warnings: string[] = [];
  if (text.includes('چارژینو')) warnings.push('نام برند را «شارژینو» بنویسید نه «چارژینو».');
  if (PASSWORD.test(text) && !/هرگز.{0,80}(رمز|پسورد)|درخواست نمی/u.test(text)) {
    warnings.push('متن نباید از کاربر رمز عبور بخواهد.');
  }
  if (DELIVERY.test(text)) warnings.push('وعدهٔ زمان تحویل در راهنما مجاز نیست.');
  if (PRICE.test(text)) warnings.push('عدد قیمت در متن راهنما نگذارید.');
  if (/\*\*|INDEX\.md|\bslug\b|\bappid\b|طبق فرم محصول/i.test(text)) {
    warnings.push('خروجی عمومی نباید Markdown خام، INDEX، slug یا ارجاع فرم محصول داشته باشد.');
  }
  return warnings;
}
