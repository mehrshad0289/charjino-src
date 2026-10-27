import { buttonClassName } from '@/components/ui/button/styles';
import { SOCIAL, telegramHrefWithText } from '@/lib/social';
import { absoluteUrl } from '@/lib/site';

type SupportOrderBoxProps = {
  productTitle?: string | null;
  pagePath?: string | null;
};

function supportLinkClass(variant: 'fill' | 'outline') {
  return buttonClassName({
    color: 'primary',
    variant,
    size: 'L',
    previewState: 'default',
    disabled: false,
    loading: false,
    fullWidth: true,
    className: '',
  });
}

export function SupportOrderBox({ productTitle, pagePath }: SupportOrderBoxProps) {
  const pageUrl = pagePath?.trim() ? absoluteUrl(pagePath.trim()) : '';
  const title = productTitle?.trim() ?? '';
  const telegramText = [title ? `سفارش ${title}` : '', pageUrl].filter(Boolean).join('\n');
  const telegramHref = telegramHrefWithText(telegramText);

  return (
    <div className="flex w-full flex-col gap-3 rounded-2xl bg-card px-4 py-4 ring-1 ring-border">
      <p className="text-sm font-semibold text-text">ثبت سفارش از طریق پشتیبانی</p>
      <p className="text-sm leading-7 text-text/80">
        برای ثبت سفارش این محصول، از طریق تلگرام یا اینستاگرام به پشتیبانی شارژینو پیام دهید. سفارش شما
        دستی ثبت و در سریع‌ترین زمان تحویل می‌شود.
      </p>
      <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
        <a href={telegramHref} target="_blank" rel="noopener noreferrer" className={supportLinkClass('fill')}>
          سفارش در تلگرام
        </a>
        <a
          href={SOCIAL.instagram.href}
          target="_blank"
          rel="noopener noreferrer"
          className={supportLinkClass('outline')}
        >
          سفارش در اینستاگرام
        </a>
      </div>
    </div>
  );
}
